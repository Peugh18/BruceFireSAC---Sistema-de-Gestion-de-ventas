<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\DocumentSeries;
use App\Models\HistoricalCreditNote;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('import:historical-sales {csvPath} {--fresh : Borra un import histórico previo antes de re-importar}')]
#[Description('Importa el histórico real de ventas 2025-2026 (Facturas/Boletas → Ventas, Proformas → Cotizaciones, Notas de crédito → tabla histórica) desde el CSV consolidado')]
class ImportHistoricalSales extends Command
{
    private const PLACEHOLDER_EMAIL = 'ventas.historicas@brucefire.pe';

    private const PLACEHOLDER_RUC = '00000000000';

    /** @var array<string, array{type: string, id: int}> normalizado => ['type' => 'product'|'service', 'id' => int] */
    private array $catalog = [];

    /** @var array<string, int> "tipo:numero" => client_id */
    private array $clients = [];

    private int $placeholderClientId;

    private int $vendedorId;

    public function handle(): void
    {
        $path = $this->argument('csvPath');

        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return;
        }

        if (Sale::where('numero_interno', 'like', 'VTA-HIST-%')->exists()
            || Quote::where('numero', 'like', 'COT-HIST-%')->exists()) {
            if (! $this->option('fresh')) {
                $this->error('Ya existe un import histórico previo. Corre de nuevo con --fresh para reemplazarlo.');

                return;
            }

            $this->purgePreviousImport();
        }

        $rows = $this->readCsv($path);
        $this->info(count($rows).' filas leídas de '.$path);

        $this->vendedorId = User::firstOrCreate(
            ['email' => self::PLACEHOLDER_EMAIL],
            ['name' => 'Ventas Históricas (Importado)', 'password' => Hash::make(Str::random(32))],
        )->id;

        $placeholderClient = Client::firstOrCreate(
            ['tipo_documento' => 'ruc', 'numero_documento' => self::PLACEHOLDER_RUC],
            ['codigo_interno' => 'CLI-TMP-'.Str::uuid()->toString(), 'razon_social' => 'SIN DOCUMENTO — HISTÓRICO', 'activo' => true],
        );
        if ($placeholderClient->wasRecentlyCreated) {
            $placeholderClient->update(['codigo_interno' => 'CLI-'.str_pad((string) $placeholderClient->id, 4, '0', STR_PAD_LEFT)]);
        }
        $this->placeholderClientId = $placeholderClient->id;

