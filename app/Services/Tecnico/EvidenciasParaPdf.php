<?php

namespace App\Services\Tecnico;

use App\Models\Evidencia;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\Storage;

/**
 * Las firmas y fotos de una orden como imágenes incrustadas (data URI) para
 * los PDF: los archivos son privados y el PDF no puede abrir una URL.
 */
class EvidenciasParaPdf
{
    /**
     * La firma más reciente de alguna de las etapas.
     *
     * @param  array<int, string>  $etapas
     */
    public function firma(ServiceOrder $orden, array $etapas): ?string
    {
        $firma = Evidencia::query()
            ->where('service_order_id', $orden->id)
            ->where('tipo', 'firma')
            ->whereIn('etapa', $etapas)
            ->latest('id')
            ->first();

        return $firma ? $this->incrustar($firma) : null;
    }

    /**
     * @param  array<int, string>  $etapas
     * @return array<int, array{etapa: string, equipo: string|null, src: string}>
     */
    public function fotos(ServiceOrder $orden, array $etapas, int $maximo = 6): array
    {
        return Evidencia::query()
            ->where('service_order_id', $orden->id)
            ->where('tipo', 'foto')
            ->whereIn('etapa', $etapas)
            ->oldest('id')
            ->limit($maximo)
            ->get()
            ->map(fn (Evidencia $foto) => [
                'etapa' => $foto->etapa,
                'equipo' => $foto->equipment_id ? (string) $orden->equipments()->whereKey($foto->equipment_id)->value('equipment.numero_serie') : null,
                'src' => $this->incrustar($foto),
            ])
            ->filter(fn (array $foto) => $foto['src'] !== null)
            ->values()
            ->all();
    }

    protected function incrustar(Evidencia $evidencia): ?string
    {
        if (! Storage::disk('local')->exists($evidencia->path)) {
            return null;
        }

        return 'data:'.$evidencia->mime.';base64,'.base64_encode((string) Storage::disk('local')->get($evidencia->path));
    }
}
