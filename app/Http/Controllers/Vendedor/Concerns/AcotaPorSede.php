<?php

namespace App\Http\Controllers\Vendedor\Concerns;

use App\Models\Certificate;
use App\Models\Equipment;
use App\Models\Sale;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cada vendedor opera solo lo de su sede (el Gerente ve todas). Lo que no
 * es de su sede responde 404, como si no existiera. La regla vive en el
 * scope `visiblePara()` de cada modelo; aquí solo se aplica.
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
     * 404 si el usuario no puede ver el registro (scope `visiblePara`).
     */
    protected function asegurarVisible(Sale|ServiceOrder|Certificate|Equipment $modelo): void
    {
        $user = request()->user();

        abort_if($user === null, 404);

        $visible = match (true) {
            $modelo instanceof Sale => Sale::query()->visiblePara($user),
            $modelo instanceof ServiceOrder => ServiceOrder::query()->visiblePara($user),
            $modelo instanceof Certificate => Certificate::query()->visiblePara($user),
            $modelo instanceof Equipment => Equipment::query()->visiblePara($user),
        };

        abort_unless($visible->whereKey($modelo->getKey())->exists(), 404);
    }

    /**
     * Una venta (y sus comprobantes) solo la opera quien la hizo, dentro de
     * su sede; el Gerente opera todas.
     */
    protected function asegurarVenta(?Sale $sale): void
    {
        abort_if($sale === null, 404);
        $this->asegurarVisible($sale);
    }

    protected function asegurarSedeDeCertificado(Certificate $certificate): void
    {
        $this->asegurarVisible($certificate);
    }

    /**
     * @param  Builder<Certificate>  $query
     * @return Builder<Certificate>
     */
    protected function certificadosDeMiSede(Builder $query): Builder
    {
        $user = request()->user();

        return $user ? $query->visiblePara($user) : $query;
    }
}
