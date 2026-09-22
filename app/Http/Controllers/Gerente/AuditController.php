<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    /**
     * Visor de auditoría para acciones sensibles del sistema (§37).
     */
    public function index(Team $current_team, Request $request): Response
    {
        $accion = (string) $request->input('accion', 'todas');
        $userId = $request->input('user_id');
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $query = AuditLog::query()
            ->with('user:id,name')
            ->when($accion !== 'todas', fn ($q) => $q->where('action', $accion))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($fechaDesde, fn ($q) => $q->whereDate('created_at', '>=', $fechaDesde))
            ->when($fechaHasta, fn ($q) => $q->whereDate('created_at', '<=', $fechaHasta))
            ->orderByDesc('created_at');

        $registros = $query->paginate(20)->withQueryString()->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'accion' => $log->action,
            'usuario' => $log->user?->name ?? 'Sistema',
            'entidad' => $log->auditable_type ? class_basename($log->auditable_type) : null,
            'entidad_id' => $log->auditable_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'fecha' => $log->created_at?->toDateTimeString(),
        ]);

        $acciones = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $usuarios = User::query()
            ->whereHas('auditLogs')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('gerente/auditoria/index', [
            'registros' => $registros,
            'filters' => [
                'accion' => $accion,
                'user_id' => $userId,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
            'acciones' => $acciones,
            'usuarios' => $usuarios,
            'kpis' => [
                'totalRegistros' => AuditLog::count(),
                'registrosHoy' => AuditLog::whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
