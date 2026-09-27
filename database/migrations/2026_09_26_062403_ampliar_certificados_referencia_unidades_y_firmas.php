<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Certificados como los de Bruce Fire: referencia (placa o sede) y
     * dirección del local, tipo de atención, datos de capacitación, datos
     * técnicos de cada extintor, numeración propia del cliente y firmantes.
     */
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->date('fecha_vigencia_hasta')->nullable()->change();
            $table->string('referencia', 150)->nullable()->after('client_id');
            $table->string('direccion', 255)->nullable()->after('referencia');
            $table->string('tipo_atencion', 20)->nullable()->after('direccion');
            $table->json('datos')->nullable()->after('tipo_atencion');
        });

        Schema::table('certificate_units', function (Blueprint $table) {
            $table->unsignedInteger('orden')->nullable()->after('equipment_id');
            $table->string('numero_cliente', 40)->nullable()->after('orden');
            $table->string('capacidad', 50)->nullable()->after('numero_serie_snapshot');
            $table->string('marca', 100)->nullable()->after('capacidad');
            $table->string('tipo_agente', 50)->nullable()->after('marca');
            $table->unsignedSmallInteger('anio_fabricacion')->nullable()->after('tipo_agente');
            $table->string('presion_ph', 30)->nullable()->after('fecha_ultima_recarga');
            $table->string('tiempo_ph', 30)->nullable()->after('presion_ph');
            $table->string('presion_trabajo', 30)->nullable()->after('tiempo_ph');
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->string('numero_cliente', 40)->nullable()->after('numero_serie');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('firma_tecnico_nombre', 120)->nullable();
            $table->string('firma_administrador_nombre', 120)->nullable();
            $table->string('firma_ingeniero_nombre', 120)->nullable();
            $table->string('firma_ingeniero_cip', 40)->nullable();
            $table->string('instructor_capacitacion', 120)->nullable();
        });

        DB::table('company_settings')->update([
            'firma_tecnico_nombre' => 'ANDER AVALOS C.',
            'firma_administrador_nombre' => 'GROBER GUEVARA C.',
            'firma_ingeniero_nombre' => 'T. HOMAR FLORES G.',
            'firma_ingeniero_cip' => '293886',
            'instructor_capacitacion' => 'Edgar Guevara Cabrera',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['firma_tecnico_nombre', 'firma_administrador_nombre', 'firma_ingeniero_nombre', 'firma_ingeniero_cip', 'instructor_capacitacion']);
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('numero_cliente');
        });

        Schema::table('certificate_units', function (Blueprint $table) {
            $table->dropColumn(['orden', 'numero_cliente', 'capacidad', 'marca', 'tipo_agente', 'anio_fabricacion', 'presion_ph', 'tiempo_ph', 'presion_trabajo']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['referencia', 'direccion', 'tipo_atencion', 'datos']);
        });
    }
};
