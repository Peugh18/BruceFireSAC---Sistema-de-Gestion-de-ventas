<?php

namespace App\Services\Inventory;

use App\Models\CompanySetting;
use App\Models\InventoryUnit;
use App\Models\Reception;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorPNG;

class StickerPdfService
{
    /**
     * Genera el documento PDF con stickers en grilla 2x2 (4 por hoja A4)
     * para todas las unidades serializadas recibidas en la recepción (§84.9).
     */
    public function generate(Reception $reception): DompdfWrapper
    {
        $reception->loadMissing([
            'movements.inventoryUnit.product',
            'sedeAlmacen',
        ]);

        /** @var Collection<int, InventoryUnit> $units */
        $units = $reception->movements
            ->pluck('inventoryUnit')
            ->filter()
            ->unique('id')
            ->values();

        $barcodeGenerator = new BarcodeGeneratorPNG;
        $stickers = $units->map(function (InventoryUnit $unit) use ($barcodeGenerator) {
            $barcodeBytes = $barcodeGenerator->getBarcode(
                $unit->numero_serie,
                $barcodeGenerator::TYPE_CODE_128,
                2,
                48
            );

            return [
                'id' => $unit->id,
                'numero_serie' => $unit->numero_serie,
                'producto' => $unit->product->nombre ?? 'Equipo contra incendio',
                'codigo_producto' => $unit->product->codigo ?? '',
                'marca' => $unit->marca,
                'anio_fabricacion' => $unit->anio_fabricacion,
                'barcode_base64' => base64_encode($barcodeBytes),
            ];
        });

        $company = CompanySetting::current();
        $logoBase64 = $this->logoBase64($company);

        return Pdf::loadView('pdf.stickers', [
            'reception' => $reception,
            'stickers' => $stickers,
            'company' => $company,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'portrait');
    }

    protected function logoBase64(?CompanySetting $company): ?string
    {
        if (! $company || ! $company->logo_path || ! Storage::disk('public')->exists($company->logo_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($company->logo_path) ?: 'image/png';
        $contents = Storage::disk('public')->get($company->logo_path);

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
