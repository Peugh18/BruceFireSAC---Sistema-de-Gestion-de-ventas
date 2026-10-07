<?php

namespace App\Services\Inventory;

use App\Models\CompanySetting;
use App\Models\Equipment;
use App\Models\InventoryUnit;
use App\Models\Reception;
use App\Models\ServiceOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorPNG;

class StickerPdfService
{
    /** Stickers por hoja A4: 4 columnas x 5 filas de 5 x 5 cm. */
    public const POR_HOJA = 20;

    /**
     * Stickers de las unidades con serie recibidas en la recepción (§84.9).
     * `$inicio` es la posición de la hoja (1 a 20) donde empieza, para
     * aprovechar una hoja ya usada.
     */
    public function generate(Reception $reception, int $inicio = 1): DompdfWrapper
    {
        $reception->loadMissing('movements.inventoryUnit');

        $series = $reception->movements
            ->pluck('inventoryUnit')
            ->filter()
            ->unique('id')
            ->map(fn (InventoryUnit $unit) => $unit->numero_serie)
            ->values();

        return $this->render($series, $inicio);
    }

    /**
     * Reimpresión de un solo sticker.
     */
    public function generateForUnit(InventoryUnit $unit, int $inicio = 1): DompdfWrapper
    {
        return $this->render(collect([$unit->numero_serie]), $inicio);
    }

    /**
     * Stickers de los equipos del taller (§18).
     *
     * @param  iterable<Equipment>  $equipments
     */
    public function generateForEquipments(iterable $equipments, ?ServiceOrder $serviceOrder = null, int $inicio = 1): DompdfWrapper
    {
        $series = collect($equipments)->map(fn (Equipment $equipment) => $equipment->numero_serie)->values();

        return $this->render($series, $inicio);
    }

    /**
     * Solo logo, código de barras Code 128 y la serie debajo.
     *
     * @param  Collection<int, string>  $series
     */
    protected function render(Collection $series, int $inicio): DompdfWrapper
    {
        $generator = new BarcodeGeneratorPNG;
        $stickers = $series->map(fn (string $serie) => [
            'numero_serie' => $serie,
            'barcode_base64' => base64_encode($generator->getBarcode($serie, $generator::TYPE_CODE_128, 2, 48)),
        ]);

        return Pdf::loadView('pdf.stickers', [
            'stickers' => $stickers,
            'inicio' => max(1, min(self::POR_HOJA, $inicio)),
            'logoBase64' => $this->logoBase64(CompanySetting::current()),
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
