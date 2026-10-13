<?php

namespace App\Services\Chispa;

use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\User;

/**
 * Unas pocas cifras de hoy que Chispa puede citar. Son las mismas que el
 * usuario ya ve en su pantalla de Inicio: lo que su rol puede ver y nada más.
 * Chispa no consulta la base de datos por su cuenta.
 */
class DatosDelMomento
{
    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    public function para(User $user, array $roles): array
    {
        $lineas = [];

        if (in_array('Gerente', $roles, true)) {
            $emitidas = Sale::query()->where('estado', 'confirmada')->whereDate('fecha', today());
            $lineas[] = 'Ventas emitidas hoy en toda la empresa: '.$emitidas->count().' por S/ '.number_format((float) $emitidas->sum('total'), 2).'.';
        } elseif (in_array('Vendedor', $roles, true)) {
            $propias = Sale::query()->where('vendedor_id', $user->id)->where('estado', 'confirmada')->whereDate('fecha', today());
            $lineas[] = 'Tus ventas emitidas hoy: '.$propias->count().' por S/ '.number_format((float) $propias->sum('total'), 2).'.';
            $lineas[] = CashRegister::query()->where('vendedor_id', $user->id)->where('estado', 'abierto')->exists()
                ? 'Tu caja está abierta.'
                : 'Tu caja está cerrada (para cobrar en efectivo hay que abrirla).';
        }

        foreach (['TecnicoPlanta' => 'planta', 'TecnicoCampo' => 'campo'] as $rol => $area) {
            if (! in_array($rol, $roles, true)) {
                continue;
            }

            $abiertas = ServiceOrder::query()->accessibleToTechnician($user)
                ->where('departamento_tecnico', $area)
                ->whereNotIn('estado', ['entregado', 'cerrado', 'anulada'])
                ->count();
            $lineas[] = "Órdenes abiertas que puedes atender en {$area}: {$abiertas}.";
        }

        return $lineas;
    }
}
