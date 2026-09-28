<?php

namespace App\Http\Controllers\Almacen;

use App\Actions\Almacen\TransferInventory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\StoreTransferRequest;
use App\Models\Product;
use App\Models\Sede;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(Team $current_team): Response
    {
        $sourceSedeId = request()->user()->almacenRestringidoId();

        return Inertia::render('almacen/traslados/index', [
            'sourceSede' => Sede::query()->findOrFail($sourceSedeId),
            'destinations' => Sede::query()->whereIn('tipo', ['almacen', 'mixta'])->where('activo', true)->whereKeyNot($sourceSedeId)->orderBy('nombre')->get(['id', 'nombre']),
            'bulkProducts' => Product::query()->where('activo', true)->where('serializado', false)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function store(Team $current_team, StoreTransferRequest $request, TransferInventory $transfer): RedirectResponse
    {
        $transfer->handle((int) $request->user()->almacenRestringidoId(), array_filter([
            'destination_sede_id' => $request->integer('destination_sede_id'),
            'serials' => $request->filled('serials') ? array_values(array_map('strval', (array) $request->input('serials'))) : null,
            'product_id' => $request->filled('product_id') ? $request->integer('product_id') : null,
            'quantity' => $request->filled('quantity') ? $request->integer('quantity') : null,
            'observation' => $request->filled('observation') ? $request->string('observation')->toString() : null,
        ], fn ($valor) => $valor !== null), $request->user());

        return back()->with('success', 'Traslado registrado en ambas sedes.');
    }
}
