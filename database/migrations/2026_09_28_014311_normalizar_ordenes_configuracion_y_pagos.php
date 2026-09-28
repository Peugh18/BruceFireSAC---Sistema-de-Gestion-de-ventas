<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('service_orders')
            ->whereNull('service_id')
            ->orderBy('id')
            ->each(function (object $order): void {
                $name = trim((string) $order->tipo_servicio) ?: 'Servicio no especificado';
                $serviceId = DB::table('services')->whereRaw('LOWER(nombre) = LOWER(?)', [$name])->value('id');

                if ($serviceId === null) {
                    $baseCode = 'LEG-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
                    $serviceId = DB::table('services')->insertGetId([
                        'codigo' => $baseCode,
                        'nombre' => $name,
                        'descripcion' => 'Servicio recuperado al normalizar órdenes históricas.',
                        'unidad_medida' => 'ZZ',
                        'precio_venta' => 0,
                        'aplica_igv' => true,
                        'activo' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('service_orders')->where('id', $order->id)->update(['service_id' => $serviceId]);
            });

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropForeign(['service_id']);
        });
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('service_id')->nullable(false)->change();
            $table->dropColumn('tipo_servicio');
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
        });

        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'firma_tecnico_nombre',
                'firma_administrador_nombre',
                'firma_ingeniero_nombre',
                'firma_ingeniero_cip',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->string('firma_tecnico_nombre', 120)->nullable();
            $table->string('firma_administrador_nombre', 120)->nullable();
            $table->string('firma_ingeniero_nombre', 120)->nullable();
            $table->string('firma_ingeniero_cip', 40)->nullable();
        });

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->string('tipo_servicio')->nullable()->after('service_id');
            $table->dropForeign(['service_id']);
        });
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('service_id')->nullable()->change();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });

        DB::table('service_orders')->orderBy('id')->each(function (object $order): void {
            DB::table('service_orders')->where('id', $order->id)->update([
                'tipo_servicio' => DB::table('services')->where('id', $order->service_id)->value('nombre'),
            ]);
        });
    }
};
