<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateUnit;
use Illuminate\Support\Collection;

class PruebasHidrostaticas
{
    /**
     * La PH pertenece al extintor y al cliente. Reutilizarla no cambia el
     * certificado ni reinicia sus cinco años desde la prueba real.
     *
     * @param  list<int>  $equipmentIds
     * @return Collection<int, CertificateUnit>
     */
    public static function vigentes(int $clientId, array $equipmentIds): Collection
    {
        return Certificate::query()
            ->with(['certificateUnits' => fn ($query) => $query
                ->whereIn('equipment_id', $equipmentIds)
                ->whereDate('fecha_ultima_ph', '>', today()->subYears(5))
                ->whereDate('fecha_ultima_ph', '<=', today())
                ->whereHas('equipment', fn ($equipment) => $equipment
                    ->where('client_id', $clientId)
                    ->where('estado', '!=', 'baja'))])
            ->where('client_id', $clientId)
            ->where('estado', 'vigente')
            ->whereDate('fecha_vigencia_hasta', '>=', today())
            ->whereHas('certificateType', fn ($query) => $query->where('codigo', 'prueba_hidrostatica'))
            ->whereHas('certificateUnits', fn ($query) => $query->whereIn('equipment_id', $equipmentIds))
            ->orderBy('fecha_emision')
            ->orderBy('id')
            ->get()
            ->flatMap(function (Certificate $certificate) {
                return $certificate->certificateUnits->map(fn (CertificateUnit $unit) => $unit->setRelation('certificate', $certificate->withoutRelations()));
            })
            ->keyBy('equipment_id');
    }
}
