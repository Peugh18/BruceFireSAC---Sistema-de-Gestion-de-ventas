<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase H: guía de remisión electrónica. Datos maestros (vehículos de la
 * empresa, conductores, peso por producto, establecimiento anexo por sede),
 * traslados entre sedes "en tránsito" y las guías con sus ítems.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 10)->unique();
            // M1 (auto hasta 8 asientos), L (moto) o N (camión).
            $table->string('categoria', 2);
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 8)->unique();
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('licencia', 20);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('peso_kg', 10, 3)->nullable()->after('capacidad');
        });

        Schema::table('sedes', function (Blueprint $table) {
            $table->string('direccion')->nullable()->after('ubigeo');
            // Código de establecimiento anexo SUNAT (catálogo del RUC): 0000 es la sede principal.
            $table->string('cod_establecimiento_anexo', 4)->default('0000')->after('direccion');
        });

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->enum('estado', ['disponible', 'reservado', 'vendido', 'baja', 'en_transito'])->default('disponible')->change();
        });

        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origen_sede_id')->constrained('sedes');
            $table->foreignId('destino_sede_id')->constrained('sedes');
            $table->foreignId('user_id')->constrained();
            $table->string('estado', 20)->default('en_transito');
            $table->string('observacion')->nullable();
            $table->foreignId('recibido_por')->nullable()->constrained('users');
            $table->timestamp('recibido_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            // Salida del origen: de ahí sale el lote que entra al destino.
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements');
            $table->foreignId('inventory_unit_id')->nullable()->constrained('inventory_units');
            $table->unsignedInteger('cantidad');
            $table->timestamps();
        });

        Schema::create('dispatch_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes');
            $table->foreignId('user_id')->constrained();
            $table->string('serie', 4);
            $table->unsignedInteger('correlativo');
            $table->dateTime('fecha_emision');
            $table->date('fecha_traslado');
            $table->string('motivo', 2);
            $table->string('motivo_descripcion')->nullable();
            $table->string('modalidad', 2);
            $table->string('destinatario_tipo_doc', 1);
            $table->string('destinatario_num_doc', 15);
            $table->string('destinatario_nombre');
            $table->string('partida_ubigeo', 6);
            $table->string('partida_direccion');
            $table->string('partida_cod_establecimiento', 4)->nullable();
            $table->string('llegada_ubigeo', 6);
            $table->string('llegada_direccion');
            $table->string('llegada_cod_establecimiento', 4)->nullable();
            $table->decimal('peso_bruto', 10, 3);
            $table->foreignId('transport_vehicle_id')->nullable()->constrained('transport_vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->string('transportista_ruc', 11)->nullable();
            $table->string('transportista_razon')->nullable();
            $table->foreignId('sale_id')->nullable()->constrained();
            $table->foreignId('service_order_id')->nullable()->constrained();
            $table->foreignId('inventory_transfer_id')->nullable()->constrained();
            $table->string('doc_relacionado_tipo', 2)->nullable();
            $table->string('doc_relacionado_numero', 20)->nullable();
            // borrador, enviada (hay ticket), aceptada (CDR aceptado), rechazada.
            $table->string('estado_sunat', 20)->default('borrador');
            $table->string('sunat_ticket', 40)->nullable();
            $table->string('sunat_codigo', 10)->nullable();
            $table->text('sunat_mensaje')->nullable();
            $table->string('hash_zip', 64)->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->unique(['serie', 'correlativo']);
        });

        Schema::create('dispatch_guide_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_guide_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->string('codigo', 30)->nullable();
            $table->string('descripcion');
            $table->string('unidad', 3)->default('NIU');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('peso_kg', 10, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_guide_items');
        Schema::dropIfExists('dispatch_guides');
        Schema::dropIfExists('inventory_transfer_items');
        Schema::dropIfExists('inventory_transfers');
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->enum('estado', ['disponible', 'reservado', 'vendido', 'baja'])->default('disponible')->change();
        });
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'cod_establecimiento_anexo']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('peso_kg');
        });
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('transport_vehicles');
    }
};
