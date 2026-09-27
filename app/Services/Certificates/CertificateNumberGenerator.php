<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateSequence;
use App\Models\CertificateType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Numera los certificados por tipo con un correlativo bloqueado en base de
 * datos: un número nunca se repite ni se reutiliza (aunque se borre o anule un
 * certificado) y cada serie puede continuar desde el número que ya usa la
 * empresa en papel (por ejemplo, luces LM-0006540).
 */
class CertificateNumberGenerator
{
    /**
     * Reserva y devuelve el siguiente número del tipo. Debe llamarse dentro de
     * la transacción que crea el certificado para que el bloqueo lo proteja.
     */
    public function siguiente(CertificateType $tipo): string
    {
        return DB::transaction(function () use ($tipo) {
            $anio = $this->anio($tipo);
            $secuencia = $this->secuenciaBloqueada($tipo, $anio);

            do {
                $secuencia->ultimo_numero++;
                $numero = $this->formatear($tipo, $secuencia->ultimo_numero, $anio);
            } while (Certificate::where('numero', $numero)->exists());

            $secuencia->save();

            return $numero;
        });
    }

    /**
     * Número que saldrá en el próximo certificado, sin reservarlo (vista previa).
     */
    public function proximo(CertificateType $tipo): string
    {
        $anio = $this->anio($tipo);
        $ultimo = CertificateSequence::query()
            ->where('certificate_type_id', $tipo->id)
            ->where('anio', $anio)
            ->value('ultimo_numero') ?? $this->ultimoUsado($tipo, $anio);

        return $this->formatear($tipo, $ultimo + 1, $anio);
    }

    /**
     * Hace que la serie continúe desde el número indicado (por ejemplo, desde el
     * último certificado hecho en papel). No permite retroceder a números ya usados.
     */
    public function continuarDesde(CertificateType $tipo, int $siguiente): void
    {
        DB::transaction(function () use ($tipo, $siguiente) {
            $anio = $this->anio($tipo);
            $secuencia = $this->secuenciaBloqueada($tipo, $anio);

            if ($siguiente <= $this->ultimoUsado($tipo, $anio)) {
                throw ValidationException::withMessages([
                    'siguiente_numero' => 'Ese número ya se usó en un certificado emitido. Elige uno mayor.',
                ]);
            }

            $secuencia->update(['ultimo_numero' => $siguiente - 1]);
        });
    }

    public function formatear(CertificateType $tipo, int $numero, int $anio): string
    {
        $correlativo = str_pad((string) $numero, max(1, $tipo->digitos ?: 4), '0', STR_PAD_LEFT);

        return $anio > 0
            ? sprintf('%s-%d-%s', $this->prefijo($tipo), $anio, $correlativo)
            : sprintf('%s-%s', $this->prefijo($tipo), $correlativo);
    }

    /**
     * Prefijo configurado o, para tipos sin configurar, el que usaba el sistema:
     * iniciales del código (operatividad_garantia → OG) y BF-UME para capacitación.
     */
    public function prefijo(CertificateType $tipo): string
    {
        if ($tipo->prefijo) {
            return $tipo->prefijo;
        }

        if ($tipo->codigo === 'capacitacion') {
            return 'BF-UME';
        }

        $partes = explode('_', $tipo->codigo);

        return count($partes) >= 2
            ? strtoupper(substr($partes[0], 0, 1).substr($partes[1], 0, 1))
            : strtoupper(substr($tipo->codigo, 0, 2));
    }

    /**
     * Año de la serie, o 0 si la serie es continua (no se reinicia cada año).
     */
    protected function anio(CertificateType $tipo): int
    {
        return ($tipo->incluye_anio ?? true) ? (int) now()->year : 0;
    }

    protected function secuenciaBloqueada(CertificateType $tipo, int $anio): CertificateSequence
    {
        $secuencia = CertificateSequence::query()
            ->where('certificate_type_id', $tipo->id)
            ->where('anio', $anio)
            ->lockForUpdate()
            ->first();

        return $secuencia ?? CertificateSequence::create([
            'certificate_type_id' => $tipo->id,
            'anio' => $anio,
            'ultimo_numero' => $this->ultimoUsado($tipo, $anio),
        ]);
    }

    /**
     * Mayor correlativo ya usado en certificados de este tipo con el mismo prefijo
     * (y año). Sirve para arrancar la secuencia sin chocar con números antiguos.
     */
    protected function ultimoUsado(CertificateType $tipo, int $anio): int
    {
        $inicio = $anio > 0 ? $this->prefijo($tipo).'-'.$anio.'-' : $this->prefijo($tipo).'-';

        return (int) Certificate::query()
            ->where('certificate_type_id', $tipo->id)
            ->where('numero', 'like', $inicio.'%')
            ->pluck('numero')
            ->map(fn (string $numero) => (int) substr($numero, strlen($inicio)))
            ->max();
    }
}
