<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\EmitElectronicDocument;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Models\ElectronicDocument;
use App\Models\Team;
use App\Services\Billing\PrecioConIgv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Phar;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    use AcotaPorSede;

    /**
     * Máximo de comprobantes por ZIP para no agotar memoria ni tiempo.
     */
    public const LIMITE_DESCARGA = 500;

    public function index(Team $current_team, Request $request): Response
    {
        $tipo = $request->string('tipo')->toString();
        $estado = $request->string('estado')->toString();
        $mes = $request->string('mes')->toString();
        $buscar = $request->string('buscar')->toString();
        $vendedorId = $request->user()->id;

        $documents = $this->consulta($request)
            ->with('sale.client')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ElectronicDocument $document) => [
                'id' => $document->id,
                'sale_id' => $document->sale_id,
                'tipo' => $document->tipo,
                'serie' => $document->serie,
                'correlativo' => $document->correlativo,
                'cliente' => $document->sale->client->razon_social,
                'total' => $document->sale->total,
                'sunat_estado' => $document->sunat_estado,
                'sunat_codigo_respuesta' => $document->sunat_codigo_respuesta,
                'sunat_mensaje' => $document->sunat_mensaje,
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
                'buscar' => $buscar,
            ],
            'totalFiltrados' => $this->consulta($request)->count(),
            'kpis' => [
                'emitidos_hoy' => $baseKpiQuery()->whereDate('created_at', today())->count(),
                'aceptados_hoy' => $baseKpiQuery()->whereDate('created_at', today())->where('sunat_estado', 'aceptado')->count(),
                'observados' => $baseKpiQuery()->where('sunat_estado', 'observado')->count(),
                'rechazados' => $baseKpiQuery()->where('sunat_estado', 'rechazado')->count(),
            ],
        ]);
    }

    /**
     * Descarga masiva: un ZIP con carpetas xml/, cdr/ y pdf/ de los
     * comprobantes marcados (ids[]) o de todo lo filtrado.
     */
    public function descargaMasiva(Team $current_team, Request $request): BinaryFileResponse
    {
        $incluir = array_intersect($request->input('incluir', ['xml', 'cdr', 'pdf']), ['xml', 'cdr', 'pdf']);
        $documentos = $this->seleccion($request)->with('sale.client')->limit(self::LIMITE_DESCARGA)->get();

        abort_if($documentos->isEmpty() || $incluir === [], 422, 'No hay comprobantes para descargar.');

        $directorio = storage_path('app/private/tmp');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }

        $ruta = $directorio.'/comprobantes-'.now()->format('Ymd-His').'-'.Str::random(6).'.zip';
        $zip = new PharData($ruta, 0, null, Phar::ZIP);
        $agregados = 0;

        foreach ($documentos as $documento) {
            foreach (['xml' => $documento->xml_path, 'cdr' => $documento->cdr_path, 'pdf' => $documento->pdf_path] as $carpeta => $path) {
                if (! in_array($carpeta, $incluir, true) || ! $path || ! Storage::disk('local')->exists($path)) {
                    continue;
                }

                $extension = pathinfo($path, PATHINFO_EXTENSION);
                $zip->addFromString("{$carpeta}/".$this->friendlyFileName($documento, $extension), (string) Storage::disk('local')->get($path));
                $agregados++;
            }
        }

        abort_if($agregados === 0, 422, 'Los comprobantes elegidos aún no tienen archivos generados.');

        return response()->download($ruta, 'comprobantes-'.now()->format('Y-m-d').'.zip')->deleteFileAfterSend();
    }

    /**
     * Registro de ventas en CSV (abre en Excel) de los comprobantes marcados
     * o de todo lo filtrado. Las notas de crédito restan.
     */
    public function exportarExcel(Team $current_team, Request $request): StreamedResponse
    {
        $documentos = $this->seleccion($request)->with(['sale.client', 'cpeAfectado'])->orderBy('created_at')->get();

        return response()->streamDownload(function () use ($documentos) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Fecha emisión', 'Tipo', 'Serie', 'Número', 'Tipo doc. cliente', 'N° doc. cliente', 'Razón social', 'Base imponible', 'IGV', 'Total', 'Estado SUNAT', 'Código SUNAT', 'Comprobante afectado', 'Referencia'], ';');

            foreach ($documentos as $documento) {
                $sale = $documento->sale;
                $esNota = in_array($documento->tipo, ['nota_credito', 'nota_debito'], true);
                $montos = $esNota
                    ? PrecioConIgv::desglosar((float) $documento->importe)
                    : ['base' => (float) $sale->subtotal, 'igv' => (float) $sale->igv, 'total' => (float) $sale->total];
                $signo = $documento->tipo === 'nota_credito' ? -1 : 1;

                fputcsv($salida, [
                    ($documento->fecha_emision ?? $documento->created_at)?->format('d/m/Y'),
                    strtoupper(str_replace('_', ' ', $documento->tipo)),
                    $documento->serie,
                    str_pad((string) $documento->correlativo, 8, '0', STR_PAD_LEFT),
                    strtoupper($sale->client->tipo_documento),
                    $sale->client->numero_documento,
                    $sale->client->razon_social,
                    number_format($signo * $montos['base'], 2, '.', ''),
                    number_format($signo * $montos['igv'], 2, '.', ''),
                    number_format($signo * $montos['total'], 2, '.', ''),
                    $documento->sunat_estado,
                    $documento->sunat_codigo_respuesta,
                    $documento->cpeAfectado ? "{$documento->cpeAfectado->serie}-{$documento->cpeAfectado->correlativo}" : '',
                    $sale->referencia,
                ], ';');
            }

            fclose($salida);
        }, 'registro-ventas-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Comprobantes del vendedor con los filtros del listado.
     *
     * @return Builder<ElectronicDocument>
     */
    protected function consulta(Request $request): Builder
    {
        $tipo = $request->string('tipo')->toString();
        $estado = $request->string('estado')->toString();
        $mes = $request->string('mes')->toString();
        $buscar = trim($request->string('buscar')->toString());

        return ElectronicDocument::query()
            ->whereHas('sale', fn ($query) => $query->where('vendedor_id', $request->user()->id))
            ->when($tipo === 'nota', fn ($query) => $query->whereIn('tipo', ['nota_credito', 'nota_debito']))
            ->when(in_array($tipo, ['factura', 'boleta', 'nota_credito', 'nota_debito'], true), fn ($query) => $query->where('tipo', $tipo))
            ->when($estado !== '' && $estado !== 'todos', fn ($query) => $query->where('sunat_estado', $estado))
            ->when(preg_match('/^\d{4}-\d{2}$/', $mes) === 1, fn ($query) => $query
                ->whereYear('created_at', (int) substr($mes, 0, 4))
                ->whereMonth('created_at', (int) substr($mes, 5, 2)))
            ->when($buscar !== '', function ($query) use ($buscar) {
                $numero = preg_match('/^([A-Z0-9]{4})-0*(\d+)$/i', $buscar, $partes) === 1 ? $partes : null;

                $query->where(function ($query) use ($buscar, $numero) {
                    if ($numero) {
                        $query->orWhere(fn ($q) => $q->where('serie', strtoupper($numero[1]))->where('correlativo', (int) $numero[2]));
                    }

                    $query->orWhere('serie', 'like', "%{$buscar}%")
                        ->orWhereHas('sale.client', fn ($q) => $q->buscar($buscar));
                });
            });
    }

    /**
     * @return Builder<ElectronicDocument>
     */
    protected function seleccion(Request $request): Builder
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));

        return $ids !== []
            ? $this->consulta($request)->whereIn('id', $ids)
            : $this->consulta($request);
    }

    public function resend(Team $current_team, ElectronicDocument $electronic_document, EmitElectronicDocument $emitElectronicDocument): RedirectResponse
    {
        $this->asegurarSede($electronic_document->sale?->sede_id);

        if (! in_array($electronic_document->sunat_estado, ['pendiente', 'observado', 'excepcion'], true)) {
            abort(422, 'Solo se pueden reenviar documentos pendientes observados o con excepción.');
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
        $this->asegurarSede($document->sale?->sede_id);

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
