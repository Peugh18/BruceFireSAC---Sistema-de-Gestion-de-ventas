<?php

namespace App\Http\Controllers\Gerente;

use App\Actions\Certificates\GuardarFirmante;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gerente\SaveSignerRequest;
use App\Models\CertificateType;
use App\Models\Signer;
use App\Models\Team;
use App\Services\Certificates\CertificatePdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SignerController extends Controller
{
    /**
     * Firmantes de los certificados con su firma y sello, y en qué tipos de
     * certificado aparece cada uno.
     */
    public function index(Team $current_team): Response
    {
        $tipos = CertificateType::query()->with('signers')->orderBy('nombre')->get();

        return Inertia::render('gerente/configuracion/firmas', [
            'firmantes' => Signer::query()
                ->with('certificateTypes:id,codigo')
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->get()
                ->map(fn (Signer $signer) => [
                    'id' => $signer->id,
                    'nombre' => $signer->nombre,
                    'cargo' => $signer->cargo,
                    'cip' => $signer->cip,
                    'activo' => $signer->activo,
                    'firma_url' => $signer->firma_path ? Storage::disk('public')->url($signer->firma_path) : null,
                    'sello_url' => $signer->sello_path ? Storage::disk('public')->url($signer->sello_path) : null,
                    'tipos' => $signer->certificateTypes->pluck('codigo')->values(),
                ]),
            'tipos' => $tipos->map(fn (CertificateType $tipo) => [
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'firmantes' => $tipo->signers->pluck('nombre')->values(),
                'sin_firma' => $tipo->signers->isEmpty() || $tipo->signers->contains(fn (Signer $signer) => $signer->firma_path === null),
            ]),
        ]);
    }

    public function store(Team $current_team, SaveSignerRequest $request, GuardarFirmante $guardar): RedirectResponse
    {
        $signer = $guardar->handle(null, $request->safe()->except(['firma', 'sello']), $request->file('firma'), $request->file('sello'), $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Firmante {$signer->nombre} agregado."]);

        return back();
    }

    public function update(Team $current_team, Signer $firmante, SaveSignerRequest $request, GuardarFirmante $guardar): RedirectResponse
    {
        $guardar->handle($firmante, $request->safe()->except(['firma', 'sello']), $request->file('firma'), $request->file('sello'), $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cambios de {$firmante->nombre} guardados."]);

        return back();
    }

    /**
     * Certificado de ejemplo del tipo, para ver cómo quedan las firmas.
     */
    public function vistaPrevia(Team $current_team, CertificateType $tipo, CertificatePdfService $pdf): HttpResponse
    {
        return $pdf->muestra($tipo)->stream("ejemplo-{$tipo->codigo}.pdf");
    }
}
