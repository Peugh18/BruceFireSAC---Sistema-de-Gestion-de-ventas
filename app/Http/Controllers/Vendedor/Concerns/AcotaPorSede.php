<?php

namespace App\Http\Controllers\Vendedor\Concerns;

use App\Models\Certificate;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cada vendedor opera solo lo de su sede (el Gerente ve todas). Lo que no
 * es de su sede responde 404, como si no existiera.
 */
trait AcotaPorSede
{
    protected function sedeDelUsuario(): ?int
    {
        return request()->user()?->sedeRestringidaId();
    }

    protected function asegurarSede(?int $sedeId): void
    {
        $miSede = $this->sedeDelUsuario();

        abort_if($miSede !== null && (int) $sedeId !== $miSede, 404);
    }

    /**
     * Una venta (y sus comprobantes) solo la opera quien la hizo, dentro de
     * su sede; el Gerente opera todas.
     */
    protected function asegurarVenta(?Sale $sale): void
    {
        abort_if($sale === null, 404);
        $this->asegurarSede($sale->sede_id);

        $vendedorId = request()->user()?->vendedorRestringidoId();
        abort_if($vendedorId !== null && (int) $sale->vendedor_id !== $vendedorId, 404);
    }

    /**
     * Sede de un certificado: la de su venta o la de su orden de servicio.
     */
    protected function asegurarSedeDeCertificado(Certificate $certificate): void
    {
        $certificate->loadMissing('sale:id,sede_id', 'serviceOrder:id,sede_id');

        $this->asegurarSede($certificate->sale?->sede_id ?? $certificate->serviceOrder?->sede_id);
    }

    /**
     * @param  Builder<Certificate>  $query
     * @return Builder<Certificate>
     */
    protected function certificadosDeMiSede(Builder $query): Builder
    {
        $miSede = $this->sedeDelUsuario();

        return $query->when($miSede, fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->whereHas('sale', fn (Builder $venta) => $venta->where('sede_id', $miSede))
            ->orWhereHas('serviceOrder', fn (Builder $orden) => $orden->where('sede_id', $miSede))));
    }
}
