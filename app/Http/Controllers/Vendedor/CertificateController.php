<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\AcotaPorSede;
use App\Models\Certificate;
use App\Models\CertificateParticipant;
use App\Models\CertificateRevision;
use App\Models\CertificateType;
use App\Models\CertificateUnit;
use App\Models\Team;
use App\Services\Certificates\CertificatePdfService;
use App\Services\Certificates\CertificateWordService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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
            ->with(['certificateType', 'client', 'certificateUnits', 'sale.electronicDocuments'])
            ->withCount(['participants' => fn ($query) => $query->whereNull('anulado_at')])
            ->when($tipo !== '', fn ($query) => $query->whereHas('certificateType', fn ($q) => $q->where('codigo', $tipo)))
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero', 'like', "%{$search}%")
                        ->orWhere('referencia', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($cq) => $cq->where('razon_social', 'like', "%{$search}%")->orWhere('numero_documento', 'like', "%{$search}%"))
                        ->orWhereHas('sale', fn ($sq) => $sq->where('numero_interno', 'like', "%{$search}%")->orWhere('numero_nota_venta', 'like', "%{$search}%"))
                        ->orWhereHas('certificateUnits', fn ($uq) => $uq->where('numero_serie_snapshot', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Certificate $c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'tipo' => $c->certificateType?->nombre,
                'tipo_codigo' => $c->certificateType?->codigo,
                'cliente' => $c->client?->razon_social,
                'client_id' => $c->client_id,
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

        $certificate->load(['certificateType', 'client', 'certificateUnits', 'participants', 'revisions.user', 'sale.electronicDocuments']);
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

        return $sale ? [
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

        $codigo = $certificate->certificateType->codigo;

        if (in_array($codigo, EmitirCertificadoDeServicio::TIPOS_DE_EXTINTORES, true)) {
            return route('vendedor.ventas.certificados.create', ['current_team' => $team, 'sale' => $certificate->sale_id]);
        }

        return route('vendedor.ventas.certificado-servicio.create', ['current_team' => $team, 'sale' => $certificate->sale_id, 'tipo' => $codigo]);
    }
}
