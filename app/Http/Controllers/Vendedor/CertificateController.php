<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Team;
use App\Services\Certificates\CertificatePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    /**
     * Muestra la lista paginada de certificados para el vendedor.
     */
    public function index(Team $current_team, Request $request): Response
    {
        $search = $request->string('search')->toString();

        $certificates = Certificate::query()
            ->with(['certificateType', 'client', 'certificateUnits'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($cq) => $cq->where('razon_social', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Certificate $c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'tipo' => $c->certificateType?->nombre,
                'tipo_codigo' => $c->certificateType?->codigo,
                'cliente' => $c->client?->razon_social,
                'unidades' => $c->certificateUnits->pluck('numero_serie_snapshot')->all(),
                'fecha_emision' => $c->fecha_emision?->toDateString(),
                'fecha_vigencia_hasta' => $c->fecha_vigencia_hasta?->toDateString(),
                'estado' => $c->estado,
                'qr_token' => $c->qr_token,
            ]);

        return Inertia::render('vendedor/certificados/index', [
            'certificates' => $certificates,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Muestra el detalle de un certificado con sus relaciones cargadas.
     *
     * Nota: {current_team} precede a {certificate} en la ruta y el dispatcher
     * de Laravel pasa los parámetros de ruta por POSICIÓN a menos que cada uno
     * tenga un parámetro correspondiente en el método.
     */
    public function show(Team $current_team, Certificate $certificate): Response
    {
        $certificate->load(['certificateType', 'client', 'certificateUnits']);

        return Inertia::render('vendedor/certificados/show', [
            'certificate' => [
                'id' => $certificate->id,
                'numero' => $certificate->numero,
                'tipo' => $certificate->certificateType?->nombre,
                'tipo_codigo' => $certificate->certificateType?->codigo,
                'cliente' => $certificate->client?->razon_social,
                'fecha_emision' => $certificate->fecha_emision?->toDateString(),
                'fecha_vigencia_hasta' => $certificate->fecha_vigencia_hasta?->toDateString(),
                'estado' => $certificate->estado,
                'qr_token' => $certificate->qr_token,
                'certificate_type' => $certificate->certificateType,
                'client' => $certificate->client,
                'certificate_units' => $certificate->certificateUnits,
                'unidades' => $certificate->certificateUnits->pluck('numero_serie_snapshot')->all(),
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
        $pdf = $certificatePdfService->generate($certificate);
        $filename = "certificado-{$certificate->numero}.pdf";
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $filename),
        ]);
    }
}
