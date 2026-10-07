<?php

use App\Enums\EquipmentType;
use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase C (ventas, caja, cobranzas y alertas):
 * - V7: cada cobro y devolución queda ligado al turno de caja; la anulación
 *   de un cobro se registra en el turno donde se anuló.
 * - V4/S11: las notas aceptadas ajustan el saldo por cobrar con rastro.
 * - V6: el adicional autorizado guarda su importe y su cuota por cobrar.
 * - X7: tiempos de registro de venta y de cotización; alerta de origen.
 * - X8/X9: "contactado" en alertas; agente y capacidad del servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->foreignId('cash_register_id')->nullable()->after('user_id')->constrained('cash_registers')->nullOnDelete();
            $table->foreignId('anulacion_cash_register_id')->nullable()->after('anulado_motivo')->constrained('cash_registers')->nullOnDelete();
        });

        Schema::table('sale_refunds', function (Blueprint $table) {
            $table->foreignId('cash_register_id')->nullable()->after('user_id')->constrained('cash_registers')->nullOnDelete();
        });

        Schema::table('installments', function (Blueprint $table) {
            $table->decimal('monto_acreditado', 10, 2)->default(0)->after('monto');
            $table->foreignId('electronic_document_id')->nullable()->after('estado')->constrained('electronic_documents')->nullOnDelete();
            $table->foreignId('deficiency_authorization_id')->nullable()->after('electronic_document_id')->constrained('deficiency_authorizations')->nullOnDelete();
        });

        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->timestamp('saldo_aplicado_at')->nullable();
        });

        Schema::table('deficiency_authorizations', function (Blueprint $table) {
            $table->decimal('importe', 10, 2)->nullable()->after('cotizacion_adicional_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dateTime('registro_iniciado_at')->nullable();
            $table->dateTime('confirmada_at')->nullable();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dateTime('emitida_at')->nullable();
            $table->foreignId('origen_alerta_equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('agente', 30)->nullable()->after('unidad_medida');
            $table->string('capacidad', 20)->nullable()->after('agente');
        });

        Schema::create('alert_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->string('nota', 255)->nullable();
            $table->timestamps();

            $table->index(['client_id', 'fecha']);
        });

        $this->ligarCobrosAntiguosAlTurno('sale_payments');
        $this->ligarCobrosAntiguosAlTurno('sale_refunds');

        // Un cobro antiguo ya anulado dejaba de contar en su propio turno: se
        // conserva igual (la anulación queda en el mismo turno).
        DB::table('sale_payments')->whereNotNull('deleted_at')->update(['anulacion_cash_register_id' => DB::raw('cash_register_id')]);

        // Los servicios de recarga toman el agente y la capacidad de su nombre;
        // lo que no se reconoce queda vacío para que el Gerente lo complete.
        DB::table('services')->where('nombre', 'like', '%recarga%')->orderBy('id')->each(function (object $servicio) {
            $agente = EquipmentType::fromDescription($servicio->nombre);

            DB::table('services')->where('id', $servicio->id)->update([
                'agente' => $agente === EquipmentType::Otro ? null : $agente->value,
                'capacidad' => Service::capacidadDelTexto($servicio->nombre),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_contacts');

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['agente', 'capacidad']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origen_alerta_equipment_id');
            $table->dropColumn('emitida_at');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['registro_iniciado_at', 'confirmada_at']);
        });

        Schema::table('deficiency_authorizations', function (Blueprint $table) {
            $table->dropColumn('importe');
        });

        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->dropColumn('saldo_aplicado_at');
        });

        Schema::table('installments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deficiency_authorization_id');
            $table->dropConstrainedForeignId('electronic_document_id');
            $table->dropColumn('monto_acreditado');
        });

        Schema::table('sale_refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_register_id');
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulacion_cash_register_id');
            $table->dropConstrainedForeignId('cash_register_id');
        });
    }

    /**
     * Los cobros anteriores a esta fase se ligan al turno del vendedor que
     * estaba abierto cuando se registraron (como se calculaba antes).
     */
    protected function ligarCobrosAntiguosAlTurno(string $tabla): void
    {
        DB::table($tabla)
            ->join('sales', 'sales.id', '=', "{$tabla}.sale_id")
            ->join('cash_registers', function (JoinClause $join) use ($tabla) {
                $join->on('cash_registers.vendedor_id', '=', 'sales.vendedor_id')
                    ->whereColumn("{$tabla}.created_at", '>=', 'cash_registers.fecha_apertura')
                    ->where(fn ($query) => $query
                        ->whereNull('cash_registers.fecha_cierre')
                        ->orWhereColumn("{$tabla}.created_at", '<=', 'cash_registers.fecha_cierre'));
            })
            ->whereNull("{$tabla}.cash_register_id")
            ->update(["{$tabla}.cash_register_id" => DB::raw('cash_registers.id')]);
    }
};
