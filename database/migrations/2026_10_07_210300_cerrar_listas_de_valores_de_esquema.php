<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cierra las listas de valores que el dominio tiene cerradas pero la base
     * aceptaba como texto libre (el criterio de "enum cerrado" que ya se usó
     * en sales.comprobante_tipo):
     *
     * - inventory_transfers.estado: en_transito | recibido (los dos estados
     *   que maneja InventoryTransfer).
     * - dispatch_guides.estado_sunat: borrador | enviada | aceptada |
     *   rechazada (los cuatro que maneja DispatchGuide y GuiaRemisionService).
     * - sale_items.tipo_afectacion_igv y quote_items.tipo_afectacion_igv:
     *   10 | 20 | 30 (los códigos SUNAT que emite AfectacionIgv) o NULL.
     * - document_series.tipo_comprobante: los tipos cerrados de comprobante,
     *   más las dos familias dinámicas que la numeración interna usa como
     *   clave de contador: 'interno' (VTA/COT) y 'baja_YYYYMMDD' (las series
     *   RA/RC diarias de las comunicaciones de baja). Por eso no es un enum:
     *   su dominio es cerrado pero con una familia con fecha.
     *
     * No se tocó equipment.ubicacion_actual (es la ubicación física que
     * escribe el usuario, texto libre) ni deficiency_authorizations.autorizado_por
     * (el nombre de la persona que autorizó, también texto libre).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->checks() as [$table, $name, $expression]) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_reverse($this->checks()) as [$table, $name]) {
            DB::statement("ALTER TABLE `{$table}` DROP CHECK `{$name}`");
        }
    }

    /** @return list<array{string, string, string}> */
    private function checks(): array
    {
        return [
            ['inventory_transfers', 'inventory_transfers_estado_valido', "`estado` IN ('en_transito', 'recibido')"],
            ['dispatch_guides', 'dispatch_guides_estado_sunat_valido', "`estado_sunat` IN ('borrador', 'enviada', 'aceptada', 'rechazada')"],
            ['sale_items', 'sale_items_tipo_afectacion_igv_valido', "`tipo_afectacion_igv` IS NULL OR `tipo_afectacion_igv` IN ('10', '20', '30')"],
            ['quote_items', 'quote_items_tipo_afectacion_igv_valido', "`tipo_afectacion_igv` IS NULL OR `tipo_afectacion_igv` IN ('10', '20', '30')"],
            ['document_series', 'document_series_tipo_comprobante_valido', "`tipo_comprobante` REGEXP '^(factura|boleta|nota_venta|nota_credito|nota_debito|guia_remision|interno|baja_[0-9]{8})$'"],
        ];
    }
};
