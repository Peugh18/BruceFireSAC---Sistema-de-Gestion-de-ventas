<?php

namespace App\Services\Certificates;

use Carbon\CarbonInterface;

class CertificateDateCalculator
{
    public static function proximaPruebaHidrostatica(CarbonInterface $fechaUltimaPH): CarbonInterface
    {
        return $fechaUltimaPH->copy()->addYears(5); // NTP 350.043
    }

    public static function proximaOperatividad(CarbonInterface $fechaRecargaOInstalacion): CarbonInterface
    {
        return $fechaRecargaOInstalacion->copy()->addYear();
    }
}
