<?php

namespace App\Services\Billing;

use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\ServiceOrder;

/**
 * Datos con los que nace una guía según el movimiento que la origina, para
 * no volver a escribirlos: venta (motivo 01), orden de recojo o entrega
 * (motivo configurable, 13 por defecto) y traslado entre sedes (motivo 04).
 *
 * @phpstan-type Linea array{product_id:int|null,codigo:string|null,descripcion:string,unidad:string,cantidad:int|float,peso_kg:float}
 * @phpstan-type Datos array<string, mixed>
 */
class GuiaRemisionPrefill
{
    /**
     * @return Datos
     */
    public function desdeVenta(Sale $sale): array
    {
        $sale->loadMissing('client', 'sede.ubicacion', 'items.product', 'electronicDocuments');

        $items = $sale->lineasComprobante()
            ->filter(fn ($linea) => $linea->product !== null)
            ->map(fn ($linea) => $this->lineaDeProducto($linea->product, (int) $linea->cantidad))
            ->values()->all();

        $documento = $sale->electronicDocuments
            ->whereIn('tipo', ['factura', 'boleta'])
            ->reject(fn (ElectronicDocument $d) => $d->fueRechazado())
            ->sortBy('id')->last();

        return [
            'sale_id' => $sale->id,
            'sede_id' => $sale->sede_id,
            'motivo' => '01',
            'motivo_descripcion' => null,
            'doc_relacionado_tipo' => $documento ? ($documento->tipo === 'factura' ? '01' : '03') : null,
            'doc_relacionado_numero' => $documento ? "{$documento->serie}-{$documento->correlativo}" : null,
            ...$this->destinatario($sale->client),
            ...$this->partidaDeSede($sale->sede),
            ...$this->llegadaDeCliente($sale->client),
            'items' => $items,
        ];
    }

    /**
     * @return Datos
     */
    public function desdeOrden(ServiceOrder $orden, string $etapa): array
    {
        $orden->loadMissing('client', 'sede.ubicacion', 'equipments.product');
        $esRecojo = $etapa === 'recojo';

        $items = $orden->equipments->map(fn ($equipo) => [
            'product_id' => $equipo->product_id,
            'codigo' => $equipo->numero_serie,
            'descripcion' => $equipo->product->nombre.' (serie '.$equipo->numero_serie.')',
            'unidad' => 'NIU',
            'cantidad' => 1,
            'peso_kg' => (float) ($equipo->product->peso_kg ?? 0),
        ])->values()->all();

        return [
            'service_order_id' => $orden->id,
            'sede_id' => $orden->sede_id,
            'motivo' => (string) config('billing.gre.motivo_ordenes'),
            'motivo_descripcion' => (string) config($esRecojo ? 'billing.gre.descripcion_recojo' : 'billing.gre.descripcion_entrega'),
            'doc_relacionado_tipo' => null,
            'doc_relacionado_numero' => null,
            ...$this->destinatario($orden->client),
            ...($esRecojo
                ? [...$this->partidaDeCliente($orden->client), ...$this->llegadaDeSede($orden->sede)]
                : [...$this->partidaDeSede($orden->sede), ...$this->llegadaDeCliente($orden->client)]),
            'items' => $items,
        ];
    }

    /**
     * @return Datos
     */
    public function desdeTraslado(InventoryTransfer $traslado): array
    {
        $traslado->loadMissing('origen.ubicacion', 'destino.ubicacion', 'items.product');
        $empresa = CompanySetting::current();

        return [
            'inventory_transfer_id' => $traslado->id,
            'sede_id' => $traslado->origen_sede_id,
            'motivo' => '04',
            'motivo_descripcion' => null,
            'doc_relacionado_tipo' => null,
            'doc_relacionado_numero' => null,
            'destinatario_tipo_doc' => '6',
            'destinatario_num_doc' => $empresa->ruc,
            'destinatario_nombre' => $empresa->razon_social,
            ...$this->partidaDeSede($traslado->origen),
            ...$this->llegadaDeSede($traslado->destino),
            'items' => $traslado->items->groupBy('product_id')->map(
                fn ($grupo) => $this->lineaDeProducto($grupo->first()->product, (int) $grupo->sum('cantidad'))
            )->values()->all(),
        ];
    }

    /**
     * @return Linea
     */
    protected function lineaDeProducto(Product $producto, int $cantidad): array
    {
        return [
            'product_id' => $producto->id,
            'codigo' => $producto->codigo,
            'descripcion' => $producto->nombre,
            'unidad' => $this->unidad($producto),
            'cantidad' => $cantidad,
            'peso_kg' => round((float) ($producto->peso_kg ?? 0) * $cantidad, 3),
        ];
    }

    protected function unidad(Product $producto): string
    {
        return match (mb_strtolower(trim($producto->unidad_medida))) {
            'kg', 'kgm', 'kilogramo' => 'KGM',
            'ltr', 'litro' => 'LTR',
            'mtr', 'metro' => 'MTR',
            'par', 'pr' => 'PR',
            default => 'NIU',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function destinatario(Client $cliente): array
    {
        return [
            'destinatario_tipo_doc' => $cliente->tipo_documento === 'ruc' ? '6' : '1',
            'destinatario_num_doc' => $cliente->numero_documento,
            'destinatario_nombre' => $cliente->razon_social,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function partidaDeSede(?Sede $sede): array
    {
        return [
            'partida_ubigeo' => $sede->ubigeo ?? CompanySetting::current()->ubigeo,
            'partida_direccion' => $sede->direccion ?? CompanySetting::current()->direccion,
            'partida_cod_establecimiento' => $sede->cod_establecimiento_anexo ?? '0000',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function llegadaDeSede(?Sede $sede): array
    {
        return [
            'llegada_ubigeo' => $sede->ubigeo ?? CompanySetting::current()->ubigeo,
            'llegada_direccion' => $sede->direccion ?? CompanySetting::current()->direccion,
            'llegada_cod_establecimiento' => $sede->cod_establecimiento_anexo ?? '0000',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function partidaDeCliente(Client $cliente): array
    {
        return ['partida_ubigeo' => $cliente->ubigeo, 'partida_direccion' => $cliente->direccion_fiscal, 'partida_cod_establecimiento' => null];
    }

    /**
     * @return array<string, string|null>
     */
    protected function llegadaDeCliente(Client $cliente): array
    {
        return ['llegada_ubigeo' => $cliente->ubigeo, 'llegada_direccion' => $cliente->direccion_fiscal, 'llegada_cod_establecimiento' => null];
    }
}
