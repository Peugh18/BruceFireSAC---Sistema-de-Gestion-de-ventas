<?php

namespace App\Services\Billing;

use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\Team;

class DocumentosRelacionados
{
    protected const NOMBRES = [
        'factura' => 'Factura',
        'boleta' => 'Boleta',
        'nota_credito' => 'Nota de crédito',
        'nota_debito' => 'Nota de débito',
    ];

    /**
     * La cadena de una venta, en orden de trabajo: cotización, órdenes,
     * comprobantes y guías. Las notas tienen su propio panel en la venta.
     *
     * @return list<array{tipo: string, numero: string, estado: ?string, url: string}>
     */
    public function deLaVenta(Sale $sale, Team $team): array
    {
        $sale->loadMissing('quote', 'serviceOrders', 'electronicDocuments', 'dispatchGuides', 'corrige', 'correccion');
        $filas = [];

        foreach (['Corrige a' => $sale->corrige, 'Corregida por' => $sale->correccion] as $tipo => $venta) {
            if ($venta) {
                $filas[] = $this->fila($tipo, $venta->numero_interno, $venta->estado, route('vendedor.ventas.show', ['current_team' => $team, 'sale' => $venta]));
            }
        }

        if ($sale->quote) {
            $filas[] = $this->fila('Cotización', $sale->quote->numero, $sale->quote->estado, route('vendedor.cotizaciones.pdf', ['current_team' => $team, 'quote' => $sale->quote]));
        }
        foreach ($sale->serviceOrders as $orden) {
            $filas[] = $this->fila('Orden de servicio', $orden->codigo, $orden->estado, route('vendedor.ordenes-servicio.show', ['current_team' => $team, 'service_order' => $orden]));
        }
        foreach ($sale->electronicDocuments->whereIn('tipo', ['factura', 'boleta']) as $documento) {
            $filas[] = $this->fila(
                self::NOMBRES[$documento->tipo],
                self::numero($documento->serie, $documento->correlativo),
                $documento->sunat_estado,
                route('vendedor.facturacion.pdf', ['current_team' => $team, 'electronic_document' => $documento]),
            );
        }
        foreach ($sale->dispatchGuides as $guia) {
            $filas[] = $this->fila('Guía de remisión', self::numero($guia->serie, $guia->correlativo), $guia->estado_sunat, route('guias.show', ['current_team' => $team, 'guide' => $guia]));
        }

        return $filas;
    }

    /**
     * El comprobante que modifica una nota: «Factura F001-00000191».
     */
    public static function referencia(ElectronicDocument $documento): ?string
    {
        $origen = $documento->cpeAfectado;

        return $origen ? (self::NOMBRES[$origen->tipo] ?? $origen->tipo).' '.self::numero($origen->serie, $origen->correlativo) : null;
    }

    protected static function numero(?string $serie, int|string|null $correlativo): string
    {
        return $serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{tipo: string, numero: string, estado: ?string, url: string}
     */
    protected function fila(string $tipo, string $numero, ?string $estado, string $url): array
    {
        return compact('tipo', 'numero', 'estado', 'url');
    }
}
