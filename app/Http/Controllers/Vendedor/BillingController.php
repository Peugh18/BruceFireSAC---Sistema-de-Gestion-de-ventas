<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Billing\EmitElectronicDocument;
use App\Actions\Billing\VoidElectronicDocument;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Http\Controllers\Vendedor\Concerns\FiltraPorFechas;
use App\Models\ElectronicDocument;
use App\Models\Team;
use App\Services\Billing\ComprobantePdfService;
use App\Services\Billing\MensajeSunat;
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
    use FiltraPorFechas;

    /**
     * Máximo de comprobantes por ZIP para no agotar memoria ni tiempo.
     */
    public const LIMITE_DESCARGA = 500;

    public function index(Team $current_team, Request $request): Response
    {
        $tipo = $request->string('tipo')->toString();
        $estado = $request->string('estado')->toString();
        $buscar = $request->string('buscar')->toString();
        [$desde, $hasta] = $this->rangoDeFechas($request);
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
                // Una nota de crédito o débito vale su importe, no el de la venta.
                'total' => in_array($document->tipo, ['nota_credito', 'nota_debito'], true) && $document->importe !== null
                    ? $document->importe
                    : $document->sale->total,
                'sunat_estado' => $document->sunat_estado,
                'sunat_codigo_respuesta' => $document->sunat_codigo_respuesta,
                'sunat_mensaje' => $document->sunat_mensaje,
                'sunat_mensaje_simple' => MensajeSunat::simple($document->sunat_mensaje, $document->sunat_codigo_respuesta),
                'created_at' => $document->created_at?->format('d/m/Y H:i'),
            ]);

        // KPIs acotados al vendedor autenticado: misma regla que el Dashboard
        // y el listado de Ventas (Documento Maestro §77.3/§80.2).
        $baseKpiQuery = fn () => ElectronicDocument::whereHas('sale', fn ($query) => $query->where('vendedor_id', $vendedorId));

        return Inertia::render('vendedor/facturacion/index', [
            'documents' => $documents,
            'filters' => [
                'tipo' => $tipo,
                'estado' => $estado,
                'buscar' => $buscar,
                'plazo' => $request->string('plazo')->toString(),
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
            ],
            'hoy' => today()->toDateString(),
            'totalFiltrados' => $this->consulta($request)->count(),
            'kpis' => [
                'emitidos_hoy' => $baseKpiQuery()->whereDate('created_at', today())->count(),
                'aceptados_hoy' => $baseKpiQuery()->whereDate('created_at', today())->where('sunat_estado', 'aceptado')->count(),
                'observados' => $baseKpiQuery()->where('sunat_estado', 'observado')->count(),
                'rechazados' => $baseKpiQuery()->where('sunat_estado', 'rechazado')->count(),
            ],
            'plazoSunat' => [
                'por_vencer' => $baseKpiQuery()->porVencerSunat()->count(),
                'vencidos' => $baseKpiQuery()->vencidosSunat()->count(),
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
            $pdf = in_array('pdf', $incluir, true) ? app(ComprobantePdfService::class)->vigente($documento) : null;

            foreach (['xml' => $documento->xml_path, 'cdr' => $documento->cdr_path, 'pdf' => $pdf] as $carpeta => $path) {
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
        $buscar = trim($request->string('buscar')->toString());
        $plazo = $request->string('plazo')->toString();
        [$desde, $hasta] = $this->rangoDeFechas($request);

        return ElectronicDocument::query()
            ->whereHas('sale', fn ($query) => $query->where('vendedor_id', $request->user()->id))
            ->when($tipo === 'nota', fn ($query) => $query->whereIn('tipo', ['nota_credito', 'nota_debito']))
            ->when(in_array($tipo, ['factura', 'boleta', 'nota_credito', 'nota_debito'], true), fn ($query) => $query->where('tipo', $tipo))
            ->when($estado !== '' && $estado !== 'todos', fn ($query) => $query->where('sunat_estado', $estado))
            // Aviso de plazo SUNAT (S8): muestra todos los que vencen, sin el rango de fechas.
            ->when($plazo === 'por_vencer', fn ($query) => $query->porVencerSunat())
            ->when($plazo === 'vencidos', fn ($query) => $query->vencidosSunat())
            // Fecha del comprobante (la que va a SUNAT), no la de registro.
            ->when(! in_array($plazo, ['por_vencer', 'vencidos'], true), fn ($query) => $query
                ->whereRaw('COALESCE(fecha_emision, DATE(created_at)) BETWEEN ? AND ?', [$desde->toDateString(), $hasta->toDateString()]))
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
        $this->asegurarVenta($electronic_document->sale);

        if (! in_array($electronic_document->sunat_estado, ['pendiente', 'observado', 'excepcion'], true)) {
            abort(422, 'Solo se pueden reenviar documentos pendientes observados o con excepción.');
        }

        $emitElectronicDocument->sendDocument($electronic_document);

        return back();
    }

    /**
     * Comunicación de baja: el vendedor declara que el comprobante no se
     * entregó al cliente y da el motivo (máx. 100 caracteres, límite SUNAT).
     */
    public function baja(Team $current_team, ElectronicDocument $electronic_document, Request $request, VoidElectronicDocument $voidElectronicDocument): RedirectResponse
    {
        $this->asegurarVenta($electronic_document->sale);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:100'],
            'no_entregado' => ['accepted'],
        ], [
            'no_entregado.accepted' => 'Confirma que el comprobante no se entregó al cliente. Si ya lo entregaste, emite una nota de crédito.',
        ]);

        $documento = $voidElectronicDocument->handle($electronic_document, $datos['motivo'], noEntregado: true);
        $numero = "{$documento->serie}-{$documento->correlativo}";

        return match ($documento->sunat_estado) {
            'anulado' => back()->with('success', "SUNAT aceptó la baja de {$numero}. La venta quedó anulada."),
            'baja_pendiente' => back()->with('success', "Baja de {$numero} enviada a SUNAT. Te avisaremos aquí cuando la acepte."),
            default => back()->with('error', (string) $documento->baja_mensaje),
        };
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
        $this->asegurarVenta($electronic_document->sale);

        return $this->downloadPath($electronic_document, app(ComprobantePdfService::class)->vigente($electronic_document), 'pdf');
    }

    protected function downloadPath(ElectronicDocument $document, ?string $path, string $extension): StreamedResponse
    {
        $this->asegurarVenta($document->sale);

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        // El PDF se redibuja cuando cambia el diseño: que el navegador no
        // muestre una copia guardada.
        return Storage::disk('local')->download($path, $this->friendlyFileName($document, $extension), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
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
