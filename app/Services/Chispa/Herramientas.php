<?php

namespace App\Services\Chispa;

use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Installment;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Sede;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que Chispa puede hacer, por rol. Las consultas leen con los mismos
 * límites que las pantallas del usuario (su sede, su almacén). Las
 * "preparar_*" no guardan nada: dejan un borrador en la sesión y devuelven
 * un botón; la persona revisa la pantalla llenada y confirma.
 */
class Herramientas
{
    /** @var array<string, list<string>> */
    private const POR_ROL = [
        'Vendedor' => ['buscar_cliente', 'buscar_catalogo', 'preparar_venta', 'preparar_cotizacion'],
        'Almacen' => ['buscar_catalogo', 'buscar_serie', 'bajo_minimo', 'preparar_recepcion'],
        'Gerente' => ['resumen_hoy', 'ventas_periodo', 'deudores', 'cajas_con_diferencia', 'buscar_cliente', 'buscar_catalogo', 'buscar_serie', 'bajo_minimo', 'preparar_cambio_precio'],
    ];

    /** @return list<string> */
    public function nombresPara(User $user): array
    {
        $nombres = [];
        foreach (self::POR_ROL as $rol => $lista) {
            if ($user->hasRole($rol)) {
                $nombres = [...$nombres, ...$lista];
            }
        }

        return array_values(array_unique($nombres));
    }

