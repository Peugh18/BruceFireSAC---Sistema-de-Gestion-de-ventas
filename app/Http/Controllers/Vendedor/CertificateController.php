<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Actions\Certificates\IssueCertificate;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Http\Requests\Certificates\CorregirCertificadoRequest;
use App\Models\Certificate;
use App\Models\CertificateParticipant;
use App\Models\CertificateRevision;
use App\Models\CertificateType;
use App\Models\CertificateUnit;
use App\Models\Sale;
use App\Models\Team;
use App\Services\Certificates\CertificatePdfService;
use App\Services\Certificates\CertificateWordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
    use AcotaPorSede;

    /**
     * Lista de certificados con la venta y el comprobante a los que
     * pertenecen. Se busca por número, cliente, documento, venta o serie del
     * extintor, y se filtra por tipo en el servidor (no solo en la página).
     */
    public function index(Team $current_team, Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $tipo = $request->string('tipo')->toString();
        $estado = $request->string('estado')->toString();

        $certificates = $this->certificadosDeMiSede(Certificate::query())
            ->with(['certificateType', 'client', 'certificateUnits', 'serviceOrder:id,codigo', 'sale' => fn ($query) => $query->visiblePara(request()->user()), 'sale.electronicDocuments'])
            ->withCount(['participants' => fn ($query) => $query->whereNull('anulado_at')])
            ->when($tipo !== '', fn ($query) => $query->whereHas('certificateType', fn ($q) => $q->where('codigo', $tipo)))
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero', 'like', "%{$search}%")
                        ->orWhere('referencia', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($cq) => $cq->where('razon_social', 'like', "%{$search}%")->orWhere('numero_documento', 'like', "%{$search}%"))
                        ->orWhereHas('sale', fn ($sq) => $sq->visiblePara(request()->user())->whereColumn('sales.client_id', 'certificates.client_id')->where(fn ($venta) => $venta->where('numero_interno', 'like', "%{$search}%")->orWhere('numero_nota_venta', 'like', "%{$search}%")))
                        ->orWhereHas('certificateUnits', fn ($uq) => $uq->where('numero_serie_snapshot', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            // ponytail: se agrupa en la pantalla por atención; un grupo grande
            // puede partirse entre páginas. Agrupar en SQL si llega a pasar seguido.
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Certificate $c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'tipo' => $c->certificateType?->nombre,
                'tipo_codigo' => $c->certificateType?->codigo,
                'cliente' => $c->client?->razon_social,
                'documento' => $c->client->numero_documento,
                'client_id' => $c->client_id,
                'orden' => $c->serviceOrder?->codigo,
                'codigos' => $c->certificateUnits->pluck('codigo_interno')->filter()->values()->all(),
                'referencia' => $c->referencia,
                'venta' => $this->venta($c),
                'unidades' => $c->certificateUnits->pluck('numero_serie_snapshot')->all(),
                'participantes' => $c->participants_count,
                'fecha_emision' => $c->fecha_emision?->toDateString(),
                'fecha_vigencia_hasta' => $c->fecha_vigencia_hasta?->toDateString(),
                'estado' => $c->estado,
            ]);

        return Inertia::render('vendedor/certificados/index', [
            'certificates' => $certificates,
            'tipos' => CertificateType::query()->whereHas('certificates')->orderBy('nombre')->get(['codigo', 'nombre']),
            'filters' => [
                'search' => $search,
                'tipo' => $tipo,
                'estado' => $estado,
            ],
        ]);
    }

    /**
     * Detalle del certificado con su vista previa, la venta de la que salió
     * y el camino para corregirlo.
     *
     * Nota: {current_team} precede a {certificate} en la ruta y el dispatcher
     * de Laravel pasa los parámetros de ruta por POSICIÓN a menos que cada uno
     * tenga un parámetro correspondiente en el método.
     */
    public function show(Team $current_team, Certificate $certificate): Response
    {
        $this->asegurarSedeDeCertificado($certificate);

        $certificate->load(['certificateType', 'client', 'certificateUnits', 'participants', 'revisions.user', 'sale' => fn ($query) => $query->visiblePara(request()->user()), 'sale.electronicDocuments']);
        $tipo = $certificate->certificateType;

        return Inertia::render('vendedor/certificados/show', [
            'certificate' => [
                'id' => $certificate->id,
                'numero' => $certificate->numero,
                'revision' => $certificate->revision,
                'tipo' => $tipo?->nombre,
                'tipo_codigo' => $tipo?->codigo,
                'es_diploma' => $tipo?->codigo === 'capacitacion',
                'referencia' => $certificate->referencia,
                'direccion' => $certificate->direccion,
                'corregir_datos_url' => $certificate->estado === 'vigente' && request()->user()?->can('certificates.print')
                    ? route('vendedor.certificados.corregir', ['current_team' => $current_team, 'certificate' => $certificate])
                    : null,
                'cliente' => $certificate->client ? [
                    'id' => $certificate->client->id,
                    'razon_social' => $certificate->client->razon_social,
                    'numero_documento' => $certificate->client->numero_documento,
                ] : null,
                'venta' => $this->venta($certificate),
                'fecha_emision' => $certificate->fecha_emision?->toDateString(),
                'fecha_vigencia_hasta' => $certificate->fecha_vigencia_hasta?->toDateString(),
                'estado' => $certificate->estado,
                'verificar_url' => route('certificados.verificar', ['token' => $certificate->qr_token]),
                'corregir_url' => $this->corregirUrl($current_team, $certificate),
                'unidades' => $certificate->certificateUnits->sortBy(fn (CertificateUnit $unidad) => [(int) $unidad->numero_cliente, $unidad->id])->map(fn (CertificateUnit $unidad) => [
                    'id' => $unidad->id,
                    'numero_cliente' => $unidad->numero_cliente,
                    'serie' => $unidad->numero_serie_snapshot,
                    'marca' => $unidad->marca,
                    'capacidad' => $unidad->capacidad,
                    'agente' => $unidad->tipo_agente,
                    'anio_fabricacion' => $unidad->anio_fabricacion,
                ])->values()->all(),
                'participants' => $certificate->participants->whereNull('anulado_at')->map(fn (CertificateParticipant $participant) => [
                    'id' => $participant->id,
                    'numero' => $participant->numero(),
                    'nombres' => $participant->nombres,
                    'dni' => $participant->dni,
                    'cargo' => $participant->cargo,
                ])->values()->all(),
                'revisiones' => $certificate->revisions->sortByDesc('numero_revision')->map(fn (CertificateRevision $revision) => [
                    'numero' => $revision->numero_revision,
                    'motivo' => $revision->motivo,
                    'usuario' => $revision->user?->name,
                    'fecha' => $revision->created_at?->format('d/m/Y H:i'),
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Genera y sirve el PDF del certificado (§83.2). `inline=1` lo abre en
     * el navegador (para imprimir); sin ese parámetro se descarga.
     */
    public function pdf(
        Team $current_team,
        Certificate $certificate,
        Request $request,
        CertificatePdfService $certificatePdfService
    ): HttpResponse {
        $this->asegurarSedeDeCertificado($certificate);
        $participant = $this->participant($certificate, $request);
        $pdf = $certificatePdfService->generate($certificate, $participant);
        $filename = 'certificado-'.($participant?->numero() ?? $certificate->numero).'.pdf';
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $filename),
        ]);
    }

    /**
     * Descarga el certificado en Word (.docx) con el mismo diseño del PDF,
     * para retocarlo a mano cuando haga falta.
     */
    public function word(
        Team $current_team,
        Certificate $certificate,
        Request $request,
        CertificateWordService $certificateWordService
    ): BinaryFileResponse {
        $this->asegurarSedeDeCertificado($certificate);
        $participant = $this->participant($certificate, $request);
        $numero = $participant?->numero() ?? $certificate->numero;

        return response()
            ->download($certificateWordService->generate($certificate, $participant), "certificado-{$numero}.docx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend();
    }

    protected function participant(Certificate $certificate, Request $request): ?CertificateParticipant
    {
        if (! $request->filled('participant')) {
            return null;
        }

        return $certificate->participants()->whereKey($request->integer('participant'))->firstOrFail();
    }

    /**
     * @return array{id: int, numero: string|null, comprobante: string|null}|null
     */
    protected function venta(Certificate $certificate): ?array
    {
        $sale = $certificate->sale;

        return $sale && $sale->client_id === $certificate->client_id && Sale::query()->visiblePara(request()->user())->whereKey($sale->id)->exists() ? [
            'id' => $sale->id,
            'numero' => $sale->numero_interno,
            'comprobante' => $sale->numeroComprobante(),
        ] : null;
    }

    /**
     * Dónde se corrige: los de servicio en su formulario; los de extintores y
     * capacitación en "Armar certificados" de la venta. Mismo número, nueva
     * revisión.
     */
    protected function corregirUrl(Team $team, Certificate $certificate): ?string
    {
        if (! $certificate->sale_id || $certificate->estado !== 'vigente' || ! $certificate->certificateType) {
            return null;
        }

        if (! Sale::query()->visiblePara(request()->user())->whereKey($certificate->sale_id)->where('client_id', $certificate->client_id)->exists()) {
            return null;
        }

        $codigo = $certificate->certificateType->codigo;

        if (in_array($codigo, EmitirCertificadoDeServicio::TIPOS_DE_EXTINTORES, true)) {
            return route('vendedor.ventas.certificados.create', ['current_team' => $team, 'sale' => $certificate->sale_id]);
        }

        return route('vendedor.ventas.certificado-servicio.create', ['current_team' => $team, 'sale' => $certificate->sale_id, 'tipo' => $codigo]);
    }

    /**
     * Corrige los datos de un certificado ya emitido (dirección, serie, marca,
     * capacidad...) sin cambiar su número: queda una revisión con el motivo,
     * el antes y el después, y el registro en auditoría.
     */
    public function corregir(
        Team $current_team,
        Certificate $certificate,
        CorregirCertificadoRequest $request,
        IssueCertificate $action
    ): RedirectResponse {
        $this->asegurarSedeDeCertificado($certificate);

        if ($certificate->estado !== 'vigente') {
            throw ValidationException::withMessages(['certificado' => 'Solo se corrige un certificado vigente.']);
        }

        $datos = $request->validated();
        /** @var array<int, array<string, mixed>> $editadas */
        $editadas = $request->array('unidades');
        $cambios = collect($editadas)->keyBy('id');
        $unidades = $certificate->certificateUnits()->orderBy('orden')->get()
            ->map(fn (CertificateUnit $unidad) => [
                'equipment_id' => $unidad->equipment_id,
                'numero_cliente' => $unidad->numero_cliente,
                'numero_serie' => $cambios->get($unidad->id)['numero_serie'] ?? $unidad->numero_serie_snapshot,
                'marca' => $cambios->get($unidad->id)['marca'] ?? $unidad->marca,
                'capacidad' => $cambios->get($unidad->id)['capacidad'] ?? $unidad->capacidad,
                'tipo_agente' => $cambios->get($unidad->id)['tipo_agente'] ?? $unidad->tipo_agente,
                'anio_fabricacion' => $cambios->get($unidad->id)['anio_fabricacion'] ?? $unidad->anio_fabricacion,
                'fecha_ultima_ph' => $unidad->fecha_ultima_ph?->toDateString(),
                'fecha_ultima_recarga' => $unidad->fecha_ultima_recarga?->toDateString(),
                'presion_ph' => $unidad->presion_ph,
                'tiempo_ph' => $unidad->tiempo_ph,
                'presion_trabajo' => $unidad->presion_trabajo,
            ])->all();

        $action->corregir(
            $certificate,
            $unidades === [] ? null : $unidades,
            ['direccion' => $datos['direccion'] ?? null, 'referencia' => $datos['referencia'] ?? null],
            $datos['motivo'],
            $request->user()->id,
        );

        return back()->with('success', "Certificado {$certificate->numero} corregido.");
    }
}
