<?php

namespace App\Services\Certificates;

use App\Models\CompanySetting;
use Carbon\CarbonInterface;

class CertificateDateCalculator
{
    public static function proximaPruebaHidrostatica(CarbonInterface $fechaUltimaPH): CarbonInterface
    {
        return $fechaUltimaPH->copy()->addYears(self::intervaloPh());
    }

    public static function intervaloPh(): int
    {
        return (int) (CompanySetting::current()->intervalo_ph_anios ?? 5);
    }

    public static function proximaOperatividad(CarbonInterface $fechaRecargaOInstalacion): CarbonInterface
    {
        return $fechaRecargaOInstalacion->copy()->addYear();
    }
}