    /**
     * Definiciones en formato de "tools" (compatible con OpenAI y DeepSeek).
     *
     * @return list<array<string, mixed>>
     */
    public function definiciones(User $user): array
    {
        $item = ['type' => 'object', 'properties' => [
            'codigo' => ['type' => 'string', 'description' => 'Código del catálogo (PRD-0001, SRV-0001), tomado de buscar_catalogo'],
            'cantidad' => ['type' => 'integer', 'minimum' => 1],
            'precio' => ['type' => 'number', 'description' => 'Solo si el usuario dijo otro precio; si no, el de lista'],
        ], 'required' => ['codigo', 'cantidad']];

        $todas = [
            'buscar_cliente' => ['Busca un cliente por RUC, DNI o nombre. Devuelve su documento y lo que debe.', ['texto' => ['type' => 'string']], ['texto']],
            'buscar_catalogo' => ['Busca productos y servicios por nombre o código, con precio de lista y stock.', ['texto' => ['type' => 'string']], ['texto']],
            'preparar_venta' => ['Deja lista la pantalla Nueva venta con cliente, comprobante y productos. Solo productos: si hay servicios, usa preparar_cotizacion.', [
                'documento_cliente' => ['type' => 'string', 'description' => 'RUC o DNI del cliente'],
                'comprobante' => ['type' => 'string', 'enum' => ['factura', 'boleta', 'nota_venta']],
                'items' => ['type' => 'array', 'items' => $item],
            ], ['documento_cliente', 'comprobante', 'items']],
            'preparar_cotizacion' => ['Deja lista la pantalla Nueva cotización con cliente, productos y servicios.', [
                'documento_cliente' => ['type' => 'string'],
                'items' => ['type' => 'array', 'items' => $item],
            ], ['documento_cliente', 'items']],
            'buscar_serie' => ['Dónde está y en qué estado está una unidad por su serie BF-EQ o del fabricante.', ['serie' => ['type' => 'string']], ['serie']],
            'bajo_minimo' => ['Productos con stock en o bajo su mínimo.', [], []],
            'preparar_recepcion' => ['Deja lista la pantalla Nueva recepción con proveedor y cantidades. Solo productos.', [
                'proveedor' => ['type' => 'string'],
                'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['codigo' => ['type' => 'string'], 'cantidad' => ['type' => 'integer', 'minimum' => 1]], 'required' => ['codigo', 'cantidad']]],
            ], ['proveedor', 'items']],
            'resumen_hoy' => ['Lo que la gerencia debe atender hoy: órdenes listas sin cobrar, entregas, cajas con diferencia, stock bajo y ventas del día.', [], []],
            'ventas_periodo' => ['Ventas emitidas entre dos fechas, total y por vendedor.', ['desde' => ['type' => 'string', 'description' => 'AAAA-MM-DD'], 'hasta' => ['type' => 'string', 'description' => 'AAAA-MM-DD']], ['desde', 'hasta']],
            'deudores' => ['Clientes que más deben, con cuotas vencidas.', [], []],
            'cajas_con_diferencia' => ['Cierres de caja con faltante o sobrante que la gerencia no revisó.', [], []],
            'preparar_cambio_precio' => ['Prepara el cambio del precio de lista de un producto o servicio; la gerencia lo confirma.', ['codigo' => ['type' => 'string'], 'precio' => ['type' => 'number', 'minimum' => 0.01]], ['codigo', 'precio']],
        ];

        return array_map(fn (string $nombre) => ['type' => 'function', 'function' => [
            'name' => $nombre,
            'description' => $todas[$nombre][0],
            'parameters' => ['type' => 'object', 'properties' => (object) $todas[$nombre][1], 'required' => $todas[$nombre][2]],
        ]], $this->nombresPara($user));
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{datos: array<string, mixed>, accion: array<string, mixed>|null}
     */
    public function ejecutar(User $user, string $nombre, array $args): array
    {
        if (! in_array($nombre, $this->nombresPara($user), true)) {
            return $this->sinAccion(['error' => 'Esa acción no es de tu rol.']);
        }

        return match ($nombre) {
            'buscar_cliente' => $this->sinAccion($this->buscarCliente($user, (string) ($args['texto'] ?? ''))),
            'buscar_catalogo' => $this->sinAccion($this->buscarCatalogo($user, (string) ($args['texto'] ?? ''))),
            'buscar_serie' => $this->sinAccion($this->buscarSerie((string) ($args['serie'] ?? ''))),
            'bajo_minimo' => $this->sinAccion($this->bajoMinimo($user)),
            'resumen_hoy' => $this->sinAccion($this->resumenHoy()),
            'ventas_periodo' => $this->sinAccion($this->ventasPeriodo((string) ($args['desde'] ?? ''), (string) ($args['hasta'] ?? ''))),
            'deudores' => $this->sinAccion($this->deudores()),
            'cajas_con_diferencia' => $this->sinAccion($this->cajasConDiferencia()),
            'preparar_venta' => $this->prepararVenta($user, $args),
            'preparar_cotizacion' => $this->prepararCotizacion($user, $args),
            'preparar_recepcion' => $this->prepararRecepcion($args),
            'preparar_cambio_precio' => $this->prepararCambioPrecio($args),
            default => $this->sinAccion(['error' => 'No conozco esa acción.']),
        };
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{datos: array<string, mixed>, accion: null}
     */
    private function sinAccion(array $datos): array
    {
        return ['datos' => $datos, 'accion' => null];
    }

    /** @return list<int>|null almacenes que el usuario puede ver (null = todos) */
    private function almacenes(User $user): ?array
    {
        if ($almacen = $user->almacenRestringidoId()) {
            return [$almacen];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function buscarCliente(User $user, string $texto): array
    {
        $clientes = Client::query()->buscar($texto)->limit(5)->get();

        return ['clientes' => $clientes->map(function (Client $c) use ($user) {
            $cuotas = Installment::query()->whereIn('estado', ['pendiente', 'parcial', 'vencido'])
                ->whereHas('sale', fn ($q) => $q->where('client_id', $c->id)->where('estado', 'confirmada')->visiblePara($user))
                ->with('payments')->get();

            return [
                'documento' => $c->numero_documento,
                'tipo_documento' => $c->tipo_documento,
                'razon_social' => $c->razon_social,
                'debe' => round($cuotas->sum(fn (Installment $i) => $i->saldo()), 2),
                'vencido' => round($cuotas->where('estado', 'vencido')->sum(fn (Installment $i) => $i->saldo()), 2),
            ];
        })->values()->all(), 'nota' => $clientes->isEmpty() ? 'No está registrado: hay que registrarlo en Clientes antes de venderle.' : null];
    }

    /** @return array<string, mixed> */
    private function buscarCatalogo(User $user, string $texto): array
    {
        $almacenes = $this->almacenes($user);
        $palabras = preg_split('/\s+/', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [''];
        $coincide = function ($q) use ($texto, $palabras) {
            $q->where('codigo', $texto)->orWhere(function ($q) use ($palabras) {
                foreach ($palabras as $p) {
                    $q->where(fn ($q) => $q->where('nombre', 'like', "%{$p}%")->orWhere('codigo', 'like', "%{$p}%"));
                }
            });
        };

        $productos = Product::query()->conStock($almacenes)->where('activo', true)
            ->where(fn ($q) => $q->where('codigo_barras', $texto)->orWhere($coincide))
            ->orderBy('nombre')->limit(8)->get()
            ->map(fn (Product $p) => ['tipo' => 'producto', 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'precio' => (float) $p->precio_venta, 'stock' => $p->stockDisponible(), 'con_serie' => (bool) $p->serializado]);

        $servicios = Service::query()->where('activo', true)->where($coincide)->orderBy('nombre')->limit(6)->get()
            ->map(fn (Service $s) => ['tipo' => 'servicio', 'codigo' => $s->codigo, 'nombre' => $s->nombre, 'precio' => (float) $s->precio_venta]);

        return ['resultados' => $productos->concat($servicios)->values()->all()];
    }

    /** @return array<string, mixed> */
    private function buscarSerie(string $serie): array
    {
        $unidad = InventoryUnit::query()->with('product:id,nombre')
            ->where('numero_serie', $serie)->orWhere('serie_fabricante', $serie)->first();

        if (! $unidad) {
            return ['encontrada' => false];
        }

        return ['encontrada' => true, 'serie' => $unidad->numero_serie, 'producto' => $unidad->product->nombre,
            'estado' => $unidad->estado, 'almacen' => Sede::find($unidad->sede_almacen_id)?->nombre,
            'marca' => $unidad->marca, 'anio_fabricacion' => $unidad->anio_fabricacion];
    }

    /** @return array<string, mixed> */
    private function bajoMinimo(User $user): array
    {
        $productos = Product::query()->bajoMinimo($this->almacenes($user))->conStock($this->almacenes($user))->orderBy('nombre')->get();

        return ['total' => $productos->count(), 'productos' => $productos->take(15)->map(fn (Product $p) => [
            'codigo' => $p->codigo, 'nombre' => $p->nombre, 'stock' => $p->stockDisponible(), 'minimo' => $p->stock_minimo,
        ])->values()->all()];
    }

    /** @return array<string, mixed> */
    private function resumenHoy(): array
    {
        $listasSinCobrar = ServiceOrder::query()->whereNull('sale_id')->whereIn('estado', ['listo_certificado', 'listo_entrega'])->get(['id']);
        $hoy = Sale::query()->where('estado', 'confirmada')->whereDate('fecha', today());

        return [
            'ordenes_listas_sin_cobrar' => $listasSinCobrar->count(),
            'entregas_pendientes' => ServiceOrder::query()->where('estado', 'listo_entrega')->count(),
            'cajas_con_diferencia_sin_revisar' => CashRegister::query()->where('estado', 'cerrado')->whereNull('revisada_at')->where('diferencia', '!=', 0)->count(),
            'productos_bajo_minimo' => Product::query()->bajoMinimo()->count(),
            'ventas_de_hoy' => ['cantidad' => $hoy->count(), 'total' => round((float) $hoy->sum('total'), 2)],
        ];
    }

    /** @return array<string, mixed> */
    private function ventasPeriodo(string $desde, string $hasta): array
    {
        try {
            $inicio = Carbon::parse($desde)->startOfDay();
            $fin = Carbon::parse($hasta)->endOfDay();
        } catch (\Throwable) {
            return ['error' => 'Fechas no válidas.'];
        }

        $ventas = Sale::query()->with('vendedor:id,name')->where('estado', 'confirmada')->whereBetween('fecha', [$inicio, $fin])->get(['id', 'vendedor_id', 'total']);

        return [
            'desde' => $inicio->toDateString(), 'hasta' => $fin->toDateString(),
            'cantidad' => $ventas->count(), 'total' => round((float) $ventas->sum('total'), 2),
            'por_vendedor' => $ventas->groupBy(fn (Sale $s) => $s->vendedor->name)
                ->map(fn (Collection $v, string $nombre) => ['vendedor' => $nombre, 'ventas' => $v->count(), 'total' => round((float) $v->sum('total'), 2)])
                ->sortByDesc('total')->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function deudores(): array
    {
        $cuotas = Installment::query()->whereIn('estado', ['pendiente', 'parcial', 'vencido'])
            ->whereHas('sale', fn ($q) => $q->where('estado', 'confirmada'))
            ->with(['sale.client:id,razon_social,numero_documento', 'payments'])->get();

        return ['clientes' => $cuotas->groupBy(fn (Installment $i) => $i->sale->client_id)
            ->map(fn (Collection $c) => [
                'cliente' => $c->first()->sale->client->razon_social,
                'documento' => $c->first()->sale->client->numero_documento,
                'debe' => round($c->sum(fn (Installment $i) => $i->saldo()), 2),
                'vencido' => round($c->where('estado', 'vencido')->sum(fn (Installment $i) => $i->saldo()), 2),
                'proximo_vencimiento' => $c->min('fecha_vencimiento')?->toDateString(),
            ])->sortByDesc('debe')->take(8)->values()->all()];
    }

    /** @return array<string, mixed> */
    private function cajasConDiferencia(): array
    {
        return ['cierres' => CashRegister::query()->with('vendedor:id,name', 'sede:id,nombre')
            ->where('estado', 'cerrado')->whereNull('revisada_at')->where('diferencia', '!=', 0)
            ->latest('fecha_cierre')->limit(10)->get()
            ->map(fn (CashRegister $c) => [
                'vendedor' => $c->vendedor->name, 'sede' => $c->sede?->nombre,
                'cierre' => $c->fecha_cierre?->format('d/m/Y H:i'),
                'diferencia' => (float) $c->diferencia, 'explicacion' => $c->observacion,
            ])->values()->all()];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: list<array{modelo: Product|Service, cantidad: int, precio: float}>, 1: list<string>}
     */
    private function resolverItems(array $args): array
    {
        $lineas = [];
        $faltan = [];
        foreach ((array) ($args['items'] ?? []) as $item) {
            $codigo = (string) ($item['codigo'] ?? '');
            $modelo = Product::query()->where('activo', true)->where('codigo', $codigo)->first()
                ?? Service::query()->where('activo', true)->where('codigo', $codigo)->first();
            if (! $modelo) {
                $faltan[] = $codigo;

                continue;
            }
            $lineas[] = [
                'modelo' => $modelo,
                'cantidad' => max(1, (int) ($item['cantidad'] ?? 1)),
                'precio' => isset($item['precio']) && (float) $item['precio'] > 0 ? round((float) $item['precio'], 2) : (float) $modelo->precio_venta,
            ];
        }

        return [$lineas, $faltan];
    }

    private function cliente(string $documento): ?Client
    {
        return Client::query()->where('numero_documento', trim($documento))->first();
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{datos: array<string, mixed>, accion: array<string, mixed>|null}
     */
    private function prepararVenta(User $user, array $args): array
    {
        $cliente = $this->cliente((string) ($args['documento_cliente'] ?? ''));
        if (! $cliente) {
            return $this->sinAccion(['error' => 'Ese documento no está en Clientes: hay que registrarlo primero.']);
        }
        [$lineas, $faltan] = $this->resolverItems($args);
        if ($faltan !== [] || $lineas === []) {
            return $this->sinAccion(['error' => 'No encontré estos códigos en el catálogo: '.implode(', ', $faltan ?: ['(ninguno)']).'. Búscalos con buscar_catalogo.']);
        }
        if (collect($lineas)->contains(fn ($l) => $l['modelo'] instanceof Service)) {
            return $this->sinAccion(['error' => 'Con servicios la venta sale de una cotización: usa preparar_cotizacion.']);
        }

        $comprobante = (string) ($args['comprobante'] ?? 'boleta');
        $aviso = null;
        if ($comprobante === 'factura' && $cliente->tipo_documento !== 'ruc') {
            $comprobante = 'boleta';
            $aviso = 'El cliente no tiene RUC: se preparó boleta.';
        }

        $sedeId = $user->sedeRestringidaId() ?? $user->sede_id;
        $almacenId = $sedeId ? Sede::find($sedeId)?->almacenEfectivoId() : null;
        $items = [];
        $faltaStock = [];
        foreach ($lineas as $l) {
            /** @var Product $p */
            $p = $l['modelo'];
            if (! $p->serializado) {
                $items[] = ['tipo_linea' => 'producto', 'product_id' => $p->id, 'nombre' => $p->nombre, 'cantidad' => $l['cantidad'], 'precio_unitario' => $l['precio'], 'descuento' => 0];

                continue;
            }
            $unidades = InventoryUnit::query()->where('product_id', $p->id)->where('estado', 'disponible')
                ->when($almacenId, fn ($q) => $q->where('sede_almacen_id', $almacenId))
                ->orderBy('id')->limit($l['cantidad'])->get();
            if ($unidades->count() < $l['cantidad']) {
                $faltaStock[] = "{$p->nombre}: hay {$unidades->count()} de {$l['cantidad']}";
            }
            foreach ($unidades as $u) {
                $items[] = ['tipo_linea' => 'unidad_nueva', 'numero_serie' => $u->numero_serie, 'product_id' => $p->id, 'inventory_unit_id' => $u->id,
                    'nombre' => $p->nombre, 'detalle' => collect([$u->capacidad, $u->marca])->filter()->implode(' · '),
                    'cantidad' => 1, 'precio_unitario' => $l['precio'], 'descuento' => 0];
            }
        }

        session()->put('chispa.venta', [
            'id' => null, 'de_chispa' => true, 'numero_interno' => 'Chispa',
            'client' => $cliente->ficha(), 'sede_id' => $sedeId, 'vehicle_id' => null, 'destino' => 'local_cliente',
            'referencia' => null, 'condicion_pago' => 'contado', 'medio_pago' => 'efectivo', 'numero_operacion' => null,
            'comprobante_tipo' => $comprobante, 'observaciones' => null, 'cuotas' => [], 'items' => $items,
        ]);

        $total = round(collect($items)->sum(fn ($i) => $i['cantidad'] * $i['precio_unitario']), 2);

        return ['datos' => ['listo' => true, 'cliente' => $cliente->razon_social, 'comprobante' => $comprobante, 'total' => $total, 'aviso' => $aviso, 'falta_stock' => $faltaStock],
            'accion' => [
                'tipo' => 'abrir', 'etiqueta' => 'Abrir venta llenada',
                'url' => route('vendedor.ventas.create', ['current_team' => $user->currentTeam?->slug, 'chispa' => 1]),
                'titulo' => match ($comprobante) {
                    'factura' => 'Factura', 'boleta' => 'Boleta', default => 'Nota de venta'
                },
                'subtitulo' => $cliente->razon_social.' · '.$cliente->numero_documento,
                'lineas' => collect($lineas)->map(fn ($l) => ['texto' => $l['cantidad'].' × '.$l['modelo']->nombre, 'monto' => round($l['cantidad'] * $l['precio'], 2)])->all(),
                'total' => $total,
                'avisos' => array_values(array_filter([$aviso, ...$faltaStock])),
            ]];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{datos: array<string, mixed>, accion: array<string, mixed>|null}
     */
    private function prepararCotizacion(User $user, array $args): array
    {
        $cliente = $this->cliente((string) ($args['documento_cliente'] ?? ''));
        if (! $cliente) {
            return $this->sinAccion(['error' => 'Ese documento no está en Clientes: hay que registrarlo primero.']);
        }
        [$lineas, $faltan] = $this->resolverItems($args);
        if ($faltan !== [] || $lineas === []) {
            return $this->sinAccion(['error' => 'No encontré estos códigos en el catálogo: '.implode(', ', $faltan ?: ['(ninguno)']).'.']);
        }

        session()->put('chispa.cotizacion', [
            'numero' => 'Chispa', 'client' => $cliente->ficha(), 'destino' => 'local_cliente', 'vehicle_id' => null,
            'referencia' => null, 'direccion_del_local' => false, 'condicion_pago_propuesta' => 'Contado', 'observaciones' => null,
            'items' => collect($lineas)->map(fn ($l) => [
                'tipo' => $l['modelo'] instanceof Product ? 'product' : 'service',
                'product_id' => $l['modelo'] instanceof Product ? $l['modelo']->id : null,
                'service_id' => $l['modelo'] instanceof Service ? $l['modelo']->id : null,
                'codigo' => $l['modelo']->codigo, 'nombre' => $l['modelo']->nombre,
                'cantidad' => $l['cantidad'], 'precio_unitario' => $l['precio'], 'descuento' => 0,
            ])->all(),
        ]);

        $total = round(collect($lineas)->sum(fn ($l) => $l['cantidad'] * $l['precio']), 2);

        return ['datos' => ['listo' => true, 'cliente' => $cliente->razon_social, 'total' => $total],
            'accion' => [
                'tipo' => 'abrir', 'etiqueta' => 'Abrir cotización llenada',
                'url' => route('vendedor.cotizaciones.create', ['current_team' => $user->currentTeam?->slug, 'chispa' => 1]),
                'titulo' => 'Cotización', 'subtitulo' => $cliente->razon_social.' · '.$cliente->numero_documento,
                'lineas' => collect($lineas)->map(fn ($l) => ['texto' => $l['cantidad'].' × '.$l['modelo']->nombre, 'monto' => round($l['cantidad'] * $l['precio'], 2)])->all(),
                'total' => $total, 'avisos' => [],
            ]];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{datos: array<string, mixed>, accion: array<string, mixed>|null}
     */
    private function prepararRecepcion(array $args): array
    {
        [$lineas, $faltan] = $this->resolverItems($args);
        $lineas = array_values(array_filter($lineas, fn ($l) => $l['modelo'] instanceof Product));
        if ($faltan !== [] || $lineas === []) {
            return $this->sinAccion(['error' => 'No encontré estos productos: '.implode(', ', $faltan ?: ['(ninguno)']).'.']);
        }
        $proveedor = mb_substr(trim((string) ($args['proveedor'] ?? '')), 0, 150);

        session()->put('chispa.recepcion', [
            'proveedor' => $proveedor,
            'items' => collect($lineas)->map(fn ($l) => ['product_id' => $l['modelo']->id, 'cantidad' => $l['cantidad']])->all(),
        ]);

        $conSerie = collect($lineas)->filter(fn ($l) => $l['modelo']->serializado)->sum('cantidad');

        return ['datos' => ['listo' => true, 'proveedor' => $proveedor],
            'accion' => [
                'tipo' => 'abrir', 'etiqueta' => 'Abrir recepción llenada',
                'url' => route('almacen.recepciones.create', ['current_team' => request()->user()?->currentTeam?->slug, 'chispa' => 1]),
                'titulo' => 'Recepción', 'subtitulo' => $proveedor,
                'lineas' => collect($lineas)->map(fn ($l) => ['texto' => $l['modelo']->nombre, 'monto' => null, 'cantidad' => $l['cantidad']])->all(),
                'total' => null,
                'avisos' => $conSerie > 0 ? ["{$conSerie} unidades piden serie, marca y año."] : [],
            ]];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{datos: array<string, mixed>, accion: array<string, mixed>|null}
     */
    private function prepararCambioPrecio(array $args): array
    {
        [$lineas] = $this->resolverItems(['items' => [['codigo' => $args['codigo'] ?? '', 'cantidad' => 1]]]);
        $precio = round((float) ($args['precio'] ?? 0), 2);
        if ($lineas === [] || $precio <= 0) {
            return $this->sinAccion(['error' => 'No encontré ese código o el precio no es válido.']);
        }
        $modelo = $lineas[0]['modelo'];

        session()->put('chispa.precio', ['tipo' => $modelo instanceof Product ? 'producto' : 'servicio', 'id' => $modelo->id, 'precio' => $precio]);

        return ['datos' => ['listo' => true, 'nombre' => $modelo->nombre, 'antes' => (float) $modelo->precio_venta, 'despues' => $precio, 'falta' => 'Que la gerencia confirme con el botón.'],
            'accion' => [
                'tipo' => 'confirmar', 'etiqueta' => 'Confirmar cambio',
                'url' => route('asistente.precio'),
                'titulo' => 'Precio de lista', 'subtitulo' => $modelo->nombre,
                'lineas' => [['texto' => 'Antes', 'monto' => (float) $modelo->precio_venta], ['texto' => 'Ahora', 'monto' => $precio]],
                'total' => null, 'avisos' => ['Las cotizaciones ya enviadas mantienen su precio.'],
            ]];
    }
}
