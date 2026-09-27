<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Service;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    /**
     * Buscador único de Cotización y Venta: por nombre (palabras en cualquier
     * orden), código o código de barras, y por serie BF-EQ exacta. Con una
     * sede indica el stock disponible de cada producto en su almacén.
     */
    public function buscar(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());

        if (mb_strlen($search) < 1) {
            return response()->json(['unidad' => null, 'items' => []]);
        }

        $almacenId = $this->almacenId($request);

        $unidad = $almacenId
            ? InventoryUnit::with('product')
                ->where('numero_serie', $search)
                ->where('sede_almacen_id', $almacenId)
                ->where('estado', 'disponible')
                ->first()
            : null;

        $soloServicios = $request->string('tipo')->toString() === 'service';

        $products = $soloServicios ? collect() : Product::query()
            ->where('activo', true)
            ->where(fn (Builder $query) => $this->coincide($query, $search))
            ->orderByRaw('codigo = ? desc', [$search])
            ->orderBy('nombre')
            ->limit(12)
            ->get()
            ->map(fn (Product $product) => [
                'tipo' => 'product',
                'id' => $product->id,
                'codigo' => $product->codigo,
                'nombre' => $product->nombre,
                'precio_venta' => (float) $product->precio_venta,
                'serializado' => (bool) $product->serializado,
                'stock' => $almacenId ? $this->stock($product, $almacenId) : null,
            ]);

        $services = Service::query()
            ->where('activo', true)
            ->where(fn (Builder $query) => $this->coincide($query, $search))
            ->orderBy('nombre')
            ->limit(8)
            ->get()
            ->map(fn (Service $service) => [
                'tipo' => 'service',
                'id' => $service->id,
                'codigo' => $service->codigo,
                'nombre' => $service->nombre,
                'precio_venta' => (float) $service->precio_venta,
                'serializado' => false,
                'stock' => null,
            ]);

        return response()->json([
            'unidad' => $unidad ? $this->unidadJson($unidad) : null,
            'items' => $products->concat($services)->values(),
        ]);
    }

    /**
     * Series disponibles de un producto en el almacén de la sede, para elegir
     * la unidad exacta cuando se busca el producto por nombre.
     */
    public function unidades(Request $request): JsonResponse
    {
        $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);

        $almacenId = $this->almacenId($request);

        if (! $almacenId) {
            return response()->json([]);
        }

        return response()->json(
            InventoryUnit::with('product')
                ->where('product_id', $request->integer('product_id'))
                ->where('sede_almacen_id', $almacenId)
                ->where('estado', 'disponible')
                ->orderBy('numero_serie')
                ->limit(100)
                ->get()
                ->map(fn (InventoryUnit $unit) => $this->unidadJson($unit)),
        );
    }

    /**
     * Ficha resumida del cliente elegido: datos fiscales, placas y sedes para
     * la tarjeta del buscador y el campo Referencia.
     */
    public function ficha(Team $current_team, Client $client): JsonResponse
    {
        $client->load(['vehicles:id,client_id,placa,descripcion', 'sites:id,client_id,nombre,direccion']);

        return response()->json([
            'id' => $client->id,
            'tipo_documento' => $client->tipo_documento,
            'numero_documento' => $client->numero_documento,
            'razon_social' => $client->razon_social,
            'nombre_comercial' => $client->nombre_comercial,
            'direccion_fiscal' => $client->direccion_fiscal,
            'estado_contribuyente' => $client->estado_contribuyente,
            'condicion_domicilio' => $client->condicion_domicilio,
            'vehiculos' => $client->vehicles->map->only(['id', 'placa', 'descripcion'])->values(),
            'sedes' => $client->sites->map->only(['id', 'nombre', 'direccion'])->values(),
        ]);
    }

    protected function coincide(Builder $query, string $search): void
    {
        $query->where('codigo', $search)
            ->orWhere(function (Builder $query) use ($search) {
                foreach (preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    $query->where(fn (Builder $query) => $query
                        ->where('nombre', 'like', "%{$word}%")
                        ->orWhere('codigo', 'like', "%{$word}%"));
                }
            });
    }

    protected function almacenId(Request $request): ?int
    {
        $sedeId = $request->user()->sedeRestringidaId() ?? $request->integer('sede_id');

        return $sedeId ? Sede::find($sedeId)?->almacenEfectivoId() : null;
    }

    protected function stock(Product $product, int $almacenId): int
    {
        if ($product->serializado) {
            return InventoryUnit::where('product_id', $product->id)
                ->where('sede_almacen_id', $almacenId)
                ->where('estado', 'disponible')
                ->count();
        }

        return max(0, (int) InventoryMovement::where('product_id', $product->id)
            ->where('sede_id', $almacenId)
            ->sum('cantidad'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function unidadJson(InventoryUnit $unit): array
    {
        return [
            'inventory_unit_id' => $unit->id,
            'numero_serie' => $unit->numero_serie,
            'product_id' => $unit->product_id,
            'nombre' => $unit->product->nombre,
            'precio_venta' => (float) $unit->product->precio_venta,
            'capacidad' => $unit->capacidad,
            'marca' => $unit->marca,
            'serie_fabricante' => $unit->serie_fabricante,
        ];
    }
}
