<?php

namespace App\Services\Inventory;

use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\ServiceOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Support\Collection;
use Picqer\Barcode\BarcodeGeneratorPNG;

class StickerPdfService
{
    /**
     * Stickers de las unidades con serie recibidas en la recepción (§84.9).
     */
    public function generate(Reception $reception, string $formato = 'rollo'): DompdfWrapper
    {
        $reception->loadMissing(['movements.inventoryUnit.product', 'movements.product']);

        $stickers = $reception->movements
            ->pluck('inventoryUnit')
            ->filter(fn (mixed $unit): bool => $unit instanceof InventoryUnit)
            ->unique('id')
            ->map(fn (InventoryUnit $unit) => $this->stickerForUnit($unit))
            ->values();

        $productos = $reception->movements->pluck('product')
            ->filter(fn (mixed $product): bool => $product instanceof Product && ! $product->serializado)
            ->unique('id')->map(fn (Product $product): array => $this->stickerForProduct($product))->values();

        return $this->render($stickers->concat($productos), $formato);
    }

    public function generateForProduct(Product $product, string $formato = 'rollo'): DompdfWrapper
    {
        return $this->render(collect([$this->stickerForProduct($product)]), $formato);
    }

    /** @return array{numero_serie: string, marca: string, kilaje: string, agente: string, producto: string} */
    protected function stickerForProduct(Product $product): array
    {
        return ['numero_serie' => $product->codigo, 'marca' => '', 'kilaje' => '', 'agente' => '', 'producto' => $product->nombre];
    }

    /**
     * Reimpresión de un solo sticker.
     */
    public function generateForUnit(InventoryUnit $unit, string $formato = 'rollo'): DompdfWrapper
    {
        $unit->loadMissing('product');

        return $this->render(collect([$this->stickerForUnit($unit)]), $formato);
    }

    /**
     * Stickers de los equipos del taller (§18).
     *
     * @param  iterable<Equipment>  $equipments
     */
    public function generateForEquipments(iterable $equipments, ?ServiceOrder $serviceOrder = null, string $formato = 'rollo'): DompdfWrapper
    {
        $stickers = collect($equipments)->map(function (Equipment $equipment): array {
            $equipment->loadMissing('product');

            return $this->stickerForEquipment($equipment);
        })->values();

        return $this->render($stickers, $formato);
    }

    /**
     * @template T of array{numero_serie: string, marca: string, kilaje: string, agente: string, producto?: string}
     *
     * @param  Collection<int, T>  $datos
     */
    protected function render(Collection $datos, string $formato): DompdfWrapper
    {
        $generator = new BarcodeGeneratorPNG;
        $stickers = $datos->map(fn (array $sticker) => array_merge($sticker, [
            'barcode_base64' => base64_encode($generator->getBarcode(
                $sticker['numero_serie'],
                $generator::TYPE_CODE_128,
                2,
                48,
            )),
        ]))->values();

        return Pdf::loadView('pdf.stickers', compact('stickers', 'formato'))
            ->setPaper($formato === 'a4' ? 'a4' : [0, 0, 226.77, 141.73]);
    }

    /**
     * @return array{numero_serie: string, marca: string, kilaje: string, agente: string}
     */
    protected function stickerForUnit(InventoryUnit $unit): array
    {
        return [
            'numero_serie' => trim((string) $unit->numero_serie),
            'marca' => trim((string) $unit->marca),
            'kilaje' => $this->formatearKilaje($unit->capacidad ?? $unit->product->capacidad),
            'agente' => trim((string) $unit->agenteParaEquipo()),
        ];
    }

    /**
     * @return array{numero_serie: string, marca: string, kilaje: string, agente: string}
     */
    protected function stickerForEquipment(Equipment $equipment): array
    {
        return [
            'numero_serie' => trim((string) $equipment->numero_serie),
            'marca' => trim((string) $equipment->marca),
            'kilaje' => $this->formatearKilaje($equipment->capacidad ?? $equipment->product?->capacidad),
            'agente' => $this->etiquetarAgente($equipment->tipo_agente ?? $equipment->product?->agente),
        ];
    }

    protected function formatearKilaje(?string $capacidad): string
    {
        $valor = trim((string) $capacidad);

        if ($valor === '') {
            return '';
        }

        return is_numeric($valor) ? $valor.' kg' : $valor;
    }

    protected function etiquetarAgente(?string $agente): string
    {
        $valor = trim((string) $agente);

        if ($valor === '') {
            return '';
        }

        return EquipmentType::tryFrom($valor)?->etiqueta() ?? $valor;
    }
}
