<?php

namespace App\Services\Quotes;

use App\Models\CompanyBankAccount;
use App\Models\CompanySetting;
use App\Models\Quote;
use App\Support\HojaPdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Support\Facades\URL;

/**
 * PDF de la cotización para el cliente, con la marca de la empresa, y el
 * enlace público firmado para mandarlo por WhatsApp sin iniciar sesión.
 */
class QuotePdfService
{
    public function generate(Quote $quote): DompdfWrapper
    {
        $quote->loadMissing('client', 'vendedor', 'items.product', 'items.service');

        $company = CompanySetting::current();

        return HojaPdf::cargar('pdf.cotizacion', [
            'quote' => $quote,
            ...$company->identidadPdf(),
            'bankAccounts' => CompanyBankAccount::query()->where('activo', true)->orderBy('orden')->get(),
        ]);
    }

    /**
     * Enlace que el cliente abre desde WhatsApp: firmado y válido hasta 30
     * días después de la vigencia de la cotización.
     */
    public function enlacePublico(Quote $quote): string
    {
        return URL::temporarySignedRoute(
            'cotizaciones.publico',
            $quote->vigencia_hasta->copy()->endOfDay()->addDays(30),
            ['quote' => $quote->id],
        );
    }

    public function nombreArchivo(Quote $quote): string
    {
        return "cotizacion-{$quote->numero}.pdf";
    }
}