        DB::transaction(function () use ($rows) {
            $this->importCatalog($rows);
            $this->importClients($rows);
            $this->importCreditNotes($rows);
            [$saleCount, $quoteCount, $seriesMax] = $this->importDocuments($rows);
            $this->seedDocumentSeries($seriesMax);
            $this->printSummary($rows, $saleCount, $quoteCount);
        });
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ';');
        $rows = [];

        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            if (count($line) !== count($header)) {
                continue;
            }
            $rows[] = array_combine($header, $line);
        }
        fclose($handle);

        return $rows;
    }

    private function normalizeProducto(string $producto): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $producto)));
    }

    private function classifyCategoria(string $normalizado): string
    {
        if (str_contains($normalizado, 'EXTINTOR')) {
            return 'extintor';
        }

        $epp = ['ZAPATO', 'BOTA', 'POLO', 'CASCO', 'GUANTE', 'CHALECO', 'CORTAVIENTO', 'TAYVECK', 'LENTE', 'TAPON', 'ARNES', 'CORREA'];
        foreach ($epp as $palabra) {
            if (str_contains($normalizado, $palabra)) {
                return 'epp';
            }
        }

        return 'otro';
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function importCatalog(array $rows): void
    {
        /** @var array<string, array{ultimaFecha: string, ultimoPrecio: float, variantes: array<string, true>, unidades: float}> $grupos */
        $grupos = [];

        foreach ($rows as $row) {
            if ($row['doc_tipo'] === 'NC') {
                continue;
            }
            $normalizado = $this->normalizeProducto($row['producto']);
            if ($normalizado === '') {
                continue;
            }

            $grupos[$normalizado] ??= ['ultimaFecha' => '', 'ultimoPrecio' => 0.0, 'variantes' => [], 'unidades' => 0.0];
            $g = &$grupos[$normalizado];
            $g['variantes'][trim($row['producto'])] = true;
            $g['unidades'] += (float) $row['cantidad'];

            if ($row['fecha_emision'] >= $g['ultimaFecha']) {
                $g['ultimaFecha'] = $row['fecha_emision'];
                $g['ultimoPrecio'] = (float) $row['precio'];
            }
            unset($g);
        }

        $creados = 0;
        foreach ($grupos as $normalizado => $g) {
            $esServicio = str_contains($normalizado, 'RECARGA') || str_contains($normalizado, 'MANTENIMIENTO');
            $codigo = ($esServicio ? 'IMP-S-' : 'IMP-P-').strtoupper(substr(md5($normalizado), 0, 8));
            $descripcion = sprintf(
                'Importado del histórico 2025-2026. %d variante(s) de texto agrupadas, %s unidades vendidas históricamente.',
                count($g['variantes']),
                number_format($g['unidades'], 0),
            );

            if ($esServicio) {
                $registro = Service::firstOrCreate(
                    ['codigo' => $codigo],
                    [
                        'nombre' => Str::limit($normalizado, 250, ''),
                        'descripcion' => $descripcion,
                        'precio_venta' => $g['ultimoPrecio'],
                        'aplica_igv' => true,
                        'activo' => true,
                    ],
                );
                $this->catalog[$normalizado] = ['type' => 'service', 'id' => $registro->id];
            } else {
                $registro = Product::firstOrCreate(
                    ['codigo' => $codigo],
                    [
                        'nombre' => Str::limit($normalizado, 250, ''),
                        'categoria' => $this->classifyCategoria($normalizado),
                        'descripcion' => $descripcion,
                        'precio_venta' => $g['ultimoPrecio'],
                        'aplica_igv' => true,
                        'serializado' => false,
                        'activo' => true,
                    ],
                );
                $this->catalog[$normalizado] = ['type' => 'product', 'id' => $registro->id];
            }

            if ($registro->wasRecentlyCreated) {
                $creados++;
            }
        }

        $this->info("Catálogo: {$creados} producto(s)/servicio(s) nuevos, ".count($grupos).' agrupaciones totales.');
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function importClients(array $rows): void
    {
        /** @var array<string, string> "tipo:numero" => razon_social */
        $unicos = [];

        foreach ($rows as $row) {
            $digitos = preg_replace('/\D/', '', $row['ruc_cliente']);
            $tipo = match (strlen($digitos)) {
                11 => 'ruc',
                8 => 'dni',
                default => null,
            };

            if ($tipo === null) {
                continue;
            }

            $unicos["{$tipo}:{$digitos}"] ??= trim($row['cliente']) !== '' ? trim($row['cliente']) : 'CLIENTE HISTÓRICO';
        }

        $creados = 0;
        foreach ($unicos as $key => $razonSocial) {
            [$tipo, $numero] = explode(':', $key, 2);

            $client = Client::firstOrCreate(
                ['tipo_documento' => $tipo, 'numero_documento' => $numero],
                ['codigo_interno' => 'CLI-TMP-'.Str::uuid()->toString(), 'razon_social' => $razonSocial, 'activo' => true],
            );
            if ($client->wasRecentlyCreated) {
                $client->update(['codigo_interno' => 'CLI-'.str_pad((string) $client->id, 4, '0', STR_PAD_LEFT)]);
                $creados++;
            }

            $this->clients[$key] = $client->id;
        }

        $this->info("Clientes: {$creados} nuevos, ".count($unicos).' documentos distintos procesados.');
    }

    private function resolveClientId(string $rucCliente): int
    {
        $digitos = preg_replace('/\D/', '', $rucCliente);
        $tipo = match (strlen($digitos)) {
            11 => 'ruc',
            8 => 'dni',
            default => null,
        };

        if ($tipo === null) {
            return $this->placeholderClientId;
        }

        return $this->clients["{$tipo}:{$digitos}"] ?? $this->placeholderClientId;
    }

    /**
     * @return array{0: int|null, 1: int|null} [product_id, service_id]
     */
    private function resolveCatalogItem(string $producto): array
    {
        $normalizado = $this->normalizeProducto($producto);
        $item = $this->catalog[$normalizado] ?? null;

        if ($item === null) {
            return [null, null];
        }

        return $item['type'] === 'service' ? [null, $item['id']] : [$item['id'], null];
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function importCreditNotes(array $rows): void
    {
        $creados = 0;
        foreach ($rows as $row) {
            if ($row['doc_tipo'] !== 'NC') {
                continue;
            }

            [$productId, $serviceId] = $this->resolveCatalogItem($row['producto']);

            HistoricalCreditNote::create([
                'fecha' => $row['fecha_emision'],
                'serie' => $row['serie'],
                'nro_documento' => $row['nro_documento'],
                'client_id' => $this->resolveClientId($row['ruc_cliente']) === $this->placeholderClientId ? null : $this->resolveClientId($row['ruc_cliente']),
                'cliente_nombre_original' => trim($row['cliente']),
                'producto_original' => trim($row['producto']),
                'producto_normalizado' => $this->normalizeProducto($row['producto']),
                'product_id' => $productId,
                'service_id' => $serviceId,
                'cantidad' => (float) $row['cantidad'],
                'precio_unitario' => (float) $row['precio'],
                'total' => (float) $row['total'],
                'archivo_origen' => $row['archivo_origen'],
            ]);
            $creados++;
        }

        $this->info("Notas de crédito históricas: {$creados} registradas.");
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{0: int, 1: int, 2: array<string, int>}
     */
    private function importDocuments(array $rows): array
    {
        $grupos = [];
        foreach ($rows as $row) {
            if (! in_array($row['doc_tipo'], ['F', 'B', 'PR'], true)) {
                continue;
            }
            $anio = substr($row['fecha_emision'], 0, 4);
            $key = "{$row['doc_tipo']}|{$row['serie']}|{$row['nro_documento']}|{$anio}";
            $grupos[$key][] = $row;
        }

        // Pase 1: Proformas -> Cotizaciones. Se arma un mapa de qué comprobante
        // (clave "DOC-SERIE-NRO") canjeó cada proforma, para linkear con la venta
        // real en el pase 2.
        $canjeadaPorVenta = []; // "F-F001-13256" => quote_id
        $quoteSeq = 0;
        $quoteCount = 0;

        foreach ($grupos as $key => $lineas) {
            if ($lineas[0]['doc_tipo'] !== 'PR') {
                continue;
            }

            $quoteSeq++;
            $primera = $lineas[0];
            $fecha = $primera['fecha_emision'];
            [$subtotal, $igv, $total] = $this->headerTotals($primera, $lineas);

            $quote = Quote::create([
                'numero' => 'COT-HIST-'.str_pad((string) $quoteSeq, 5, '0', STR_PAD_LEFT),
                'vendedor_id' => $this->vendedorId,
                'client_id' => $this->resolveClientId($primera['ruc_cliente']),
                'fecha' => $fecha,
                'vigencia_hasta' => date('Y-m-d', strtotime($fecha.' +15 days')),
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
                'estado' => 'vencida',
            ]);

            foreach ($lineas as $linea) {
                [$productId, $serviceId] = $this->resolveCatalogItem($linea['producto']);
                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'product_id' => $productId,
                    'service_id' => $serviceId,
                    'cantidad' => max(1, (int) round((float) $linea['cantidad'])),
                    'precio_unitario' => (float) $linea['precio'],
                    'descuento' => (float) ($linea['descuento'] ?? 0),
                    'subtotal' => (float) $linea['total'],
                ]);
            }

            $canjeada = trim($primera['proforma_canjeada']);
            if ($canjeada !== '') {
                $canjeadaPorVenta[$canjeada] = $quote->id;
            }

            $quoteCount++;
        }

        // Pase 2: Facturas/Boletas -> Ventas reales.
        $saleSeq = 0;
        $saleCount = 0;
        $seriesMax = []; // "tipo_comprobante|serie" => max nro_documento

        foreach ($grupos as $key => $lineas) {
            $docTipo = $lineas[0]['doc_tipo'];
            if (! in_array($docTipo, ['F', 'B'], true)) {
                continue;
            }

            $saleSeq++;
            $primera = $lineas[0];
            $fecha = $primera['fecha_emision'];
            $comprobanteTipo = $docTipo === 'F' ? 'factura' : 'boleta';
            [$subtotal, $igv, $total] = $this->headerTotals($primera, $lineas);

            $ventaKey = "{$docTipo}-{$primera['serie']}-{$primera['nro_documento']}";
            $quoteId = $canjeadaPorVenta[$ventaKey] ?? null;

            $sale = Sale::create([
                'numero_interno' => 'VTA-HIST-'.str_pad((string) $saleSeq, 5, '0', STR_PAD_LEFT),
                'quote_id' => $quoteId,
                'client_id' => $this->resolveClientId($primera['ruc_cliente']),
                'vendedor_id' => $this->vendedorId,
                'fecha' => $fecha,
                'destino' => 'local_cliente',
                'condicion_pago' => 'contado',
                'comprobante_tipo' => $comprobanteTipo,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
                'estado' => 'confirmada',
            ]);

            if ($quoteId !== null) {
                Quote::whereKey($quoteId)->update(['estado' => 'convertida']);
            }

            foreach ($lineas as $linea) {
                [$productId, $serviceId] = $this->resolveCatalogItem($linea['producto']);
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $productId,
                    'service_id' => $serviceId,
                    'tipo_linea' => $serviceId !== null ? 'recarga_servicio' : 'unidad_nueva',
                    'cantidad' => max(1, (int) round((float) $linea['cantidad'])),
                    'precio_unitario' => (float) $linea['precio'],
                    'descuento' => (float) ($linea['descuento'] ?? 0),
                    'subtotal' => (float) $linea['total'],
                ]);
            }

            $seriesKey = "{$comprobanteTipo}|{$primera['serie']}";
            $nro = (int) $primera['nro_documento'];
            $seriesMax[$seriesKey] = max($seriesMax[$seriesKey] ?? 0, $nro);

            $saleCount++;
        }

        return [$saleCount, $quoteCount, $seriesMax];
    }

    /**
     * Totales de cabecera del comprobante. El formato 2025 trae
     * TOTAL_GRAVADO/TOTAL_IGV/TOTAL ya calculados (repetidos en cada línea);
     * el formato 2026 no los trae, así que se reconstruyen sumando las
     * líneas del grupo con el IGV estándar de Perú (18%).
     *
     * @param  array<string, string>  $primera
     * @param  list<array<string, string>>  $lineas
     * @return array{0: float, 1: float, 2: float}
     */
    private function headerTotals(array $primera, array $lineas): array
    {
        if ($primera['total_comprobante'] !== '') {
            return [
                (float) $primera['total_gravado'],
                (float) $primera['total_igv'],
                (float) $primera['total_comprobante'],
            ];
        }

        $total = array_sum(array_map(fn ($l) => (float) $l['total'], $lineas));
        $subtotal = round($total / 1.18, 2);
        $igv = round($total - $subtotal, 2);

        return [$subtotal, $igv, $total];
    }

    /**
     * @param  array<string, int>  $seriesMax
     */
    private function seedDocumentSeries(array $seriesMax): void
    {
        foreach ($seriesMax as $key => $maxNro) {
            [$tipoComprobante, $serie] = explode('|', $key, 2);

            $documentSeries = DocumentSeries::firstOrCreate(
                ['tipo_comprobante' => $tipoComprobante, 'serie' => $serie],
                ['correlativo_actual' => 0],
            );

            if ($documentSeries->correlativo_actual < $maxNro) {
                $documentSeries->update(['correlativo_actual' => $maxNro]);
            }
        }

        $this->info('Numeración de comprobantes (document_series) blindada contra colisión con SUNAT: '.
            collect($seriesMax)->map(fn ($max, $key) => str_replace('|', ' ', $key)." hasta {$max}")->implode(', '));
    }

    private function purgePreviousImport(): void
    {
        $this->warn('Borrando import histórico previo (--fresh)...');

        $saleIds = Sale::where('numero_interno', 'like', 'VTA-HIST-%')->pluck('id');
        SaleItem::whereIn('sale_id', $saleIds)->delete();
        Sale::whereIn('id', $saleIds)->delete();

        $quoteIds = Quote::where('numero', 'like', 'COT-HIST-%')->pluck('id');
        QuoteItem::whereIn('quote_id', $quoteIds)->delete();
        Quote::whereIn('id', $quoteIds)->delete();

        HistoricalCreditNote::truncate();
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function printSummary(array $rows, int $saleCount, int $quoteCount): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Ventas (Factura/Boleta) creadas', (string) $saleCount);
        $this->components->twoColumnDetail('Cotizaciones (Proforma) creadas', (string) $quoteCount);
        $this->components->twoColumnDetail('Notas de crédito históricas', (string) HistoricalCreditNote::count());
        $this->components->twoColumnDetail('Clientes en total', (string) Client::count());
        $this->components->twoColumnDetail('Productos en total', (string) Product::count());
        $this->components->twoColumnDetail('Servicios en total', (string) Service::count());
        $this->newLine();

        $fechas = collect($rows)->pluck('fecha_emision')->filter();
        $this->info('Rango de fechas: '.$fechas->min().' a '.$fechas->max());

        $top = collect($rows)
            ->groupBy(fn ($r) => $this->normalizeProducto($r['producto']))
            ->map(fn ($grupo, $nombre) => ['producto' => $nombre, 'unidades' => $grupo->sum(fn ($r) => (float) $r['cantidad'])])
            ->sortByDesc('unidades')
            ->take(15);

        $this->table(['Producto', 'Unidades históricas'], $top->map(fn ($r) => [Str::limit($r['producto'], 60), number_format($r['unidades'], 0)]));
    }
}
