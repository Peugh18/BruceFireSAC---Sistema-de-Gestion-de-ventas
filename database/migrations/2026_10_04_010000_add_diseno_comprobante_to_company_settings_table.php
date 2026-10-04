<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diseño de la representación impresa del comprobante que el Gerente
     * ajusta una sola vez: color de la marca, página web, mensaje de
     * agradecimiento y condiciones de venta/garantía.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('color_marca', 7)->default('#D2232A')->after('logo_path');
            $table->string('sitio_web')->nullable()->after('email');
            $table->string('mensaje_agradecimiento')->nullable()->after('leyenda_pie');
            $table->text('condiciones_comprobante')->nullable()->after('mensaje_agradecimiento');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['color_marca', 'sitio_web', 'mensaje_agradecimiento', 'condiciones_comprobante']);
        });
    }
};
