<?php

namespace App\Http\Controllers\Almacen;

use App\Actions\Almacen\TransferInventory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\StoreTransferRequest;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(Team $current_team, Request $request): Response
    {
        $sourceSedeId = $this->origen($request);
        $sourceSede = $sourceSedeId ? Sede::query()->find($sourceSedeId) : null;

        return Inertia::render('almacen/traslados/index', [
            'sourceSede' => $sourceSede,
            // El Gerente no tiene almacén propio: elige desde cuál traslada.
            'origenes' => $request->user()->almacenRestringidoId() === null
                ? Sede::query()->whereIn('tipo', ['almacen', 'mixta'])->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
                : [],
            'destinations' => $sourceSede
                ? Sede::query()->whereIn('tipo', ['almacen', 'mixta'])->where('activo', true)->whereKeyNot($sourceSede->id)->orderBy('nombre')->get(['id', 'nombre'])
                : collect(),
            'bulkProducts' => Product::query()->where('activo', true)->where('serializado', false)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function store(Team $current_team, StoreTransferRequest $request, TransferInventory $transfer): RedirectResponse
    {
        $sourceSedeId = $this->origen($request);
        abort_if(! $sourceSedeId, 422, 'No se ha seleccionado una sede de origen válida.');

        $transfer->handle($sourceSedeId, array_filter([
            'destination_sede_id' => $request->integer('destination_sede_id'),
            'serials' => $request->filled('serials') ? array_values(array_map('strval', (array) $request->input('serials'))) : null,
            'product_id' => $request->filled('product_id') ? $request->integer('product_id') : null,
            'quantity' => $request->filled('quantity') ? $request->integer('quantity') : null,
            'observation' => $request->filled('observation') ? $request->string('observation')->toString() : null,
        ], fn ($valor) => $valor !== null), $request->user());

        return back()->with('success', 'Traslado registrado en ambas sedes.');
    }

    /**
     * El almacén del almacenero, o el que eligió el Gerente.
     */
    protected function origen(Request $request): ?int
    {
        $propio = $request->user()->almacenRestringidoId();

        if ($propio !== null) {
            return $propio;
        }

        $elegido = Sede::query()->whereIn('tipo', ['almacen', 'mixta'])->where('activo', true)->find($request->integer('origen_sede_id'));

        return $elegido->id ?? Sede::query()->whereIn('tipo', ['almacen', 'mixta'])->where('activo', true)->orderBy('id')->value('id');
    }
}
