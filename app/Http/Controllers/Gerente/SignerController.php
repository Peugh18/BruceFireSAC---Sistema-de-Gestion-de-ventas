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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                    'firma_url' => $signer->firma_path ? route('gerente.configuracion.firmas.imagen', ['current_team' => $current_team, 'firmante' => $signer, 'tipo' => 'firma', 'v' => md5($signer->firma_path)]) : null,
                    'sello_url' => $signer->sello_path ? route('gerente.configuracion.firmas.imagen', ['current_team' => $current_team, 'firmante' => $signer, 'tipo' => 'sello', 'v' => md5($signer->sello_path)]) : null,
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
     * Firma o sello guardados en el disco privado: solo el Gerente los ve
     * (X2). Los certificados los leen directo del disco.
     */
    public function imagen(Team $current_team, Signer $firmante, string $tipo): StreamedResponse
    {
        $ruta = $tipo === 'sello' ? $firmante->sello_path : $firmante->firma_path;

        abort_if($ruta === null || ! Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->response($ruta, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /**
     * Certificado de ejemplo del tipo, para ver cómo quedan las firmas.
     */
    public function vistaPrevia(Team $current_team, CertificateType $tipo, CertificatePdfService $pdf): HttpResponse
    {
        return $pdf->muestra($tipo)->stream("ejemplo-{$tipo->codigo}.pdf");
    }
}
