<?php

namespace App\Http\Controllers\Gerente;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateUnit;
use App\Models\ClientRetentionScore;
use App\Models\ElectronicDocument;
use App\Models\Installment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEvent;
use App\Models\Team;
use App\Models\User;
use App\Services\Cobranzas\CarteraDeCobranzas;
use App\Services\SaludDelSistema;
use App\Services\Tecnico\PasoDelTaller;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Team $current_team, Request $request, SaludDelSistema $saludDelSistema): Response
    {
        return Inertia::render('gerente/dashboard', [
            ...$this->datosMensuales($request),
            'sistema' => $saludDelSistema->resumen(),
        ]);
    }

    public function detalle(Team $current_team, Request $request): Response
    {
        $tipo = $request->string('tipo')->toString();
        abort_unless(in_array($tipo, ['ventas', 'cobros', 'ordenes', 'recompra', 'cotizaciones', 'sunat', 'sunat_vencidos', 'vendedores', 'servicios'], true), 404);
        $paso = $request->string('paso')->toString() ?: null;
        $tramo = in_array($request->integer('tramo'), [30, 60, 90], true) ? $request->integer('tramo') : null;
        $mes = $this->mesDelReporte($request->input('mes'), now()->startOfMonth());
        $sede = $request->integer('sede') ?: null;
        $filtrar = fn (Builder $query) => $query->when($sede, fn (Builder $query) => $query->where('sede_id', $sede));

        $rows = match ($tipo) {
            'ventas', 'vendedores' => Sale::query()->where('estado', 'confirmada')->whereBetween('fecha', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()])->tap($filtrar)->with('client:id,razon_social')->latest('fecha')->get()->map(fn (Sale $sale) => ['titulo' => $sale->numero_interno, 'cliente' => $sale->client->razon_social, 'estado' => $sale->estado, 'fecha' => $sale->fecha->toDateString(), 'url' => route('gerente.consulta.venta', ['current_team' => $current_team, 'sale' => $sale])]),
            'cobros' => SalePayment::query()->whereBetween('fecha', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()])->whereHas('sale', fn (Builder $sale) => $sale->tap($filtrar))->with('sale.client:id,razon_social')->latest('fecha')->get()->map(fn (SalePayment $pago) => ['titulo' => 'Cobro S/ '.number_format((float) $pago->monto, 2), 'cliente' => $pago->sale->client->razon_social, 'estado' => $pago->forma_pago, 'fecha' => $pago->fecha->toDateString(), 'url' => route('gerente.consulta.venta', ['current_team' => $current_team, 'sale' => $pago->sale])]),
            'cotizaciones' => Quote::query()->whereIn('estado', ['emitida', 'enviada'])->whereBetween('fecha', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()])->tap($filtrar)->with('client:id,razon_social')->latest('fecha')->get()->map(fn (Quote $quote) => ['titulo' => $quote->numero, 'cliente' => $quote->client->razon_social, 'estado' => $quote->estado, 'fecha' => $quote->fecha->toDateString(), 'url' => route('gerente.consulta.cliente', ['current_team' => $current_team, 'client' => $quote->client])]),
            'ordenes' => ServiceOrder::query()->whereNotIn('estado', ['entregado', 'cerrado', 'anulada'])->tap($filtrar)->with('client:id,razon_social')->latest('fecha')->get()->map(fn (ServiceOrder $orden) => ['paso' => PasoDelTaller::de($orden)['clave'], 'titulo' => $orden->codigo, 'cliente' => $orden->client->razon_social, 'estado' => $orden->estado, 'fecha' => $orden->fecha->toDateString(), 'url' => route('gerente.consulta.orden', ['current_team' => $current_team, 'service_order' => $orden])]),
            'sunat' => ElectronicDocument::query()->whereIn('sunat_estado', ['rechazado', 'excepcion', 'error'])->whereHas('sale', fn (Builder $sale) => $sale->tap($filtrar))->with('sale.client:id,razon_social')->latest('updated_at')->get()->map(fn (ElectronicDocument $documento) => ['titulo' => $documento->numero ?? $documento->tipo, 'cliente' => $documento->sale->client->razon_social, 'estado' => $documento->sunat_estado, 'fecha' => $documento->updated_at->toDateString(), 'url' => route('gerente.consulta.venta', ['current_team' => $current_team, 'sale' => $documento->sale])]),
            'sunat_vencidos' => ElectronicDocument::query()->vencidosSunat()->whereHas('sale', fn (Builder $sale) => $sale->tap($filtrar))->with('sale.client:id,razon_social')->latest('updated_at')->get()->map(fn (ElectronicDocument $documento) => ['titulo' => $documento->numero ?? $documento->tipo, 'cliente' => $documento->sale->client->razon_social, 'estado' => $documento->sunat_estado, 'fecha' => $documento->updated_at->toDateString(), 'url' => route('gerente.consulta.venta', ['current_team' => $current_team, 'sale' => $documento->sale])]),
            'recompra' => CertificateUnit::query()->whereHas('certificate', fn (Builder $certificado) => $certificado->whereNotIn('estado', ['anulado', 'vencido'])->whereBetween('fecha_vigencia_hasta', $tramo ? [today()->addDays($tramo - 29), today()->addDays($tramo)] : [today()->addDay(), today()->addDays(90)])->when($sede, fn (Builder $certificado) => $certificado->where(fn (Builder $origen) => $origen->whereHas('sale', fn (Builder $sale) => $sale->where('sede_id', $sede))->orWhereHas('serviceOrder', fn (Builder $orden) => $orden->where('sede_id', $sede)))))->with('certificate:id,client_id,fecha_vigencia_hasta')->get()->map(fn (CertificateUnit $unidad) => ['titulo' => $unidad->numero_serie_snapshot, 'cliente' => $unidad->certificate->client->razon_social, 'estado' => 'Vence '.$unidad->certificate->fecha_vigencia_hasta->toDateString(), 'fecha' => $unidad->certificate->fecha_vigencia_hasta->toDateString(), 'url' => route('gerente.consulta.cliente', ['current_team' => $current_team, 'client' => $unidad->certificate->client])]),
            'servicios' => SaleItem::query()->whereHas('sale', fn (Builder $sale) => $sale->where('estado', 'confirmada')->whereBetween('fecha', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()])->tap($filtrar))->whereHas('service')->with(['sale.client:id,razon_social', 'service:id,nombre'])->get()->map(fn (SaleItem $item) => ['titulo' => $item->service->nombre, 'cliente' => $item->sale->client->razon_social, 'estado' => $item->cantidad.' unidad(es)', 'fecha' => $item->sale->fecha->toDateString(), 'url' => route('gerente.consulta.venta', ['current_team' => $current_team, 'sale' => $item->sale])]),
        };

        // Órdenes de un paso: las mismas que cuenta el resumen.
        if ($tipo === 'ordenes' && $paso !== null) {
            $rows = $rows->filter(fn (array $fila) => $fila['paso'] === $paso);
        }

        $titulos = [
            'ventas' => 'Ventas del mes', 'vendedores' => 'Ventas del mes', 'cobros' => 'Cobros del mes',
            'cotizaciones' => 'Cotizaciones sin respuesta', 'ordenes' => 'Órdenes de servicio en curso',
            'sunat' => 'Comprobantes con error en SUNAT', 'sunat_vencidos' => 'Comprobantes vencidos en SUNAT',
            'recompra' => $tramo ? "Extintores que vencen en {$tramo} días" : 'Extintores que vencen en 90 días',
            'servicios' => 'Servicios vendidos del mes',
        ];

        return Inertia::render('gerente/dashboard-detalle', [
            'tipo' => $tipo,
            'titulo' => $titulos[$tipo],
            'rows' => $rows->map(fn (array $fila) => [...$fila, 'estado' => ucfirst(str_replace('_', ' ', (string) $fila['estado']))])->values(),
            'sede' => $sede,
            'mes' => $mes->format('Y-m'),
        ]);
    }

    /** @return array<string, mixed> */
    public function datosMensuales(Request $request): array
    {
        $mes = $this->mesDelReporte($request->input('mes'), now()->startOfMonth());
        $inicio = $mes->copy()->startOfMonth();
        $fin = $mes->copy()->endOfMonth();
        $inicioAnterior = $inicio->copy()->subMonthNoOverflow()->startOfMonth();
        $finAnterior = $inicioAnterior->copy()->endOfMonth();

        // Sedes de venta: las tiendas (cada una con su almacén). Una sede
        // mixta que surte a una tienda es su almacén, no otra sede de venta.
        $almacenesDeTiendas = Sede::query()->whereNotNull('almacen_id')->pluck('almacen_id');
        $sedes = Sede::query()->where('activo', true)->whereIn('tipo', ['tienda', 'mixta'])
            ->whereNotIn('id', $almacenesDeTiendas)
            ->orderBy('nombre')->get(['id', 'nombre', 'almacen_id']);
        $hayComparacionSedes = $sedes->count() > 1;
        $sedeSeleccionada = $hayComparacionSedes ? $sedes->firstWhere('id', $request->integer('sede')) : null;
        $sedeIds = $sedeSeleccionada === null ? null : $this->idsDeSede($sedeSeleccionada);
        $actual = $this->metricasDelPeriodo($inicio, $fin, $sedeIds);
        $anterior = $this->metricasDelPeriodo($inicioAnterior, $finAnterior, $sedeIds);

        $metricas = [
            $this->metrica('ventasNetas', 'Ventas netas', $actual['ventasNetas'], $anterior['ventasNetas'], 'moneda'),
            $this->metrica('cobrado', 'Cobrado', $actual['cobrado'], $anterior['cobrado'], 'moneda'),
            $this->metrica('ticketPromedio', 'Ticket promedio', $actual['ticketPromedio'], $anterior['ticketPromedio'], 'moneda'),
            $this->metrica('conversionCotizaciones', 'Conversión de cotizaciones', $actual['conversionCotizaciones'], $anterior['conversionCotizaciones'], 'porcentaje'),
            $this->metrica('diasPromedioCobro', 'Días promedio de cobro', $actual['diasPromedioCobro'], $anterior['diasPromedioCobro'], 'dias', 'desde la fecha de venta hasta el pago'),
            $this->metrica('diasPromedioServicio', 'Días promedio de orden', $actual['diasPromedioServicio'], $anterior['diasPromedioServicio'], 'dias', 'desde la recepción hasta la entrega'),
            $this->metrica('tasaRenovacion', 'Tasa de renovación', $actual['tasaRenovacion'], $anterior['tasaRenovacion'], 'porcentaje', 'certificados vencidos cuyo cliente volvió a atenderse'),
            ['clave' => 'margenBruto', 'etiqueta' => 'Margen bruto', 'valor' => $actual['margenBruto'], 'anterior' => $anterior['margenBruto'], 'variacion' => $this->variacion($actual['margenBruto'], $anterior['margenBruto']), 'formato' => 'moneda', 'detalle' => $actual['margenBrutoDetalle'], 'disponible' => $actual['margenBrutoDisponible'], 'productosSinCosto' => $actual['productosSinCosto']],
        ];

        $comparacionSedes = $hayComparacionSedes ? $sedes->map(function (Sede $sede) use ($inicio, $fin): array {
            $datos = $this->metricasDelPeriodo($inicio, $fin, $this->idsDeSede($sede));

            return ['id' => $sede->id, 'nombre' => $sede->nombre, 'ventasNetas' => $datos['ventasNetas'], 'ticketPromedio' => $datos['ticketPromedio'], 'conversionCotizaciones' => $datos['conversionCotizaciones'], 'cobrado' => $datos['cobrado']];
        })->values() : collect();

        return [
            'mes' => $mes->format('Y-m'),
            'mesAnterior' => $inicioAnterior->format('Y-m'),
            'periodo' => $mes->translatedFormat('F Y'),
            'fechaDesde' => $inicio->toDateString(),
            'fechaHasta' => $fin->toDateString(),
            'metricas' => $metricas,
            'metrics' => ['ventasMes' => $actual['ventasNetas'], 'ventasMesAnterior' => $anterior['ventasNetas'], 'montoCobrado' => $actual['cobrado'], 'montoCobradoMesAnterior' => $anterior['cobrado'], 'vencidoPorCobrar' => $actual['vencidoPorCobrar'], 'cotizacionesPendientes' => $actual['cotizacionesPendientes'], 'ordenesEnProceso' => $actual['ordenesEnProceso'], 'stockCritico' => $actual['stockCritico'], 'documentosSunatError' => $actual['documentosSunatError'], 'plazoSunat' => $actual['plazoSunat']],
            'sedes' => $hayComparacionSedes ? $sedes->map(fn (Sede $sede) => ['id' => $sede->id, 'nombre' => $sede->nombre])->values() : collect(),
            'sedeSeleccionada' => $sedeSeleccionada?->id,
            'sedeNombre' => $sedeSeleccionada->nombre ?? 'Toda la empresa',
            'mostrarComparacionSedes' => $hayComparacionSedes,
            'comparacionSedes' => $comparacionSedes,
            'clientesConDeuda' => $actual['clientesConDeuda'],
            'ordenesPorPaso' => $actual['ordenesPorPaso'],
            'recompra' => $actual['recompra'],
            'recompraBase' => 'Extintores de certificados vigentes que vencen en cada tramo; monto estimado con el menor precio vigente de recarga o mantenimiento del catálogo.',
            'ventasPorVendedor' => $actual['ventasPorVendedor'],
            'serviciosDelMes' => $actual['serviciosDelMes'],
            'serviciosMasVendidos' => $actual['serviciosMasVendidos'],
            'reporteInventario' => ReportController::inventarioResumen($sedeSeleccionada?->id),
            'iaRecompra' => $this->iaRecompra(),
            'kpisProyecto' => self::kpisDelProyecto($inicio, $fin),
            'graficos' => $this->graficos($inicio, $fin, $sedeIds, $comparacionSedes, $actual['recompra']),
        ];
    }

    /**
     * @param  list<int>|null  $sedeIds
     * @param  Collection<int, array<string, mixed>>  $sedes
     * @param  Collection<int, array{dias: int, cantidad: int}>  $recompra
     * @return array<string, mixed>
     */
    private function graficos(CarbonInterface $inicio, CarbonInterface $fin, ?array $sedeIds, Collection $sedes, Collection $recompra): array
    {
        $filtrar = fn ($query) => $query->when($sedeIds !== null, fn ($consulta) => $consulta->whereIn('sede_id', $sedeIds));
        $cotizaciones = Quote::query()->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->tap($filtrar);
        $cuotas = Installment::query()->whereIn('estado', CarteraDeCobranzas::DEBEN)
            ->whereHas('sale', fn ($query) => $query->where('estado', 'confirmada')->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->tap($filtrar));
        $ordenes = ServiceOrder::query()->where('estado', '!=', 'anulada')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->tap($filtrar);
        $pagos = SalePayment::query()->whereHas('sale', fn ($query) => $query->where('estado', 'confirmada')->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->tap($filtrar))->sum('monto');
        $devoluciones = SaleRefund::query()->whereHas('sale', fn ($query) => $query->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->tap($filtrar))->sum('monto');
        $abiertas = (clone $ordenes)->whereNotIn('estado', ['entregado', 'cerrado']);
        $servicios = SaleItem::query()->whereNotNull('service_id')
            ->whereHas('sale', fn ($query) => $query->where('estado', 'confirmada')->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->tap($filtrar))
            ->with('service.tipoDeServicio')->get();

        return [
            'ventas' => collect(range(5, 0))->map(function (int $atras) use ($inicio, $sedeIds): array {
                $mes = $inicio->copy()->subMonthsNoOverflow($atras)->startOfMonth();

                return ['nombre' => $mes->format('m/Y'), 'valor' => $this->ventasNetas($mes, $mes->copy()->endOfMonth(), sedeIds: $sedeIds)];
            }),
            'conversion' => [
                ['nombre' => 'Enviadas', 'valor' => (clone $cotizaciones)->whereIn('estado', ['enviada', 'aceptada', 'convertida', 'rechazada', 'vencida'])->count()],
                ['nombre' => 'Aceptadas', 'valor' => (clone $cotizaciones)->whereIn('estado', ['aceptada', 'convertida'])->count()],
                ['nombre' => 'Vendidas', 'valor' => (clone $cotizaciones)->whereHas('sale', fn ($query) => $query->where('estado', 'confirmada'))->count()],
            ],
            'cobranza' => [
                ['nombre' => 'Cobrado', 'valor' => max(0, round((float) $pagos - (float) $devoluciones, 2))],
                ['nombre' => 'Pendiente', 'valor' => max(0, CarteraDeCobranzas::saldo((clone $cuotas)->whereDate('fecha_vencimiento', '>=', today())))],
                ['nombre' => 'Vencido', 'valor' => max(0, CarteraDeCobranzas::saldo((clone $cuotas)->whereDate('fecha_vencimiento', '<', today())))],
            ],
            'sedes' => $sedes->map(fn (array $sede) => ['nombre' => $sede['nombre'], 'valor' => $sede['ventasNetas']])->values(),
            'servicios' => $servicios->groupBy(fn (SaleItem $item) => $item->service?->tipoDeServicio?->nombre ?? 'Sin tipo')
                ->map(fn ($items, string $nombre) => ['nombre' => $nombre, 'valor' => (int) $items->sum('cantidad')])->values(),
            'ordenes' => [
                ['nombre' => 'Abiertas', 'valor' => (clone $abiertas)->whereDate('fecha', '>=', today())->count(), 'secundario' => (clone $abiertas)->whereDate('fecha', '<', today())->count()],
                ['nombre' => 'Terminadas', 'valor' => (clone $ordenes)->whereIn('estado', ['entregado', 'cerrado'])->count(), 'secundario' => 0],
            ],
            'vencimientos' => $recompra->map(fn (array $tramo) => ['nombre' => ($tramo['dias'] - 29).'–'.$tramo['dias'].' días', 'valor' => $tramo['cantidad']])->values(),
        ];
    }

    /**
     * @param  list<int>|null  $sedeIds
     * @return array<string, mixed>
     */
    private function metricasDelPeriodo(CarbonInterface $inicio, CarbonInterface $fin, ?array $sedeIds): array
    {
        $filtrarSede = static fn (Builder $query) => $query->when($sedeIds !== null, fn (Builder $query) => $query->whereIn('sede_id', $sedeIds));
        $ventasNetas = $this->ventasNetas($inicio, $fin, sedeIds: $sedeIds);
        $ventasConfirmadas = Sale::query()->where('estado', 'confirmada')->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->tap($filtrarSede)->count();
        $cobrado = $this->cobradoNeto($inicio, $fin, $sedeIds);

        $cotizaciones = Quote::query()->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->whereNotIn('estado', ['borrador', 'anulada'])->tap($filtrarSede);
        $cotizacionesTotales = (int) (clone $cotizaciones)->count();
        $cotizacionesGanadas = (int) (clone $cotizaciones)->where(fn (Builder $query) => $query->whereIn('estado', ['convertida', 'aceptada'])->orWhereHas('sale', fn (Builder $sale) => $sale->where('estado', 'confirmada')))->count();

        $pagos = SalePayment::query()->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->whereHas('sale', fn (Builder $sale) => $sale->tap($filtrarSede))->with('sale:id,fecha')->get(['id', 'sale_id', 'fecha']);
        $diasCobro = $pagos->map(fn (SalePayment $pago) => max(0, (int) $pago->sale->fecha->diffInDays($pago->fecha)));

        $ordenesDelMes = ServiceOrderEvent::query()->where('tipo', 'entrega_registrada')->whereBetween('created_at', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()])->whereHas('serviceOrder', fn (Builder $orden) => $orden->tap($filtrarSede))->pluck('service_order_id')->unique();
        $eventosDeOrden = ServiceOrderEvent::query()->whereIn('service_order_id', $ordenesDelMes)->whereIn('tipo', ['recibida', 'entrega_registrada'])->get(['service_order_id', 'tipo', 'created_at']);
        $diasServicio = $eventosDeOrden->groupBy('service_order_id')->map(function (Collection $eventos): ?int {
            $recepcion = $eventos->firstWhere('tipo', 'recibida')?->created_at;
            $entrega = $eventos->firstWhere('tipo', 'entrega_registrada')?->created_at;

            return $recepcion && $entrega ? max(0, (int) $recepcion->diffInDays($entrega)) : null;
        })->filter()->values();

        $certificados = Certificate::query()->whereNotIn('estado', ['anulado'])->whereBetween('fecha_vigencia_hasta', [$inicio->toDateString(), $fin->toDateString()])->when($sedeIds !== null, fn (Builder $query) => $query->where(fn (Builder $origen) => $origen->whereHas('sale', fn (Builder $sale) => $sale->whereIn('sede_id', $sedeIds))->orWhereHas('serviceOrder', fn (Builder $orden) => $orden->whereIn('sede_id', $sedeIds))))->get(['id', 'client_id', 'fecha_vigencia_hasta']);
        $clientesCertificados = $certificados->pluck('client_id')->unique()->count();
        $clientesRenovados = $certificados->pluck('client_id')->unique()->filter(function ($clientId) use ($certificados): bool {
            $fechaVencimiento = $certificados->where('client_id', $clientId)->max('fecha_vigencia_hasta');

            return Sale::query()->where('client_id', $clientId)->where('estado', 'confirmada')->whereDate('fecha', '>', $fechaVencimiento)->exists()
                || ServiceOrder::query()->where('client_id', $clientId)->whereNotIn('estado', ['anulada'])->whereDate('fecha', '>', $fechaVencimiento)->exists();
        })->count();

        $margen = $this->margenBruto($inicio, $fin, $sedeIds, $ventasNetas);
        $deuda = fn () => Installment::query()->whereIn('installments.estado', CarteraDeCobranzas::DEBEN)->whereHas('sale', fn (Builder $sale) => $sale->where('estado', 'confirmada')->tap($filtrarSede));
        $clientesConDeuda = $deuda()->whereDate('fecha_vencimiento', '<', today())->with(['sale.client:id,razon_social', 'payments:id,installment_id,monto'])->get()->groupBy('sale.client_id')->map(function ($cuotas): array {
            return ['cliente_id' => $cuotas->first()->sale->client->id, 'cliente' => $cuotas->first()->sale->client->razon_social, 'saldo' => round($cuotas->sum(fn (Installment $cuota) => $cuota->saldo()), 2), 'fecha_mas_antigua' => $cuotas->min('fecha_vencimiento')->toDateString()];
        })->sortByDesc('saldo')->take(5)->values();
        $ordenesPendientes = ServiceOrder::query()->whereNotIn('estado', ['entregado', 'cerrado', 'anulada'])->tap($filtrarSede)->where(fn (Builder $query) => $query->whereDate('fecha', '<', today())->orWhereIn('estado', ['esperando_autorizacion', 'pendiente_datos']))->with(['equipments', 'checklists:id,service_order_id,equipment_id', 'deficiencies:id,service_order_id,estado'])->get();

        $serviciosDelMes = SaleItem::query()->whereHas('sale', fn (Builder $sale) => $sale->where('estado', 'confirmada')->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->tap($filtrarSede))->whereNotNull('service_id')->with('service:id,nombre')->get()->map(fn (SaleItem $item) => ['item' => $item, 'tipo' => match (true) {
            str_contains(mb_strtoupper($item->service->nombre), 'RECARGA') => 'recarga',
            str_contains(mb_strtoupper($item->service->nombre), 'HIDROST') => 'prueba_hidrostatica',
            str_contains(mb_strtoupper($item->service->nombre), 'MANTENIMIENTO') => 'mantenimiento',
            default => null,
        }])->filter(fn (array $fila) => $fila['tipo'] !== null)->groupBy('tipo')->map(fn ($filas, string $tipo) => ['tipo' => $tipo, 'cantidad' => (int) $filas->sum(fn (array $fila) => $fila['item']->cantidad)])->values();
        $serviciosMasVendidos = SaleItem::query()->whereHas('sale', fn (Builder $sale) => $sale->where('estado', 'confirmada')->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->tap($filtrarSede))->whereNotNull('service_id')->with('service:id,nombre')->get()->groupBy(fn (SaleItem $item) => $item->service->nombre)->map(fn ($items, string $nombre) => ['servicio' => $nombre, 'cantidad' => (int) $items->sum('cantidad'), 'monto' => round((float) $items->sum('subtotal'), 2)])->sortByDesc('cantidad')->values();
        // Neto por vendedor, igual que las ventas netas: originales del mes
        // menos notas de crédito y más notas de débito aceptadas del mes.
        $brutoPorVendedor = Sale::query()->where('sales.estado', 'confirmada')->whereBetween('sales.fecha', [$inicio->toDateString(), $fin->toDateString()])->when($sedeIds !== null, fn (Builder $query) => $query->whereIn('sales.sede_id', $sedeIds))->groupBy('sales.vendedor_id')->selectRaw('sales.vendedor_id, SUM(sales.total) as total')->pluck('total', 'vendedor_id');
        $notasPorVendedor = ElectronicDocument::query()->join('sales', 'sales.id', '=', 'electronic_documents.sale_id')->whereIn('electronic_documents.tipo', ['nota_credito', 'nota_debito'])->whereIn('electronic_documents.sunat_estado', ['aceptado', 'observado'])->whereBetween('electronic_documents.fecha_emision', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()])->when($sedeIds !== null, fn (Builder $query) => $query->whereIn('sales.sede_id', $sedeIds))->groupBy('sales.vendedor_id')->selectRaw("sales.vendedor_id, SUM(CASE WHEN electronic_documents.tipo = 'nota_debito' THEN electronic_documents.importe ELSE -electronic_documents.importe END) as neto")->pluck('neto', 'vendedor_id');
        $nombres = User::query()->whereIn('id', $brutoPorVendedor->keys()->merge($notasPorVendedor->keys())->unique())->pluck('name', 'id');
        $ventasPorVendedor = $nombres->map(fn (string $nombre, int $id) => ['vendedor' => $nombre, 'monto' => round((float) $brutoPorVendedor->get($id, 0) + (float) $notasPorVendedor->get($id, 0), 2)])->sortByDesc('monto')->values();

        $unidadesPorRenovar = CertificateUnit::query()->whereHas('certificate', fn (Builder $certificado) => $certificado->whereNotIn('estado', ['anulado', 'vencido'])->whereNotNull('fecha_vigencia_hasta')->whereBetween('fecha_vigencia_hasta', [today()->addDay(), today()->addDays(90)])->when($sedeIds !== null, fn (Builder $certificado) => $certificado->where(fn (Builder $origen) => $origen->whereHas('sale', fn (Builder $sale) => $sale->whereIn('sede_id', $sedeIds))->orWhereHas('serviceOrder', fn (Builder $orden) => $orden->whereIn('sede_id', $sedeIds)))))->with('certificate:id,client_id,fecha_vigencia_hasta')->get(['id', 'certificate_id']);
        $precioRenovacion = (float) Service::query()->where('activo', true)->where(fn (Builder $query) => $query->where('nombre', 'like', '%RECARGA%')->orWhere('nombre', 'like', '%MANTENIMIENTO%'))->min('precio_venta');
        $recompra = collect([30, 60, 90])->map(function (int $dias, int $indice) use ($unidadesPorRenovar, $precioRenovacion): array {
            $desde = $indice * 30 + 1;
            $hasta = ($indice + 1) * 30;
            $filas = $unidadesPorRenovar->filter(fn (CertificateUnit $unidad) => today()->diffInDays($unidad->certificate->fecha_vigencia_hasta) >= $desde && today()->diffInDays($unidad->certificate->fecha_vigencia_hasta) <= $hasta);

            return ['dias' => $dias, 'cantidad' => $filas->count(), 'clientes' => $filas->pluck('certificate.client_id')->unique()->count(), 'monto_estimado' => round($filas->count() * $precioRenovacion, 2)];
        })->values();
        $plazoSunat = ElectronicDocument::query()->when($sedeIds !== null, fn (Builder $query) => $query->whereHas('sale', fn (Builder $sale) => $sale->whereIn('sede_id', $sedeIds)));

        return [
            'ventasNetas' => $ventasNetas,
            'cobrado' => $cobrado,
            'ticketPromedio' => $ventasConfirmadas > 0 ? round($ventasNetas / $ventasConfirmadas, 2) : null,
            'conversionCotizaciones' => $cotizacionesTotales > 0 ? round(($cotizacionesGanadas / $cotizacionesTotales) * 100, 1) : null,
            'diasPromedioCobro' => $diasCobro->isNotEmpty() ? round((float) $diasCobro->avg(), 1) : null,
            'diasPromedioServicio' => $diasServicio->isNotEmpty() ? round((float) $diasServicio->avg(), 1) : null,
            'tasaRenovacion' => $clientesCertificados > 0 ? round(($clientesRenovados / $clientesCertificados) * 100, 1) : null,
            'margenBruto' => $margen['monto'],
            'margenBrutoDetalle' => $margen['detalle'],
            'margenBrutoDisponible' => $margen['disponible'],
            'productosSinCosto' => $margen['productosSinCosto'],
            'vencidoPorCobrar' => round(CarteraDeCobranzas::saldo($deuda()->whereDate('fecha_vencimiento', '<', today())), 2),
            'cotizacionesPendientes' => Quote::query()->whereIn('estado', ['emitida', 'enviada'])->whereDate('fecha', '<=', today()->subDays(7))->tap($filtrarSede)->count(),
            'ordenesEnProceso' => ServiceOrder::query()->whereNotIn('estado', ['entregado', 'cerrado', 'anulada'])->tap($filtrarSede)->count(),
            'stockCritico' => Product::query()->bajoMinimo($sedeIds)->count(),
            'documentosSunatError' => ElectronicDocument::query()->whereIn('sunat_estado', ['rechazado', 'excepcion', 'error'])->when($sedeIds !== null, fn (Builder $query) => $query->whereHas('sale', fn (Builder $sale) => $sale->whereIn('sede_id', $sedeIds)))->count(),
            'plazoSunat' => ['por_vencer' => (clone $plazoSunat)->porVencerSunat()->count(), 'vencidos' => (clone $plazoSunat)->vencidosSunat()->count()],
            'clientesConDeuda' => $clientesConDeuda,
            'ordenesPorPaso' => $ordenesPendientes->map(fn (ServiceOrder $orden) => PasoDelTaller::de($orden)['clave'])->countBy()->map(fn (int $cantidad, string $paso) => ['paso' => $paso, 'cantidad' => $cantidad])->values(),
            'recompra' => $recompra,
            'ventasPorVendedor' => $ventasPorVendedor,
            'serviciosDelMes' => $serviciosDelMes,
            'serviciosMasVendidos' => $serviciosMasVendidos,
        ];
    }

    /**
     * @param  list<int>|null  $sedeIds
     * @return array{monto: float|null, disponible: bool, detalle: string, productosSinCosto: int}
     */
    private function margenBruto(CarbonInterface $inicio, CarbonInterface $fin, ?array $sedeIds, float $ventasNetas): array
    {
        $items = SaleItem::query()->whereNotNull('product_id')->whereHas('sale', fn (Builder $sale) => $sale->where('estado', 'confirmada')->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->when($sedeIds !== null, fn (Builder $venta) => $venta->whereIn('sede_id', $sedeIds)))->with('product:id,costo_promedio')->get(['id', 'product_id', 'cantidad', 'subtotal']);
        $sinCosto = $items->filter(fn (SaleItem $item) => $item->product?->costo_promedio === null)->count();
        $costo = $items->sum(fn (SaleItem $item) => $item->cantidad * (float) ($item->product->costo_promedio ?? 0));

        return [
            'monto' => $sinCosto === 0 ? round($ventasNetas - $costo, 2) : null,
            'disponible' => $sinCosto === 0,
            'detalle' => $sinCosto === 0 ? 'Ventas netas menos costo promedio de productos vendidos.' : "No calculado: {$sinCosto} línea(s) de productos vendidos no tienen costo registrado.",
            'productosSinCosto' => $sinCosto,
        ];
    }

    /** @return array{clave: string, etiqueta: string, valor: float|int|null, anterior: float|int|null, variacion: float|null, formato: string, detalle: string, disponible: bool} */
    private function metrica(string $clave, string $etiqueta, float|int|null $actual, float|int|null $anterior, string $formato, string $detalle = ''): array
    {
        return ['clave' => $clave, 'etiqueta' => $etiqueta, 'valor' => $actual, 'anterior' => $anterior, 'variacion' => $this->variacion($actual, $anterior), 'formato' => $formato, 'detalle' => $detalle, 'disponible' => $actual !== null];
    }

    /**
     * Lo que dice la IA de recompra (ml:score-clients, cada noche): cuántos
     * clientes tienen probabilidad alta, media o baja de volver a comprar y
     * los que conviene llamar primero.
     *
     * @return array{alta: int, media: int, baja: int, calculado: string|null, clientes: array<int, array{cliente_id: int, cliente: string, porcentaje: int, categoria: string, resumen: string}>}
     */
    private function iaRecompra(): array
    {
        $porCategoria = ClientRetentionScore::query()->selectRaw('categoria, COUNT(*) as total')->groupBy('categoria')->pluck('total', 'categoria');
        $clientes = ClientRetentionScore::query()
            ->whereIn('categoria', ['alta', 'media'])
            ->with('client:id,razon_social,nombre_comercial')
            ->orderByDesc('probabilidad')
            ->limit(5)
            ->get()
            ->map(fn (ClientRetentionScore $score) => [
                'cliente_id' => $score->client_id,
                'cliente' => $score->client->nombre_comercial ?: $score->client->razon_social,
                'porcentaje' => (int) round($score->probabilidad * 100),
                'categoria' => $score->categoria,
                'resumen' => (string) ($score->factores_json['resumen'] ?? ''),
            ])->values()->all();

        return [
            'alta' => (int) ($porCategoria['alta'] ?? 0),
            'media' => (int) ($porCategoria['media'] ?? 0),
            'baja' => (int) ($porCategoria['baja'] ?? 0),
            'calculado' => ClientRetentionScore::query()->latest('scored_at')->first()?->scored_at->toDateString(),
            'clientes' => $clientes,
        ];
    }

    /**
     * X7: los tres indicadores del proyecto, del mes.
     * - Registro de venta: de abrir el formulario a confirmarla (minutos).
     * - Cotización: de crearla a emitirla o enviarla (minutos).
     * - Clientes recuperados: cotizaciones nacidas de "Ofrecer recarga" que
     *   terminaron en una venta confirmada este mes.
     *
     * @return array{venta_minutos: float|null, ventas_medidas: int, cotizacion_minutos: float|null, cotizaciones_medidas: int, clientes_recuperados: int}
     */
    public static function kpisDelProyecto(CarbonInterface $inicio, CarbonInterface $fin): array
    {
        // Promedios calculados en la base (AVG de la duración en minutos):
        // no se cargan todas las ventas y cotizaciones del rango en memoria.
        $ventas = Sale::query()
            ->where('estado', 'confirmada')
            ->whereNotNull('registro_iniciado_at')
            ->whereBetween('confirmada_at', [$inicio, $fin])
            ->selectRaw('COUNT(*) as medidas')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, registro_iniciado_at, confirmada_at) / 60) as minutos')
            ->toBase()
            ->first();

        $cotizaciones = Quote::query()
            ->whereBetween('emitida_at', [$inicio, $fin])
            ->selectRaw('COUNT(*) as medidas')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, emitida_at) / 60) as minutos')
            ->toBase()
            ->first();

        $recuperados = Quote::query()
            ->whereNotNull('origen_alerta_equipment_id')
            ->whereHas('sale', fn ($query) => $query->where('estado', 'confirmada')->whereBetween('confirmada_at', [$inicio, $fin]))
            ->distinct()
            ->count('client_id');

        return [
            'venta_minutos' => (int) ($ventas->medidas ?? 0) > 0 ? round((float) $ventas->minutos, 1) : null,
            'ventas_medidas' => (int) ($ventas->medidas ?? 0),
            'cotizacion_minutos' => (int) ($cotizaciones->medidas ?? 0) > 0 ? round((float) $cotizaciones->minutos, 1) : null,
            'cotizaciones_medidas' => (int) ($cotizaciones->medidas ?? 0),
            'clientes_recuperados' => $recuperados,
        ];
    }

    private function variacion(float|int|null $actual, float|int|null $anterior): ?float
    {
        if ($actual === null || $anterior === null) {
            return null;
        }
        if ((float) $anterior === 0.0) {
            return (float) $actual === 0.0 ? 0.0 : ((float) $actual > 0 ? 100.0 : -100.0);
        }

        return round(((float) $actual - (float) $anterior) / abs((float) $anterior) * 100, 1);
    }

    /** @return list<int> */
    private function idsDeSede(Sede $sede): array
    {
        return array_values(array_unique(array_filter([(int) $sede->id, $sede->almacen_id === null ? null : (int) $sede->almacen_id])));
    }

    private function mesDelReporte(mixed $valor, CarbonInterface $porDefecto): CarbonInterface
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}$/', $valor) === 1 && checkdate((int) substr($valor, 5, 2), 1, (int) substr($valor, 0, 4))
            ? Carbon::createFromFormat('Y-m', $valor)->startOfMonth()
            : $porDefecto;
    }

    /** @param list<int>|null $sedeIds */
    private function cobradoNeto(CarbonInterface $inicio, CarbonInterface $fin, ?array $sedeIds): float
    {
        $pagos = SalePayment::query()->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->when($sedeIds !== null, fn (Builder $query) => $query->whereHas('sale', fn (Builder $sale) => $sale->whereIn('sede_id', $sedeIds)));
        $devoluciones = SaleRefund::query()->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->when($sedeIds !== null, fn (Builder $query) => $query->whereHas('sale', fn (Builder $sale) => $sale->whereIn('sede_id', $sedeIds)));

        return round((float) (clone $pagos)->sum('monto') - (float) (clone $devoluciones)->sum('monto'), 2);
    }

    /** @param list<int>|null $sedeIds */
    private function ventasNetas(CarbonInterface $desde, CarbonInterface $hasta, bool $soloFacturadas = false, ?array $sedeIds = null): float
    {
        $ventas = Sale::query()->where(fn (Builder $query) => $query->where('estado', 'confirmada')->orWhere(fn (Builder $anuladas) => $anuladas->where('estado', 'anulada')->whereHas('electronicDocuments', fn (Builder $notas) => $notas->where('tipo', 'nota_credito')->whereIn('sunat_estado', ['aceptado', 'observado']))))->when($soloFacturadas, fn (Builder $query) => $query->whereIn('comprobante_tipo', ['factura', 'boleta']))->when($sedeIds !== null, fn (Builder $query) => $query->whereIn('sede_id', $sedeIds));
        $bruto = (float) (clone $ventas)->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->sum('total');
        $credito = (float) ElectronicDocument::query()->whereIn('sale_id', (clone $ventas)->select('id'))->where('tipo', 'nota_credito')->whereIn('sunat_estado', ['aceptado', 'observado'])->whereBetween('fecha_emision', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])->sum('importe');
        $debito = (float) ElectronicDocument::query()->whereIn('sale_id', (clone $ventas)->select('id'))->where('tipo', 'nota_debito')->whereIn('sunat_estado', ['aceptado', 'observado'])->whereBetween('fecha_emision', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])->sum('importe');

        return round($bruto - $credito + $debito, 2);
    }
}
