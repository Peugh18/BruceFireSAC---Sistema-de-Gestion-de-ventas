<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\EmitElectronicDocument;
use App\Http\Controllers\Controller;
use App\Models\ElectronicDocument;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function index(Team $current_team, Request $request): Response
    {
        $tipo = $request->string('tipo')->toString();
        $estado = $request->string('estado')->toString();
        $mes = $request->string('mes')->toString();
        $vendedorId = $request->user()->id;

        $documents = ElectronicDocument::query()
            ->with('sale.client')
            ->whereHas('sale', fn ($query) => $query->where('vendedor_id', $vendedorId))
            ->when($tipo !== '' && $tipo !== 'todos', fn ($query) => $query->where('tipo', $tipo))
            ->when($estado !== '' && $estado !== 'todos', fn ($query) => $query->where('sunat_estado', $estado))
            ->when($mes !== '', fn ($query) => $query->whereMonth('created_at', (int) $mes))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ElectronicDocument $document) => [
                'id' => $document->id,
                'tipo' => $document->tipo,
                'serie' => $document->serie,
                'correlativo' => $document->correlativo,
                'cliente' => $document->sale->client->razon_social,
                'total' => $document->sale->total,
                'sunat_estado' => $document->sunat_estado,
                'sunat_codigo_respuesta' => $document->sunat_codigo_respuesta,
                'created_at' => $document->created_at?->toDateTimeString(),
            ]);

        // KPIs acotados al vendedor autenticado: misma regla que el Dashboard
        // y el listado de Ventas (Documento Maestro §77.3/§80.2).
        $baseKpiQuery = fn () => ElectronicDocument::whereHas('sale', fn ($query) => $query->where('vendedor_id', $vendedorId));

        return Inertia::render('vendedor/facturacion/index', [
            'documents' => $documents,
            'filters' => [
                'tipo' => $tipo,
                'estado' => $estado,
                'mes' => $mes,
            ],
            'kpis' => [
                'emitidos_hoy' => $baseKpiQuery()->whereDate('created_at', today())->count(),
                'aceptados_hoy' => $baseKpiQuery()->whereDate('created_at', today())->where('sunat_estado', 'aceptado')->count(),
                'observados' => $baseKpiQuery()->where('sunat_estado', 'observado')->count(),
                'rechazados' => $baseKpiQuery()->where('sunat_estado', 'rechazado')->count(),
            ],
        ]);
    }

    public function resend(Team $current_team, ElectronicDocument $electronic_document, EmitElectronicDocument $emitElectronicDocument): RedirectResponse
    {
        if (! in_array($electronic_document->sunat_estado, ['observado', 'excepcion'], true)) {
            abort(422, 'Solo se pueden reenviar documentos observados o con excepción.');
        }

        $emitElectronicDocument->sendDocument($electronic_document);

        return back();
    }

    public function downloadXml(Team $current_team, ElectronicDocument $electronic_document): StreamedResponse
    {
        return $this->downloadPath($electronic_document, $electronic_document->xml_path, 'xml');
    }

    public function downloadCdr(Team $current_team, ElectronicDocument $electronic_document): StreamedResponse
    {
        return $this->downloadPath($electronic_document, $electronic_document->cdr_path, 'zip');
    }

    public function downloadPdf(Team $current_team, ElectronicDocument $electronic_document): StreamedResponse
    {
        return $this->downloadPath($electronic_document, $electronic_document->pdf_path, 'pdf');
    }

    protected function downloadPath(ElectronicDocument $document, ?string $path, string $extension): StreamedResponse
    {
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $this->friendlyFileName($document, $extension));
    }

    /**
     * Mismo patrón que usa Codeplex (proveedor anterior de BRUCE FIRE) para
     * el nombre de archivo al descargar: {Letra}-{Serie}-{Correlativo8}_
     * {Cliente}.{ext}, ej. "F-F001-00000011_MANNUCCI DIESEL SAC.pdf".
     */
    protected function friendlyFileName(ElectronicDocument $document, string $extension): string
    {
        $document->loadMissing('sale.client');

        $letra = match ($document->tipo) {
            'factura' => 'F',
            'boleta' => 'B',
            'nota_credito' => 'NC',
            'nota_debito' => 'ND',
            default => strtoupper($document->tipo),
        };

        $correlativo = str_pad((string) $document->correlativo, 8, '0', STR_PAD_LEFT);
        $cliente = $document->sale->client->razon_social;

        return "{$letra}-{$document->serie}-{$correlativo}_{$cliente}.{$extension}";
    }
}
