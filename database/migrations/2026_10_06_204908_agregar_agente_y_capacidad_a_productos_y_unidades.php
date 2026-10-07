<?php

use App\Enums\EquipmentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C1: el agente extintor y la capacidad nacen en el producto y se copian a
 * la unidad del almacén y al equipo del cliente. Los extintores del catálogo
 * toman el agente que dice su nombre; si el nombre no lo dice, queda vacío
 * para que el Gerente lo revise (nunca se asume PQS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('agente', 30)->nullable()->after('categoria');
            $table->string('capacidad', 20)->nullable()->after('agente');
        });

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->string('agente', 30)->nullable()->after('capacidad');
        });

        // El checklist guarda el tipo de equipo con la misma lista (se suma PQS BC).
        Schema::table('technical_checklists', function (Blueprint $table) {
            $table->enum('tipo_equipo', ['pqs', 'pqs_bc', 'co2', 'agua', 'espuma', 'acetato_potasio', 'agente_limpio', 'otro'])->nullable()->change();
        });

        DB::table('products')->where('categoria', 'extintor')->orderBy('id')->each(function (object $producto) {
            $agente = EquipmentType::fromDescription($producto->nombre);

            if ($agente !== EquipmentType::Otro) {
                DB::table('products')->where('id', $producto->id)->update(['agente' => $agente->value]);
            }
        });

        DB::table('inventory_units')
            ->join('products', 'products.id', '=', 'inventory_units.product_id')
            ->whereNotNull('products.agente')
            ->update(['inventory_units.agente' => DB::raw('products.agente')]);
    }

    public function down(): void
    {
        DB::table('technical_checklists')->where('tipo_equipo', EquipmentType::PqsBc->value)->update(['tipo_equipo' => EquipmentType::Pqs->value]);

        Schema::table('technical_checklists', function (Blueprint $table) {
            $table->enum('tipo_equipo', ['pqs', 'co2', 'agua', 'espuma', 'acetato_potasio', 'agente_limpio', 'otro'])->nullable()->change();
        });

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->dropColumn('agente');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['agente', 'capacidad']);
        });
    }
};
