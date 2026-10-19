<?php

namespace App\Support;

use App\Models\SunatCodigoProducto;
use App\Models\SunatDetraccion;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Catálogos de SUNAT que usa BRUCE FIRE. El 54 (detracción) vive en la base
 * (`sunat_detracciones`, con porcentajes por vigencia); aquí se consulta. El
 * 59 (medio de pago) y el 25 (código de producto) son listas cortas fijas.
 */
final class CatalogosSunat
{
    /**
     * Catálogo 59: medios de pago de la detracción.
     *
     * @var array<string, string>
     */
    public const MEDIO_DE_PAGO = [
        '001' => 'Depósito en cuenta',
        '003' => 'Transferencia de fondos',
    ];

    /**
     * Catálogo 25 (UNSPSC): códigos de producto que usa la empresa (PHP
     * guarda estas claves numéricas como enteros).
     *
     * @var array<int, string>
     */
    public const PRODUCTO = [
        '46191601' => 'Extintores',
        '72101516' => 'Servicio de inspección, mantenimiento o reparación de extintores',
    ];

    /**
     * Regla del código de producto (catálogo 25): 8 dígitos y, cuando el
     * catálogo oficial ya está cargado, que exista en él.
     *
     * @return list<mixed>
     */
    public static function reglaCodigoProducto(): array
    {
        return ['nullable', 'digits:8', ...(SunatCodigoProducto::cargado() ? [Rule::exists('sunat_codigos_producto', 'codigo')] : [])];
    }

    /**
     * Códigos que el sistema sabe calcular hoy (con porcentaje vigente).
     *
     * @return list<string>
     */
    public static function codigosDeDetraccion(): array
    {
        $codigos = [];
        foreach (self::detracciones() as $detraccion) {
            if ($detraccion['habilitado']) {
                $codigos[] = $detraccion['codigo'];
            }
        }

        return $codigos;
    }

    /**
     * El catálogo 54 completo para los formularios: los que no se pueden
     * calcular quedan como "solo consulta".
     *
     * @return list<array{codigo: string, nombre: string, porcentaje: float|null, habilitado: bool}>
     */
    public static function detracciones(): array
    {
        $hoy = Carbon::today();

        $lista = [];
        foreach (SunatDetraccion::query()->orderBy('codigo')->get() as $d) {
            $regla = $d->reglaPara($hoy);
            $lista[] = [
                'codigo' => (string) $d->codigo,
                'nombre' => $d->nombre,
                'porcentaje' => $regla ? (float) $regla->porcentaje : null,
                'habilitado' => $regla !== null,
            ];
        }

        return $lista;
    }

    /** Porcentaje vigente del código en la fecha (null si no se calcula). */
    public static function porcentajeDeDetraccion(?string $codigo, ?Carbon $fecha = null): ?float
    {
        if ($codigo === null) {
            return null;
        }

        $regla = SunatDetraccion::query()->find($codigo)?->reglaPara($fecha ?? Carbon::today());

        return $regla ? (float) $regla->porcentaje : null;
    }

    /** "022 - Otros servicios empresariales", como en la factura. */
    public static function detraccionConNombre(?string $codigo): string
    {
        if ($codigo === null || $codigo === '') {
            return '';
        }

        $nombre = SunatDetraccion::query()->whereKey($codigo)->value('nombre');

        return $nombre ? $codigo.' - '.$nombre : $codigo;
    }

    public static function medioDePagoConNombre(string $codigo): string
    {
        return isset(self::MEDIO_DE_PAGO[$codigo]) ? $codigo.' - '.self::MEDIO_DE_PAGO[$codigo] : $codigo;
    }
}
