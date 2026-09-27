<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Sale;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCertificateController extends Controller
{
    /**
     * Formulario del certificado de un servicio, armado con las columnas y
     * el checklist que tenga configurado el tipo.
     */
    public function create(Team $current_team, Sale $sale, CertificateType $tipo, Request $request): Response
    {
        $this->assertSedeAccess($request, $sale);
        abort_if(in_array($tipo->codigo, EmitirCertificadoDeServicio::TIPOS_DE_EXTINTORES, true) || empty($tipo->columnas), 404);

        $sale->load('client');
        $existente = Certificate::query()
            ->where('sale_id', $sale->id)
            ->where('certificate_type_id', $tipo->id)
            ->where('estado', 'vigente')
            ->latest('id')
            ->first();

        return Inertia::render('vendedor/ventas/certificado-servicio', [
            'sale' => [
                'id' => $sale->id,
                'numero_interno' => $sale->numero_interno,
                'estado' => $sale->estado,
                'destino' => $sale->destino,
                'referencia' => $sale->referencia,
                'client' => $sale->client->only(['id', 'razon_social', 'numero_documento', 'direccion_fiscal']),
            ],
            'tipo' => [
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'columnas' => array_values($tipo->columnas ?? []),
                'checklist' => array_values($tipo->checklist ?? []),
            ],
            'atenciones' => EmitirCertificadoDeServicio::ATENCIONES,
            'existente' => $existente ? [
                'numero' => $existente->numero,
                'revision' => $existente->revision,
                'referencia' => $existente->referencia,
                'direccion' => $existente->direccion,
                'tipo_atencion' => $existente->tipo_atencion,
                'filas' => $existente->datos['filas'] ?? [],
                'pruebas' => $existente->datos['pruebas'] ?? [],
                'observaciones' => $existente->datos['observaciones'] ?? null,
            ] : null,
        ]);
    }

    public function store(Team $current_team, Sale $sale, CertificateType $tipo, Request $request, EmitirCertificadoDeServicio $emitir): RedirectResponse
    {
        $this->assertSedeAccess($request, $sale);

        $datos = $request->validate([
            'referencia' => ['nullable', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'tipo_atencion' => ['nullable', Rule::in(EmitirCertificadoDeServicio::ATENCIONES)],
            'filas' => ['required', 'array', 'min:1', 'max:200'],
            'filas.*' => ['array'],
            'pruebas' => ['nullable', 'array'],
            'pruebas.*.estado' => ['nullable', Rule::in(EmitirCertificadoDeServicio::ESTADOS_PRUEBA)],
            'pruebas.*.valor' => ['nullable', 'string', 'max:30'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $certificado = $emitir->handle($sale, $tipo, $datos, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Certificado {$certificado->numero} listo."]);

        return redirect()->route('vendedor.ventas.show', ['current_team' => $current_team, 'sale' => $sale]);
    }

    protected function assertSedeAccess(Request $request, Sale $sale): void
    {
        $sedeId = $request->user()->sedeRestringidaId();

        abort_if($sedeId !== null && (int) $sale->sede_id !== $sedeId, 404);
    }
}
