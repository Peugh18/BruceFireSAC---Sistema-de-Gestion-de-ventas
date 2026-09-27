<?php

namespace App\Services\Avisos;

use App\Models\Deficiency;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que el vendedor tiene que atender de Servicios, solo de su sede:
 * deficiencias que esperan la respuesta del cliente, órdenes listas para
 * entregar y órdenes que nadie ha tomado. Alimenta la campana, el contador
 * del menú y los avisos del Inicio (un solo lugar para no descuadrar).
 */
class AvisosDelVendedor
{
    public function contar(User $user): int
    {
        return $this->deficiencias($user)->count()
            + $this->listasParaEntregar($user)->count()
            + $this->sinTecnico($user)->count();
    }

    /**
     * @return list<array{id: string, tipo: string, titulo: string, mensaje: string, url: string, fecha: string, urgencia: string}>
     */
    public function lista(User $user, string $teamSlug): array
    {
        $avisos = [];
        $alDetalle = fn (ServiceOrder $orden) => route('vendedor.ordenes-servicio.show', ['current_team' => $teamSlug, 'service_order' => $orden->id]);

        foreach ($this->deficiencias($user)->with('serviceOrder.client')->latest('id')->take(5)->get() as $deficiencia) {
            $avisos[] = [
                'id' => 'def-'.$deficiencia->id,
                'tipo' => 'autorizacion_pendiente',
                'titulo' => 'El cliente debe autorizar un trabajo extra',
                'mensaje' => sprintf('%s (%s): %s — %s', $deficiencia->serviceOrder?->codigo, $deficiencia->serviceOrder?->client?->razon_social, $deficiencia->componente, $deficiencia->condicion),
                'url' => route('vendedor.deficiencias.index', ['current_team' => $teamSlug]),
                'fecha' => $deficiencia->created_at?->diffForHumans() ?? 'Reciente',
                'urgencia' => 'alta',
            ];
        }

        foreach ($this->listasParaEntregar($user)->with('client')->latest('id')->take(5)->get() as $orden) {
            $avisos[] = [
                'id' => 'ord-lista-'.$orden->id,
                'tipo' => 'orden_lista_entrega',
                'titulo' => 'Listo para entregar',
                'mensaje' => sprintf('%s de %s está listo: coordina la entrega con el cliente.', $orden->codigo, $orden->client?->razon_social),
                'url' => $alDetalle($orden),
                'fecha' => $orden->updated_at?->diffForHumans() ?? 'Reciente',
                'urgencia' => 'media',
            ];
        }

        foreach ($this->sinTecnico($user)->with('client')->latest('id')->take(5)->get() as $orden) {
            $avisos[] = [
                'id' => 'ord-sin-tec-'.$orden->id,
                'tipo' => 'orden_sin_tecnico',
                'titulo' => 'Orden sin técnico',
                'mensaje' => sprintf('%s de %s todavía no tiene técnico: asígnalo desde la orden.', $orden->codigo, $orden->client?->razon_social),
                'url' => $alDetalle($orden),
                'fecha' => $orden->created_at?->diffForHumans() ?? 'Reciente',
                'urgencia' => 'baja',
            ];
        }

        return $avisos;
    }

    /** @return Builder<Deficiency> */
    protected function deficiencias(User $user): Builder
    {
        return Deficiency::query()
            ->where('estado', 'esperando_autorizacion')
            ->whereHas('serviceOrder', fn (Builder $query) => $this->deSuSede($query, $user));
    }

    /** @return Builder<ServiceOrder> */
    protected function listasParaEntregar(User $user): Builder
    {
        return $this->deSuSede(ServiceOrder::query(), $user)->where('estado', 'listo_entrega');
    }

    /** @return Builder<ServiceOrder> */
    protected function sinTecnico(User $user): Builder
    {
        return $this->deSuSede(ServiceOrder::query(), $user)
            ->whereNull('tecnico_id')
            // Solo las que aún no empiezan: ya en trabajo o listas no se avisan de nuevo.
            ->whereIn('estado', ['pendiente_recepcion', 'recibido_planta', 'en_revision']);
    }

    protected function deSuSede(Builder $query, User $user): Builder
    {
        $sedeId = $user->sedeRestringidaId();

        return $query->when($sedeId, fn (Builder $query) => $query->where('sede_id', $sedeId));
    }
}
