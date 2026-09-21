<?php

namespace App\Observers;

use App\Models\Sede;
use InvalidArgumentException;

class SedeObserver
{
    public function saving(Sede $sede): void
    {
        if ($sede->tipo === 'tienda' && ! $sede->almacen_id) {
            throw new InvalidArgumentException('Una sede de tipo "tienda" debe indicar de qué almacén saca stock (almacen_id).');
        }
    }
}
