<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateParticipant;
use App\Models\CertificateUnit;
use App\Models\CompanySetting;
use App\Services\Certificates\CertificatePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Página pública a la que lleva el QR del certificado: la usan los inspectores
 * de la municipalidad (ITSE) para comprobar que el documento es auténtico y
 * está vigente. Solo se llega con el token del QR, que no se puede adivinar.
 */
class PublicCertificateVerificationController extends Controller
{
    public function show(Request $request, string $token): Response|JsonResponse
    {
        $participant = $this->buscarParticipante($token);
        $certificate = $participant?->certificate ?? $this->buscar($token);

        if ($request->expectsJson()) {
            abort_if(! $certificate, 404);

            return response()->json([
                'numero' => $participant?->numero() ?? $certificate->numero,
                'tipo' => $certificate->certificateType?->nombre,
                'cliente' => $certificate->client?->razon_social,
                'fecha_emision' => $certificate->fecha_emision?->toDateString(),
                'fecha_vigencia_hasta' => $certificate->fecha_vigencia_hasta?->toDateString(),
                'estado' => $participant?->anulado_at ? 'anulado' : $this->estadoPublico($certificate),
                'unidades' => $certificate->certificateUnits->pluck('numero_serie_snapshot')->all(),
                'participante' => $participant ? ['nombres' => $participant->nombres, 'dni' => $this->dniVisible($participant->dni)] : null,
            ]);
        }

        return Inertia::render('publico/verificar-certificado', [
            'certificado' => $certificate ? $this->datosPublicos($certificate, $token, $participant) : null,
            'empresa' => $this->empresa(),
            'consultadoEl' => now()->format('d/m/Y H:i'),
        ])->toResponse($request)->setStatusCode($certificate ? 200 : 404);
    }

    /**
     * El PDF original, para que el inspector lo compare con el documento físico.
     */
    public function pdf(string $token, CertificatePdfService $pdfService): Response
    {
        $participant = $this->buscarParticipante($token);
        $certificate = $participant?->certificate ?? $this->buscar($token);

        abort_if(! $certificate, 404);

        return $pdfService->generate($certificate, $participant)->stream(($participant?->numero() ?? $certificate->numero).'.pdf');
    }

    protected function buscar(string $token): ?Certificate
    {
        return Certificate::query()
            ->with(['certificateType', 'client', 'certificateUnits.equipment'])
            ->where('qr_token', $token)
            ->first();
    }

    protected function buscarParticipante(string $token): ?CertificateParticipant
    {
        return CertificateParticipant::query()
            ->with(['certificate.certificateType', 'certificate.client', 'certificate.certificateUnits.equipment'])
            ->where('qr_token', $token)
            ->first();
    }

    /**
     * vigente | sin_vencimiento | vencido | reemplazado | anulado. La fecha manda
     * sobre el estado guardado: un certificado pasado de fecha se muestra vencido
     * aunque el comando nocturno todavía no lo haya marcado.
     */
    protected function estadoPublico(Certificate $certificate): string
    {
        if (in_array($certificate->estado, ['anulado', 'reemplazado'], true)) {
            return $certificate->estado;
        }

        if (! $certificate->fecha_vigencia_hasta) {
            return 'sin_vencimiento';
        }

        return $certificate->estado === 'vencido' || $certificate->fecha_vigencia_hasta->isBefore(today())
            ? 'vencido'
            : 'vigente';
    }

    /**
     * @return array<string, mixed>
     */
    protected function datosPublicos(Certificate $certificate, string $token, ?CertificateParticipant $participant = null): array
    {
        $client = $certificate->client;

        return [
            'numero' => $participant?->numero() ?? $certificate->numero,
            'tipo' => $certificate->certificateType?->nombre,
            'estado' => $participant?->anulado_at ? 'anulado' : $this->estadoPublico($certificate),
            'fecha_emision' => $certificate->fecha_emision?->format('d/m/Y'),
            'fecha_vencimiento' => $certificate->fecha_vigencia_hasta?->format('d/m/Y'),
            'revision' => $certificate->revision,
            'revision_fecha' => $certificate->revision > 0 ? $certificate->updated_at?->format('d/m/Y') : null,
            'referencia' => $certificate->referencia,
            'cliente' => [
                'razon_social' => $client?->razon_social,
                'documento_tipo' => $client?->tipo_documento ? strtoupper($client->tipo_documento) : null,
                'documento' => $this->documentoVisible($client?->tipo_documento, $client?->numero_documento),
                'direccion' => $client?->direccion_fiscal,
            ],
            'participante' => $participant ? [
                'nombres' => $participant->nombres,
                'dni' => $this->dniVisible($participant->dni),
                'cargo' => $participant->cargo,
                'personal_de' => $client?->razon_social,
            ] : null,
            'equipos' => $certificate->certificateUnits
                ->sortBy(fn (CertificateUnit $unit) => [$unit->orden ?? PHP_INT_MAX, $unit->id])
                ->values()
                ->map(fn (CertificateUnit $unit) => [
                    'serie' => $unit->numero_serie_snapshot,
                    'capacidad' => $unit->capacidad ?: $unit->equipment?->capacidad,
                    'marca' => $unit->marca ?: $unit->equipment?->marca,
                ])
                ->all(),
            'pdf_url' => route('certificados.verificar.pdf', ['token' => $token]),
        ];
    }

    /**
     * El RUC es público; el DNI de una persona se muestra parcial.
     */
    protected function documentoVisible(?string $tipo, ?string $numero): ?string
    {
        if (! $numero || strtoupper((string) $tipo) === 'RUC' || strlen($numero) < 6) {
            return $numero;
        }

        return substr($numero, 0, 2).str_repeat('•', strlen($numero) - 4).substr($numero, -2);
    }

    protected function dniVisible(?string $dni): ?string
    {
        return $dni ? $this->documentoVisible('DNI', $dni) : null;
    }

    /**
     * @return array{razon_social: string|null, ruc: string|null, telefono: string|null, email: string|null}
     */
    protected function empresa(): array
    {
        $company = CompanySetting::current();

        return [
            'razon_social' => $company->razon_social,
            'ruc' => $company->ruc,
            'telefono' => $company->telefono,
            'email' => $company->email,
        ];
    }
}
