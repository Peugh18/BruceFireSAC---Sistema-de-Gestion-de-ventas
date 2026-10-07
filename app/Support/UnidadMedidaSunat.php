<?php

namespace App\Support;

/**
 * Catálogo 03 de SUNAT (unidades de medida) que usa BRUCE FIRE. Es la única
 * lista: los formularios de productos y servicios la muestran y Greenter
 * rechaza lo que no esté aquí en vez de cambiarlo sin avisar.
 */
final class UnidadMedidaSunat
{
    /**
     * @var array<string, string>
     */
    public const CATALOGO = [
        'NIU' => 'Unidad (bienes)',
        'ZZ' => 'Unidad (servicios)',
        'KGM' => 'Kilogramos',
        'GRM' => 'Gramos',
        'LTR' => 'Litros',
        'GLL' => 'Galones',
        'MTR' => 'Metros',
        'CMT' => 'Centímetros',
        'MTK' => 'Metros cuadrados',
        'SET' => 'Juego',
        'PR' => 'Par: guantes, botas',
        'BX' => 'Caja',
        'PK' => 'Paquete',
        'DZN' => 'Docena',
        'BG' => 'Bolsa',
        'BO' => 'Botella',
        'CA' => 'Lata',
        'RO' => 'Rollo',
        'HUR' => 'Hora',
        'DAY' => 'Día',
        'MON' => 'Mes',
    ];

    /**
     * @return list<string>
     */
    public static function codigos(): array
    {
        return array_keys(self::CATALOGO);
    }

    /**
     * Para los select: código y nombre.
     *
     * @return list<array{codigo: string, nombre: string}>
     */
    public static function opciones(): array
    {
        $opciones = [];
        foreach (self::CATALOGO as $codigo => $nombre) {
            $opciones[] = ['codigo' => $codigo, 'nombre' => $nombre];
        }

        return $opciones;
    }

    public static function esValida(?string $codigo): bool
    {
        return $codigo !== null && array_key_exists($codigo, self::CATALOGO);
    }
}
