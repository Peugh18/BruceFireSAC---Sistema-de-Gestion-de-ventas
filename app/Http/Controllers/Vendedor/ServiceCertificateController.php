<?php

namespace App\Http\Controllers\Vendedor;

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendedor\Concerns\ValidaVentaVisible;
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
    use ValidaVentaVisible;

    /**
     * Formulario del certificado de un servicio, armado con las columnas y
     * el checklist que tenga configurado el tipo.
     */
    public function create(Team $current_team, Sale $sale, CertificateType $tipo, Request $request): Response
    {
        $this->assertSedeAccess($request, $sale);
        $existente = EmitirCertificadoDeServicio::existente($sale, $tipo);
        $plantilla = EmitirCertificadoDeServicio::plantillaParaVenta($sale, $tipo);
        abort_unless(EmitirCertificadoDeServicio::esFormulario($plantilla) && ($tipo->activo || $existente !== null), 404);
        abort_unless(EmitirCertificadoDeServicio::correspondeAVenta($sale, $tipo), 404);

        $sale->load('client');

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
                'columnas' => EmitirCertificadoDeServicio::usaBloque($plantilla, 'tabla') ? array_values($plantilla->columnas ?? []) : [],
                'checklist' => EmitirCertificadoDeServicio::usaBloque($plantilla, 'pruebas') ? array_values($plantilla->checklist ?? []) : [],
                'composicion' => $plantilla->composicion,
                'requiere_tabla' => EmitirCertificadoDeServicio::usaBloque($plantilla, 'tabla'),
                'requiere_pruebas' => EmitirCertificadoDeServicio::usaBloque($plantilla, 'pruebas'),
                'requiere_diploma' => EmitirCertificadoDeServicio::usaBloque($plantilla, 'diploma'),
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
                'capacitacion' => EmitirCertificadoDeServicio::usaBloque($plantilla, 'diploma') ? [
                    'curso' => $existente->datos['curso'] ?? '',
                    'horas' => $existente->datos['horas'] ?? '',
                    'instructor' => $existente->datos['instructor'] ?? '',
                    'fecha' => $existente->datos['fecha'] ?? $existente->fecha_emision->toDateString(),
                ] : null,
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
            'filas' => ['sometimes', 'array', 'max:200'],
            'filas.*' => ['array'],
            'filas.*.*' => ['nullable', 'string', 'max:1000'],
            'pruebas' => ['nullable', 'array', 'max:100'],
            'pruebas.*.estado' => ['nullable', Rule::in(EmitirCertificadoDeServicio::ESTADOS_PRUEBA)],
            'pruebas.*.valor' => ['nullable', 'string', 'max:30'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'capacitacion' => ['sometimes', 'array:curso,horas,instructor,fecha'],
            'capacitacion.curso' => ['nullable', 'string', 'max:255'],
            'capacitacion.horas' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'capacitacion.instructor' => ['nullable', 'string', 'max:255'],
            'capacitacion.fecha' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $certificado = $emitir->handle($sale, $tipo, $datos, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Certificado {$certificado->numero} listo."]);

        return redirect()->route('vendedor.ventas.show', ['current_team' => $current_team, 'sale' => $sale]);
    }
}
