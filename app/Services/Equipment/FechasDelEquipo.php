<?php

namespace App\Services\Equipment;

use App\Models\Equipment;
use App\Services\Certificates\CertificateDateCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FechasDelEquipo
{
    /** @return array<string, list<string>> */
    public static function reglas(): array
    {
        return [
            'anio_fabricacion' => ['nullable', 'integer', 'min:1980', 'max:'.today()->year],
            'fecha_ultima_ph' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'proxima_prueba_hidrostatica' => ['nullable', 'date_format:Y-m-d'],
            'proxima_fecha_atencion' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function paraGuardar(array $datos, ?Equipment $equipo = null): array
    {
        Validator::make($datos, self::reglas())->validate();
        $anio = $datos['anio_fabricacion'] ?? $equipo?->anio_fabricacion;
        $fecha = array_key_exists('fecha_ultima_ph', $datos) ? $datos['fecha_ultima_ph'] : $equipo?->fecha_ultima_ph?->toDateString();
        if (filled($fecha) && is_numeric($anio) && Carbon::parse($fecha)->year < (int) $anio) {
            throw ValidationException::withMessages(['fecha_ultima_ph' => 'La prueba no puede ser anterior al año de fabricación del cilindro.']);
        }
        $fechas = [];
        if (array_key_exists('proxima_fecha_atencion', $datos)) {
            $fechas['proxima_fecha_atencion'] = $datos['proxima_fecha_atencion'] ?: null;
        }
        if (array_key_exists('fecha_ultima_ph', $datos) || array_key_exists('proxima_prueba_hidrostatica', $datos)) {
            $proxima = $datos['proxima_prueba_hidrostatica'] ?? null;
            if (filled($proxima) && filled($fecha) && Carbon::parse($proxima)->lessThanOrEqualTo(Carbon::parse($fecha))) {
                throw ValidationException::withMessages(['proxima_prueba_hidrostatica' => 'La próxima prueba debe ser posterior a la última realizada.']);
            }
            $fechas['fecha_ultima_ph'] = filled($fecha) ? $fecha : null;
            $fechas['proxima_prueba_hidrostatica'] = filled($proxima) ? $proxima : (filled($fecha) ? CertificateDateCalculator::proximaPruebaHidrostatica(Carbon::parse($fecha))->toDateString() : null);
        }

        return $fechas;
    }
}
