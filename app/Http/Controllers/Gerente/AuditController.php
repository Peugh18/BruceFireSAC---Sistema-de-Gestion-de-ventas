<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Sale;
use App\Models\ServiceOrder;
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
        $rol = (string) $request->input('rol', 'todos');
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $query = AuditLog::query()
            ->with(['user:id,name', 'auditable'])
            ->when($accion !== 'todas', fn ($q) => $q->where('action', $accion))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($rol === 'sin_rol_registrado', fn ($q) => $q->whereNull('actor_role'))
            ->when($rol !== 'todos' && $rol !== 'sin_rol_registrado', fn ($q) => $q->where('actor_role', $rol))
            ->when($fechaDesde, fn ($q) => $q->whereDate('created_at', '>=', $fechaDesde))
            ->when($fechaHasta, fn ($q) => $q->whereDate('created_at', '<=', $fechaHasta))
            ->orderByDesc('created_at');

        $registros = $query->paginate(20)->withQueryString()->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'accion' => $this->etiquetaAccion($log->action),
            'usuario' => $log->user?->name ?? 'Sistema',
            // Los registros anteriores a la instantánea no pueden atribuirse a
            // un rol histórico: el usuario pudo haber cambiado de rol después.
            'rol' => $log->actor_role ?? 'Sin rol registrado',
            'entidad' => $this->nombreEntidad($log),
            'cambios' => $this->cambios($log),
            'entidad_id' => $log->auditable_id,
            'enlace' => $this->enlaceEntidad($current_team, $log),
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'fecha' => $log->created_at?->toDateTimeString(),
        ]);

        $acciones = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(fn (string $valor) => ['valor' => $valor, 'etiqueta' => $this->etiquetaAccion($valor)])
            ->values();

        $usuarios = User::query()
            ->whereHas('auditLogs')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $roles = AuditLog::query()
            ->whereNotNull('actor_role')
            ->distinct()
            ->orderBy('actor_role')
            ->pluck('actor_role');

        return Inertia::render('gerente/auditoria/index', [
            'registros' => $registros,
            'filters' => [
                'accion' => $accion,
                'user_id' => $userId,
                'rol' => $rol,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
            'acciones' => $acciones,
            'usuarios' => $usuarios,
            'roles' => $roles,
            'kpis' => [
                'totalRegistros' => AuditLog::count(),
                'registrosHoy' => AuditLog::whereDate('created_at', today())->count(),
            ],
        ]);
    }

    private function etiquetaAccion(string $accion): string
    {
        return [
            'venta.creada' => 'Venta creada',
            'venta.borrador_editado' => 'Borrador de venta actualizado',
            'venta.editada' => 'Venta actualizada',
            'venta.anulada' => 'Venta anulada',
            'venta.nota_venta_confirmada' => 'Nota de venta confirmada',
            'venta.extintor_cambiado' => 'Extintor vendido reemplazado',
            'stock.ajuste' => 'Ajuste de inventario registrado',
            'cobro.anulado' => 'Cobro anulado',
            'cobranza.nota_aplicada' => 'Nota aplicada al saldo',
            'detraccion.acreditada' => 'Detracción acreditada',
            'cotizacion.editada' => 'Cotización actualizada',
            'orden.recojo_confirmado' => 'Recojo confirmado',
            'orden.recepcion_confirmada' => 'Recepción en planta confirmada',
            'orden.entregada' => 'Entrega confirmada',
            'orden.entrega_asignada' => 'Entrega asignada',
            'orden.anulada' => 'Orden anulada',
            'orden.cerrada' => 'Orden cerrada',
            'checklist.completado' => 'Checklist completado',
            'deficiencia.detectada' => 'Falla detectada',
            'deficiencia.reparada' => 'Falla reparada',
            'deficiencia.cotizada' => 'Falla cotizada',
            'deficiencia.autorizacion' => 'Autorización de falla registrada',
            'instalacion.registrada' => 'Instalación registrada',
            'certificado.emitido' => 'Certificado emitido',
            'certificado.corregido' => 'Certificado corregido',
            'equipo.no_apto' => 'Equipo marcado como no apto',
            'cliente.actualizado' => 'Cliente actualizado',
            'producto.creado' => 'Producto creado',
            'producto.actualizado' => 'Producto actualizado',
            'producto.activado' => 'Producto activado',
            'producto.desactivado' => 'Producto desactivado',
            'servicio.creado' => 'Servicio creado',
            'servicio.actualizado' => 'Servicio actualizado',
            'servicio.activado' => 'Servicio activado',
            'servicio.desactivado' => 'Servicio desactivado',
            'caja.salida' => 'Salida de dinero de caja',
            'caja.cierre_con_diferencia' => 'Caja cerrada con diferencia',
            'usuario.creado' => 'Usuario creado',
            'usuario.rol_actualizado' => 'Rol de usuario actualizado',
            'usuario.sede_actualizada' => 'Sede de usuario actualizada',
            'sede.creada' => 'Sede creada',
            'sede.actualizada' => 'Sede actualizada',
            'categoria.creada' => 'Categoría creada',
            'categoria.actualizada' => 'Categoría actualizada',
            'categoria.eliminada' => 'Categoría eliminada',
            'configuracion.empresa_actualizada' => 'Configuración de empresa actualizada',
        ][$accion] ?? ucfirst(str_replace(['.', '_'], ' ', $accion));
    }

    /** @return array{url: string, etiqueta: string}|null */
    private function enlaceEntidad(Team $currentTeam, AuditLog $log): ?array
    {
        if ($log->auditable_id === null) {
            return null;
        }

        return match ($log->auditable_type) {
            Sale::class => [
                'url' => route('gerente.consulta.venta', ['current_team' => $currentTeam, 'sale' => $log->auditable_id]),
                'etiqueta' => 'Ver venta',
            ],
            ServiceOrder::class => [
                'url' => route('gerente.consulta.orden', ['current_team' => $currentTeam, 'service_order' => $log->auditable_id]),
                'etiqueta' => 'Ver orden',
            ],
            Client::class => [
                'url' => route('gerente.consulta.cliente', ['current_team' => $currentTeam, 'client' => $log->auditable_id]),
                'etiqueta' => 'Ver cliente',
            ],
            default => null,
        };
    }

    /** Nombre de los datos en palabras, para leer la auditoría sin columnas. */
    private const CAMPOS = [
        'nombre' => 'Nombre', 'codigo' => 'Código', 'precio_venta' => 'Precio de venta', 'stock_minimo' => 'Stock mínimo',
        'activo' => 'Activo', 'serializado' => 'Lleva serie', 'codigo_barras' => 'Código de barras', 'categoria' => 'Categoría',
        'unidad_medida' => 'Unidad', 'descripcion' => 'Descripción', 'agente' => 'Agente', 'capacidad' => 'Capacidad',
        'tipo_afectacion_igv' => 'IGV', 'codigo_detraccion' => 'Detracción', 'certificate_type_id' => 'Certificado',
        'peso_kg' => 'Peso (kg)', 'estado' => 'Estado', 'monto' => 'Monto', 'motivo' => 'Motivo', 'total' => 'Total',
        'tecnico_id' => 'Técnico', 'sede_id' => 'Sede', 'client_id' => 'Cliente', 'role' => 'Rol', 'email' => 'Correo',
        'direccion' => 'Dirección', 'referencia' => 'Referencia', 'revision' => 'Revisión', 'numero' => 'Número',
        'esperado' => 'Esperado', 'contado' => 'Contado', 'diferencia' => 'Diferencia', 'observacion' => 'Observación',
    ];

    /** Qué es lo auditado, por su código o número cuando lo tiene. */
    private function nombreEntidad(AuditLog $log): ?string
    {
        if ($log->auditable_type === null) {
            return null;
        }

        $tipos = [
            'Product' => 'Producto', 'Service' => 'Servicio', 'Sale' => 'Venta', 'ServiceOrder' => 'Orden',
            'Client' => 'Cliente', 'Certificate' => 'Certificado', 'Quote' => 'Cotización', 'Sede' => 'Sede',
            'User' => 'Usuario', 'CashRegister' => 'Caja', 'CompanySetting' => 'Datos de la empresa',
            'InventoryTransfer' => 'Traslado', 'Deficiency' => 'Falla',
        ];
        $base = class_basename($log->auditable_type);
        $tipo = $tipos[$base] ?? $base;
        $modelo = $log->auditable;
        $identificador = $modelo?->getAttribute('codigo') ?? $modelo?->getAttribute('numero') ?? $modelo?->getAttribute('numero_interno');

        if ($base === 'CompanySetting') {
            return $tipo;
        }

        return $identificador ? "{$tipo} {$identificador}" : "{$tipo} #{$log->auditable_id}";
    }

    /**
     * Cada dato que cambió, con su valor anterior y el nuevo en palabras.
     *
     * @return array<int, array{campo: string, antes: string, despues: string}>
     */
    private function cambios(AuditLog $log): array
    {
        $antes = $log->old_values ?? [];
        $despues = $log->new_values ?? [];

        return collect(array_keys($antes + $despues))
            ->map(fn (string $campo) => [
                'campo' => self::CAMPOS[$campo] ?? ucfirst(str_replace('_', ' ', $campo)),
                'antes' => $this->valorLegible($antes[$campo] ?? null),
                'despues' => $this->valorLegible($despues[$campo] ?? null),
            ])
            ->values()
            ->all();
    }

    private function valorLegible(mixed $valor): string
    {
        return match (true) {
            $valor === null || $valor === '' => '—',
            is_bool($valor) => $valor ? 'Sí' : 'No',
            is_array($valor) => collect($valor)->map(fn ($v, $k) => (is_int($k) ? '' : str_replace('_', ' ', (string) $k).': ').$this->valorLegible($v))->implode(', '),
            default => (string) $valor,
        };
    }
}
