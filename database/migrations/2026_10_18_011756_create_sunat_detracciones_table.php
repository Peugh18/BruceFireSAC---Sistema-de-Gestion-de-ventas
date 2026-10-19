<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo 54 de SUNAT (bienes y servicios sujetos a detracción) con sus
     * porcentajes por vigencia, y el estado de revisión de cada tipo de
     * servicio y servicio. Son datos de referencia oficiales: se cargan aquí.
     */
    public function up(): void
    {
        Schema::create('sunat_detracciones', function (Blueprint $table) {
            $table->string('codigo', 3)->primary();
            $table->string('nombre');
            $table->string('fuente');
            // Soportado: el sistema sabe calcularlo en una factura de servicios.
            $table->boolean('soportado')->default(true);
        });

        Schema::create('sunat_detraccion_reglas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 3);
            $table->foreign('codigo')->references('codigo')->on('sunat_detracciones')->cascadeOnDelete();
            $table->decimal('porcentaje', 5, 2);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
        });

        $fuente = 'https://www.sunat.gob.pe/legislacion/superin/2004/183.htm';
        // Anexo 3 de la R.S. 183-2004/SUNAT y modificatorias (tasas desde la R.S. 343-2014/SUNAT).
        $servicios = [
            ['012', 'Intermediación laboral y tercerización', 12],
            ['019', 'Arrendamiento de bienes muebles e inmuebles', 10],
            ['020', 'Mantenimiento y reparación de bienes muebles', 12],
            ['021', 'Movimiento de carga', 10],
            ['022', 'Otros servicios empresariales', 12],
            ['024', 'Comisión mercantil', 10],
            ['025', 'Fabricación de bienes por encargo', 10],
            ['026', 'Servicio de transporte de personas', 10],
            ['030', 'Contratos de construcción', 4],
            ['037', 'Demás servicios gravados con el IGV', 12],
        ];

        foreach ($servicios as [$codigo, $nombre, $porcentaje]) {
            DB::table('sunat_detracciones')->insert(['codigo' => $codigo, 'nombre' => $nombre, 'fuente' => $fuente, 'soportado' => true]);
            DB::table('sunat_detraccion_reglas')->insert(['codigo' => $codigo, 'porcentaje' => $porcentaje, 'vigente_desde' => '2015-01-01']);
        }

        // El transporte de carga tiene su propio régimen (operación 1004): solo de consulta.
        DB::table('sunat_detracciones')->insert([
            'codigo' => '027',
            'nombre' => 'Servicio de transporte de carga',
            'fuente' => 'https://www.sunat.gob.pe/legislacion/superin/2006/073.htm',
            'soportado' => false,
        ]);

        foreach (['tipos_de_servicio', 'services'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                // no_sujeto | pendiente | sujeto. Vacío en servicios anteriores: se deduce como antes.
                $table->string('estado_detraccion', 12)->nullable()->after('codigo_detraccion');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            // Catálogo 25 (UNSPSC), opcional: va al XML de la factura.
            $table->string('codigo_sunat', 8)->nullable()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('codigo_sunat'));
        foreach (['tipos_de_servicio', 'services'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn('estado_detraccion'));
        }
        Schema::dropIfExists('sunat_detraccion_reglas');
        Schema::dropIfExists('sunat_detracciones');
    }
};
