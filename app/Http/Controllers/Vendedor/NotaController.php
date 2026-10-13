<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Models\ElectronicDocument;
use App\Models\Sale;
use App\Models\Team;
use App\Services\Billing\LineasDelComprobante;
use Inertia\Inertia;
use Inertia\Response;

class NotaController extends Controller
{
    use AcotaPorSede;

    /**
     * Pantalla de la nota de crédito o débito: la misma boleta o factura, con
     * sus ítems ya puestos. El vendedor solo acepta (o elige qué devolver o
     * qué cobrar de más) y la nota sale directo a SUNAT.
     */
    public function create(Team $current_team, Sale $sale, string $tipo): Response
    {
        abort_unless(in_array($tipo, ['credito', 'debito'], true), 404);
        $this->asegurarVenta($sale);
        $sale->load('client', 'electronicDocuments');

        $original = LineasDelComprobante::original($sale);
        abort_unless($original !== null, 404);

        $acreditado = (float) $sale->electronicDocuments
            ->where('cpe_afectado_id', $original->id)
            ->where('tipo', 'nota_credito')
            ->where('sunat_estado', '!=', 'rechazado')
            ->sum('importe');

        return Inertia::render('vendedor/ventas/nota', [
            'tipo' => $tipo,
            // Tras anular todo se vuelve aquí para imprimir la nota; ya no admite otra.
            'anulada' => $sale->estado === 'anulada',
            'venta' => ['id' => $sale->id, 'numero' => $sale->numero_interno, 'cliente' => $sale->client->razon_social, 'documento' => $sale->client->numero_documento],
            'original' => [
                'id' => $original->id,
                'tipo' => $original->tipo,
                'numero' => "{$original->serie}-{$original->correlativo}",
                'fecha' => $original->created_at?->toDateString(),
                'total' => (float) $sale->total,
            ],
            'porAcreditar' => round(max((float) $sale->total - $acreditado, 0), 2),
            'lineas' => LineasDelComprobante::de($sale),
            'notas' => $sale->electronicDocuments
                ->whereIn('tipo', ['nota_credito', 'nota_debito'])
                ->sortByDesc('id')
                ->values()
                ->map(fn (ElectronicDocument $nota) => [
                    'id' => $nota->id,
                    'tipo' => $nota->tipo,
                    'numero' => "{$nota->serie}-{$nota->correlativo}",
                    'importe' => (float) $nota->importe,
                    'estado' => $nota->sunat_estado,
                    'mensaje' => $nota->sunat_mensaje,
                ]),
        ]);
    }
}
