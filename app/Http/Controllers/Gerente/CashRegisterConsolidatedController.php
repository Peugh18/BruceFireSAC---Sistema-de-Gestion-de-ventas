<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Sede;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashRegisterConsolidatedController extends Controller
{
    /**
     * Consulta consolidada de cajas de todos los vendedores (§77.2 punto 4).
     */
    public function index(Team $current_team, Request $request): Response
    {
        $vendedorId = $request->input('vendedor_id');
        $sedeId = $request->input('sede_id');
        $estado = (string) $request->input('estado', 'todos');
        $conDiferencia = $request->boolean('con_diferencia', false);
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $query = CashRegister::query()
            ->with(['vendedor:id,name,email', 'sede:id,nombre'])
            ->when($vendedorId, fn ($q) => $q->where('vendedor_id', $vendedorId))
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->when($estado === 'abierto', fn ($q) => $q->where('estado', 'abierto'))
            ->when($estado === 'cerrado', fn ($q) => $q->where('estado', 'cerrado'))
            ->when($conDiferencia, function ($q) {
                $q->where('estado', 'cerrado')
                    ->whereNotNull('diferencia')
                    ->where('diferencia', '!=', 0);
            })
            ->when($fechaDesde, fn ($q) => $q->whereDate('fecha_apertura', '>=', $fechaDesde))
            ->when($fechaHasta, fn ($q) => $q->whereDate('fecha_apertura', '<=', $fechaHasta))
            ->orderByDesc('fecha_apertura');

        $cajas = $query->paginate(15)->withQueryString()->through(function (CashRegister $cr) {
            $dif = $cr->diferencia !== null ? (float) $cr->diferencia : null;
            $tipoDiferencia = null;
            if ($cr->estado === 'cerrado' && $dif !== null) {
                if ($dif === 0.0) {
                    $tipoDiferencia = 'cuadrado';
                } elseif ($dif > 0) {
                    $tipoDiferencia = 'sobrante';
                } else {
                    $tipoDiferencia = 'faltante';
                }
            }

            return [
                'id' => $cr->id,
                'vendedor' => [
                    'id' => $cr->vendedor->id,
                    'name' => $cr->vendedor->name,
                ],
                'sede' => $cr->sede?->nombre ?? 'Principal',
                'estado' => $cr->estado,
                'fecha_apertura' => $cr->fecha_apertura?->toDateTimeString(),
                'fecha_cierre' => $cr->fecha_cierre?->toDateTimeString(),
                'monto_apertura' => (float) $cr->monto_apertura,
                'monto_contado_cierre' => $cr->monto_contado_cierre !== null ? (float) $cr->monto_contado_cierre : null,
                'monto_esperado_calculado' => $cr->monto_esperado_calculado !== null ? (float) $cr->monto_esperado_calculado : null,
                'diferencia' => $dif,
                'tipo_diferencia' => $tipoDiferencia,
                'observacion' => $cr->observacion,
            ];
        });

        // KPIs superiores
        $turnosHoy = CashRegister::query()
            ->whereDate('fecha_apertura', today())
            ->count();

        $turnosAbiertos = CashRegister::query()
            ->where('estado', 'abierto')
            ->count();

        $totalDiferenciasMes = (float) CashRegister::query()
            ->where('estado', 'cerrado')
            ->whereBetween('fecha_cierre', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('diferencia');

        $turnosConDescuadreMes = CashRegister::query()
            ->where('estado', 'cerrado')
            ->whereBetween('fecha_cierre', [now()->startOfMonth(), now()->endOfMonth()])
            ->whereNotNull('diferencia')
            ->where('diferencia', '!=', 0)
            ->count();

        // Vendedores y Sedes para filtros
        $vendedores = User::role('Vendedor')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $sedes = Sede::query()
            ->where('activo', true)
            ->select('id', 'nombre')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('gerente/cajas/index', [
            'cajas' => $cajas,
            'filters' => [
                'vendedor_id' => $vendedorId,
                'sede_id' => $sedeId,
                'estado' => $estado,
                'con_diferencia' => $conDiferencia,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
            'kpis' => [
                'turnosHoy' => $turnosHoy,
                'turnosAbiertos' => $turnosAbiertos,
                'totalDiferenciasMes' => round($totalDiferenciasMes, 2),
                'turnosConDescuadreMes' => $turnosConDescuadreMes,
            ],
            'vendedores' => $vendedores,
            'sedes' => $sedes,
        ]);
    }
}
