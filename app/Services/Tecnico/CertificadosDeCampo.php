<?php

namespace App\Services\Tecnico;

use App\Actions\Certificates\EmitirCertificadoDeServicio;
use App\Actions\Certificates\IssueCertificate;
use App\Models\CertificateType;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CertificadosDeCampo
{
    /** @return Collection<int, CertificateType> */
    public function tipos(ServiceOrder $orden): Collection
    {
        $servicios = $orden->lines()->pluck('service_id');
        if ($orden->service_id !== null) {
            $servicios->push($orden->service_id);
        }

        return CertificateType::query()
            ->whereHas('services', fn ($query) => $query->whereIn('services.id', $servicios))
            ->whereNotIn('codigo', EmitirCertificadoDeServicio::TIPOS_DE_EXTINTORES)
            ->where('activo', true)->whereNotNull('columnas')->orderBy('id')->get()
            ->filter(fn (CertificateType $tipo): bool => ! empty($tipo->columnas))->values();
    }

    /** @return list<array<string, mixed>> */
    public function formularios(ServiceOrder $orden): array
    {
        $emitidos = $orden->certificates()->with('typeVersion')->where('estado', 'vigente')->get()->keyBy('certificate_type_id');

        return array_values($this->tipos($orden)->map(function (CertificateType $tipo) use ($emitidos): array {
            $emitido = $emitidos->get($tipo->id);
            $plantilla = $emitido?->typeVersion?->configuracion;

            return [
                ...$tipo->only(['id', 'codigo', 'nombre', 'version', 'columnas', 'checklist']),
                'columnas' => $plantilla['columnas'] ?? $tipo->columnas,
                'checklist' => $plantilla['checklist'] ?? $tipo->checklist,
                'mediciones' => $emitido?->datos,
            ];
        })->all());
    }

    /** @return array<string, list<string>> */
    public static function reglas(): array
    {
        return [
            'certificados_servicio' => ['sometimes', 'array', 'max:20'],
            'certificados_servicio.*' => ['required', 'array:tipo_id,version,filas,pruebas'],
            'certificados_servicio.*.tipo_id' => ['required', 'integer', 'distinct'],
            'certificados_servicio.*.version' => ['required', 'integer', 'min:1'],
            'certificados_servicio.*.filas' => ['required', 'array', 'min:1', 'max:200'],
            'certificados_servicio.*.filas.*' => ['required', 'array'],
            'certificados_servicio.*.filas.*.*' => ['nullable', 'string', 'max:1000'],
            'certificados_servicio.*.pruebas' => ['sometimes', 'array', 'max:100'],
            'certificados_servicio.*.pruebas.*' => ['required', 'array:estado,valor'],
            'certificados_servicio.*.pruebas.*.estado' => ['required', 'in:C,NC,NA'],
            'certificados_servicio.*.pruebas.*.valor' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Valida todos los formularios antes de guardar o cerrar el trabajo.
     *
     * @param  list<array<string, mixed>>  $datos
     * @return list<array{tipo_id: int, version: int, filas: list<array<string, mixed>>, pruebas: list<array{estado: string, valor: string|null}>}>
     */
    public function validar(ServiceOrder $orden, array $datos): array
    {
        $tipos = $this->tipos($orden)->keyBy('id');
        if (count($datos) !== $tipos->count() || count(array_unique(array_column($datos, 'tipo_id'))) !== count($datos)) {
            throw ValidationException::withMessages(['certificados_servicio' => 'Completa las mediciones de cada certificado de los servicios de esta orden.']);
        }

        $limpios = [];
        $validador = app(EmitirCertificadoDeServicio::class);
        foreach ($datos as $indice => $dato) {
            $tipo = $tipos->get((int) $dato['tipo_id']);
            if ($tipo === null) {
                throw ValidationException::withMessages(['certificados_servicio' => 'El certificado no corresponde a los servicios de esta orden.']);
            }
            if ((int) ($dato['version'] ?? 0) !== $tipo->version) {
                throw ValidationException::withMessages(["certificados_servicio.{$indice}.version" => 'La plantilla cambió. Recarga la página y revisa las mediciones antes de finalizar.']);
            }

            try {
                $filas = $validador->filas($tipo, $dato['filas'] ?? []);
                $pruebas = $validador->pruebas($tipo, $dato['pruebas'] ?? []);
                foreach ($tipo->checklist ?? [] as $posicion => $prueba) {
                    $entrada = $dato['pruebas'][$posicion] ?? null;
                    if ($entrada === null || ! in_array($entrada['estado'] ?? null, EmitirCertificadoDeServicio::ESTADOS_PRUEBA, true)) {
                        throw ValidationException::withMessages(["pruebas.{$posicion}.estado" => 'Registra el resultado de esta prueba.']);
                    }
                    if (! empty($prueba['unidad']) && ($entrada['estado'] ?? null) !== 'NA' && ! is_numeric(str_replace(',', '.', (string) ($entrada['valor'] ?? '')))) {
                        throw ValidationException::withMessages(["pruebas.{$posicion}.valor" => 'Registra la medición numérica de esta prueba.']);
                    }
                }
            } catch (ValidationException $exception) {
                $errores = [];
                foreach ($exception->errors() as $clave => $mensajes) {
                    $errores["certificados_servicio.{$indice}.{$clave}"] = $mensajes;
                }
                throw ValidationException::withMessages($errores);
            }
            $limpios[] = ['tipo_id' => $tipo->id, 'version' => $tipo->version, 'filas' => $filas, 'pruebas' => $pruebas];
        }

        return $limpios;
    }

    /** @param list<array<string, mixed>> $datos */
    public function emitir(ServiceOrder $orden, array $datos, string $atencion, ?string $observaciones = null): void
    {
        foreach ($this->validar($orden, $datos) as $dato) {
            $tipo = CertificateType::query()->lockForUpdate()->findOrFail($dato['tipo_id']);
            if ($tipo->version !== $dato['version']) {
                throw ValidationException::withMessages(['certificados_servicio' => 'La plantilla cambió. Recarga la página antes de finalizar.']);
            }
            app(IssueCertificate::class)->handle($tipo, $orden->client, [], $orden->sale_id, $orden->id, [
                'tipo_atencion' => $atencion,
                'referencia' => $orden->referencia,
                'datos' => ['filas' => $dato['filas'], 'pruebas' => $dato['pruebas'], 'observaciones' => $observaciones],
            ]);
        }
    }
}
