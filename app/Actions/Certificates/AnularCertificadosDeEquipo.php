<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use App\Models\Equipment;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnularCertificadosDeEquipo
{
    public function handle(Equipment $equipo, string $motivo, int $userId): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages(['observaciones' => 'Indica por qué el extintor no es apto para uso.']);
        }

        DB::transaction(function () use ($equipo, $motivo, $userId): void {
            $equipo = Equipment::query()->lockForUpdate()->findOrFail($equipo->id);
            $equipo->update(['estado' => 'baja']);
            $certificados = Certificate::query()
                ->where('client_id', $equipo->client_id)
                ->whereIn('estado', ['vigente', 'vencido'])
                ->whereHas('certificateType', fn ($query) => $query->whereIn('codigo', ['operatividad_garantia', 'prueba_hidrostatica']))
                ->whereHas('certificateUnits', fn ($query) => $query->where('equipment_id', $equipo->id))
                ->lockForUpdate()->get();

            foreach ($certificados as $certificado) {
                $certificado->update([
                    'estado' => 'anulado',
                    'anulado_motivo' => "Extintor {$equipo->numero_serie} no apto: {$motivo}",
                    'anulado_at' => now(),
                    'anulado_por' => $userId,
                ]);
            }
            AuditLogger::log(action: 'equipo.no_apto', entity: $equipo, newValues: [
                'motivo' => $motivo,
                'certificados_anulados' => $certificados->modelKeys(),
            ], userId: $userId);
        });
    }
}
