<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Revisión diaria de que el sistema esté sano: los datos cuadran entre
 * tablas, hay respaldo reciente, las tareas automáticas corren y no hay
 * comprobantes atrasados. El resultado lo ve el Gerente en su panel.
 */
class SaludDelSistema
{
    public const CACHE_REVISION = 'sistema.salud_datos';

    public const CACHE_LATIDO = 'sistema.programador_latido';

    /**
     * Cada chequeo cuenta los registros que están mal (0 = todo bien).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const CHEQUEOS = [
        'ventas_sin_comprobante' => ['Facturas o boletas emitidas sin su comprobante electrónico', "select count(*) from sales s where s.estado='confirmada' and s.comprobante_tipo in ('factura','boleta') and not exists (select 1 from electronic_documents e where e.sale_id=s.id and e.tipo=s.comprobante_tipo)"],
        'unidades_vendidas_sin_venta' => ['Extintores marcados como vendidos que no están en ninguna venta', "select count(*) from inventory_units u where u.estado='vendido' and not exists (select 1 from sale_items i join sales s on s.id=i.sale_id where i.inventory_unit_id=u.id and s.estado in ('confirmada','borrador'))"],
        'unidades_disponibles_vendidas' => ['Extintores disponibles en stock que ya figuran en una venta', "select count(*) from inventory_units u where u.estado='disponible' and exists (select 1 from sale_items i join sales s on s.id=i.sale_id where i.inventory_unit_id=u.id and s.estado='confirmada')"],
        'stock_negativo' => ['Productos con stock negativo en algún almacén', 'select count(*) from (select product_id, sede_id from inventory_movements where inventory_unit_id is null group by product_id, sede_id having sum(cantidad) < 0) x'],
        'cuotas_no_suman' => ['Ventas a crédito cuyas cuotas no suman el total', "select count(*) from sales s where s.condicion_pago <> 'contado' and s.estado='confirmada' and abs(s.total - (select coalesce(sum(monto),0) from installments where sale_id=s.id)) > 0.05"],
        'cobros_mayores' => ['Ventas con cobros mayores a su total', 'select count(*) from sales s where (select coalesce(sum(monto),0) from sale_payments where sale_id=s.id and deleted_at is null) > s.total + 0.05'],
        'totales_distintos' => ['Ventas cuyo total no coincide con la suma de sus líneas', "select count(*) from sales s where s.estado='confirmada' and abs(s.total - (select coalesce(sum(subtotal),0) from sale_items where sale_id=s.id)) > 0.05"],
        'cliente_distinto' => ['Órdenes, certificados o ventas enlazados a un cliente distinto', 'select (select count(*) from service_orders o join sales s on s.id=o.sale_id where o.client_id<>s.client_id) + (select count(*) from certificates c join sales s on s.id=c.sale_id where c.client_id<>s.client_id) + (select count(*) from sales s join quotes q on q.id=s.quote_id where q.client_id<>s.client_id)'],
        'series_atrasadas' => ['Series de comprobantes con el contador por detrás del último emitido', 'select count(*) from document_series d where d.correlativo_actual < (select coalesce(max(correlativo),0) from electronic_documents e where e.serie=d.serie)'],
        'cajas_duplicadas' => ['Vendedores con más de una caja abierta', "select count(*) from (select vendedor_id from cash_registers where estado='abierto' group by vendedor_id having count(*) > 1) x"],
        'personal_sin_sede' => ['Trabajadores sin sede asignada', "select count(*) from users u join model_has_roles m on m.model_id=u.id join roles r on r.id=m.role_id where r.name in ('Vendedor','Almacen','TecnicoPlanta','TecnicoCampo') and u.sede_id is null"],
        'sunat_atrasados' => ['Comprobantes que debieron enviarse a SUNAT hace más de una hora', "select count(*) from electronic_documents where (sunat_estado='por_enviar' and enviar_desde < date_sub(now(), interval 1 hour)) or (sunat_estado='pendiente' and created_at < date_sub(now(), interval 1 hour))"],
        'sunat_rechazados' => ['Comprobantes rechazados por SUNAT o con error de envío', "select count(*) from electronic_documents where sunat_estado in ('rechazado','excepcion')"],
    ];

    /**
     * Corre todos los chequeos y guarda el resultado para el panel.
     *
     * @return array{revisado_at: string, problemas: list<array{clave: string, descripcion: string, cantidad: int}>, respaldo: array{ok: bool, fecha: string|null, detalle: string}}
     */
    public function revisar(): array
    {
        $problemas = [];
        foreach (self::CHEQUEOS as $clave => [$descripcion, $sql]) {
            $cantidad = (int) DB::scalar($sql);
            if ($cantidad > 0) {
                $problemas[] = ['clave' => $clave, 'descripcion' => $descripcion, 'cantidad' => $cantidad];
            }
        }

        $revision = ['revisado_at' => now()->toIso8601String(), 'problemas' => $problemas, 'respaldo' => $this->respaldo()];
        Cache::forever(self::CACHE_REVISION, $revision);

        return $revision;
    }

    /**
     * El último respaldo debe ser de las últimas 36 horas y contener las
     * tablas principales (no un archivo vacío o cortado).
     *
     * @return array{ok: bool, fecha: string|null, detalle: string}
     */
    public function respaldo(): array
    {
        $archivos = File::glob(storage_path('app/backups/bd_*.sql')) ?: [];
        usort($archivos, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
        $ultimo = $archivos[0] ?? null;

        if ($ultimo === null) {
            return ['ok' => false, 'fecha' => null, 'detalle' => 'No hay ningún respaldo de la base de datos.'];
        }

        $fecha = Carbon::createFromTimestamp((int) filemtime($ultimo), config('app.timezone'));
        $contenido = (string) file_get_contents($ultimo, length: 5_000_000);
        $completo = str_contains($contenido, 'CREATE TABLE `sales`') && str_contains($contenido, 'CREATE TABLE `clients`');

        return match (true) {
            ! $completo => ['ok' => false, 'fecha' => $fecha->toIso8601String(), 'detalle' => 'El último respaldo está incompleto: no contiene las tablas de ventas y clientes.'],
            $fecha->lt(now()->subHours(36)) => ['ok' => false, 'fecha' => $fecha->toIso8601String(), 'detalle' => 'El último respaldo tiene más de un día y medio: revisa la tarea automática.'],
            default => ['ok' => true, 'fecha' => $fecha->toIso8601String(), 'detalle' => 'Respaldo al día.'],
        };
    }

    /**
     * Resumen para el panel del Gerente.
     *
     * @return array{programadorActivo: bool, ultimoLatido: string|null, revisadoAt: string|null, problemas: list<array{clave: string, descripcion: string, cantidad: int}>, respaldo: array{ok: bool, fecha: string|null, detalle: string}|null}
     */
    public function resumen(): array
    {
        $latido = Cache::get(self::CACHE_LATIDO);
        $revision = Cache::get(self::CACHE_REVISION);
        $revision = is_array($revision) ? $revision : [];

        return [
            // El programador marca un latido cada minuto; sin él no corren
            // los envíos a SUNAT, los respaldos ni los recálculos nocturnos.
            'programadorActivo' => is_string($latido) && Carbon::parse($latido)->gt(now()->subMinutes(10)),
            'ultimoLatido' => is_string($latido) ? $latido : null,
            'revisadoAt' => $revision['revisado_at'] ?? null,
            'problemas' => array_values($revision['problemas'] ?? []),
            'respaldo' => $revision['respaldo'] ?? null,
        ];
    }
}
