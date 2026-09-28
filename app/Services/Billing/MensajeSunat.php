<?php

namespace App\Services\Billing;

use Illuminate\Support\Str;

/**
 * Traduce la respuesta técnica de SUNAT a una frase que la vendedora entiende
 * y le dice qué hacer. El mensaje original se conserva aparte para soporte.
 */
class MensajeSunat
{
    /**
     * Patrones de la respuesta de SUNAT (código o texto) y su explicación.
     *
     * @var array<string, string>
     */
    private const EXPLICACIONES = [
        'schemeid' => 'A un cliente con DNI no se le emite factura: emite una boleta.',
        'tipo de documento de identidad del receptor' => 'A un cliente con DNI no se le emite factura: emite una boleta.',
        'numero de ruc del receptor' => 'El RUC del cliente no es válido en SUNAT: revísalo en su ficha.',
        'no esta activo' => 'El RUC del cliente no está activo en SUNAT.',
        'no habido' => 'El RUC del cliente figura como no habido en SUNAT.',
        'certificado' => 'Hay un problema con el certificado digital de la empresa: avisa al administrador.',
        'firma' => 'Hay un problema con la firma del comprobante: avisa al administrador.',
        'ya fue informado' => 'SUNAT ya tenía este comprobante registrado.',
        'fecha de emision' => 'La fecha de emisión está fuera del plazo que acepta SUNAT.',
    ];

    public static function simple(?string $mensaje, ?string $codigo = null): ?string
    {
        $texto = Str::of((string) $mensaje)->lower()->ascii()->toString();

        foreach (self::EXPLICACIONES as $patron => $explicacion) {
            if ($texto !== '' && str_contains($texto, $patron)) {
                return $explicacion;
            }
        }

        if ($texto === '' && $codigo === '500') {
            return 'No se pudo conectar con SUNAT: el sistema lo vuelve a intentar solo.';
        }

        // Sin traducción conocida: la primera parte, sin el detalle técnico.
        return $mensaje ? trim(Str::before($mensaje, ' - Detalle')) : null;
    }
}
