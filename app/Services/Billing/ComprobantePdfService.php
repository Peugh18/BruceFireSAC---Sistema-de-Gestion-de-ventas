<?php

namespace App\Services\Billing;

use App\Models\Client;
use App\Models\CompanyBankAccount;
use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ComprobantePdfService
{
    /**
     * Cómo se lee la unidad en el papel: el XML lleva el código SUNAT
     * (Catálogo 03), pero al cliente se le muestra la abreviatura de siempre.
     */
    protected const UNIDADES_IMPRESAS = [
        'niu' => 'UND', 'und' => 'UND', 'unidad' => 'UND',
        'zz' => 'SERV', 'servicio' => 'SERV',
        'mtr' => 'M', 'metro' => 'M',
        'kgm' => 'KG', 'kg' => 'KG', 'kilogramo' => 'KG',
        'ltr' => 'L', 'litro' => 'L',
        'gll' => 'GAL', 'galon' => 'GAL',
    ];

    public function __construct(
        protected NumeroEnLetrasService $numeroEnLetras,
        protected ComprobanteQrGenerator $qrGenerator,
        protected DetraccionCalculator $detraccionCalculator,
    ) {}

    /**
     * Genera la representación impresa (PDF) del comprobante y la guarda en
     * el disco local. Devuelve la ruta relativa (para `pdf_path`).
     */
    public function generate(ElectronicDocument $document, string $xmlSigned): string
    {
        $path = "pdf/{$document->tipo}-{$document->serie}-{$document->correlativo}.pdf";

        Storage::disk('local')->put($path, $this->render($document, $xmlSigned));

        return $path;
    }

    /**
     * Ruta del PDF al día: si el Gerente cambió el diseño o los datos de la
     * empresa después de generarlo, se vuelve a dibujar con el mismo XML
     * firmado (el comprobante no cambia, solo su presentación).
     */
    public function vigente(ElectronicDocument $document): ?string
    {
        if (! in_array($document->tipo, ['factura', 'boleta'], true)
            || ! $document->xml_path
            || ! Storage::disk('local')->exists($document->xml_path)) {
            return $document->pdf_path;
        }

        $tieneArchivo = $document->pdf_path && Storage::disk('local')->exists($document->pdf_path);

        if ($tieneArchivo && Storage::disk('local')->lastModified($document->pdf_path) >= $this->ultimoCambioDeDiseno()) {
            return $document->pdf_path;
        }

        return $this->redibujar($document);
    }

    /**
     * Vuelve a dibujar el PDF de una factura o boleta con su XML firmado, sin
     * importar cuándo se generó. Devuelve null si no tiene XML guardado.
     */
    public function redibujar(ElectronicDocument $document): ?string
    {
        if (! in_array($document->tipo, ['factura', 'boleta'], true)
            || ! $document->xml_path
            || ! Storage::disk('local')->exists($document->xml_path)) {
            return null;
        }

        $path = $this->generate($document, (string) Storage::disk('local')->get($document->xml_path));

        if ($path !== $document->pdf_path) {
            $document->update(['pdf_path' => $path]);
        }

        return $path;
    }

    /**
     * Una factura de ejemplo con los datos reales de la empresa, para que el
     * Gerente vea cómo queda antes de emitir.
     */
    public function vistaPrevia(): string
    {
        $client = new Client([
            'tipo_documento' => 'ruc',
            'numero_documento' => '20123456789',
            'razon_social' => 'CLIENTE DE EJEMPLO S.A.C.',
            'direccion_fiscal' => 'AV. ESPAÑA 1234, TRUJILLO - LA LIBERTAD',
        ]);

        $lineas = collect([
            ['EXT-6KG', 'Extintor de 6 kg PQS - ABC', 'NIU', 2, 95.00, 0.0],
            ['REC-CO2', 'Recarga de extintor CO2 10 lb', 'ZZ', 3, 45.00, 5.0],
            ['SEN-001', 'Señalética fotoluminiscente 20×30 cm', 'NIU', 4, 12.50, 0.0],
        ])->map(function (array $linea, int $index) {
            [$codigo, $nombre, $unidad, $cantidad, $precio, $descuento] = $linea;
            $item = new SaleItem([
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'descuento' => $descuento,
                'subtotal' => round($cantidad * $precio - $descuento, 2),
            ]);
            $item->id = $index + 1;
            $catalogo = $unidad === 'ZZ'
                ? new Service(['codigo' => $codigo, 'nombre' => $nombre, 'unidad_medida' => 'servicio'])
                : new Product(['codigo' => $codigo, 'nombre' => $nombre, 'unidad_medida' => $unidad]);
            $item->product_id = $unidad === 'ZZ' ? null : $index + 1;
            $item->service_id = $unidad === 'ZZ' ? $index + 1 : null;
            $item->setRelation('product', $unidad === 'ZZ' ? null : $catalogo);
            $item->setRelation('service', $unidad === 'ZZ' ? $catalogo : null);

            return $item;
        });

        $total = round($lineas->sum('subtotal'), 2);
        $subtotal = round($total / 1.18, 2);

        $sale = new Sale([
            'fecha' => today(),
            'comprobante_tipo' => 'factura',
            'condicion_pago' => 'contado',
            'medio_pago' => 'transferencia',
            'referencia' => 'LOCAL: Sede principal',
            'observaciones' => 'Entrega en el local del cliente.',
            'subtotal' => $subtotal,
            'igv' => round($total - $subtotal, 2),
            'total' => $total,
        ]);
        $sale->setRelation('client', $client);
        $sale->setRelation('items', new EloquentCollection($lineas->all()));
        $sale->setRelation('installments', new EloquentCollection);
        $sale->setRelation('vendedor', new User(['name' => 'Vendedor de ejemplo']));
        $sale->setRelation('vehicle', null);

        $document = new ElectronicDocument([
            'tipo' => 'factura',
            'serie' => (string) config('billing.series.factura', 'F001'),
            'correlativo' => 123,
            'fecha_emision' => today(),
        ]);
        $document->created_at = Carbon::now();
        $document->setRelation('sale', $sale);

        return $this->render($document, '<ds:DigestValue>VISTA-PREVIA</ds:DigestValue>', medioPago: 'Transferencia');
    }

    /**
     * Dibuja el PDF y devuelve su contenido.
     */
    public function render(ElectronicDocument $document, string $xmlSigned, ?string $medioPago = null): string
    {
        return Pdf::loadView('pdf.comprobante', $this->datos($document, $xmlSigned, $medioPago))->setPaper('a4')->output();
    }

    /**
     * El mismo comprobante como HTML (para revisarlo sin generar el PDF).
     */
    public function html(ElectronicDocument $document, string $xmlSigned): string
    {
        return view('pdf.comprobante', $this->datos($document, $xmlSigned))->render();
    }

    /**
     * Todo lo que la plantilla necesita: lo general sale de la empresa
     * (Gerente) y lo particular de la venta.
     *
     * @return array<string, mixed>
     */
    protected function datos(ElectronicDocument $document, string $xmlSigned, ?string $medioPago = null): array
    {
        $document->loadMissing('sale.client', 'sale.vehicle', 'sale.vendedor', 'sale.items.product', 'sale.items.service', 'sale.installments');
        $sale = $document->sale;
        $company = CompanySetting::current();

        $lineas = $sale->lineasComprobante()->values();
        $medioPago ??= $sale->exists ? $sale->medioPagoTexto() : null;

        return [
            'document' => $document,
            'sale' => $sale,
            'company' => $company,
            'marca' => [
                'color' => $company->colorMarca(),
                'texto' => $company->colorTextoSobreMarca(),
                'suave' => $company->colorMarcaSuave(),
                'linea' => $company->colorMarcaSuave(0.75),
            ],
            'logo' => $company->logoParaPdf(),
            'lineas' => $lineas,
            'unidades' => $lineas->mapWithKeys(fn (SaleItem $item, int $index) => [$index => $this->unidadImpresa($item)]),
            'mostrarCodigo' => $lineas->contains(fn (SaleItem $item) => filled(($item->product ?? $item->service)?->codigo)),
            'mostrarDescuento' => $lineas->contains(fn (SaleItem $item) => (float) $item->descuento > 0),
            'condicionPago' => $sale->esCredito()
                ? 'Crédito '.str_pad((string) $sale->diasCredito(), 2, '0', STR_PAD_LEFT).' días'
                : 'Contado'.($medioPago ? ' · '.$medioPago : ''),
            'qrBase64' => base64_encode($this->qrGenerator->generate($document, $xmlSigned)),
            'montoEnLetras' => $this->numeroEnLetras->convertir((float) $sale->total),
            'bankAccounts' => CompanyBankAccount::query()->where('activo', true)->orderBy('orden')->get(),
            'detraccion' => $this->detraccionCalculator->paraVenta($sale, $document->tipo),
        ];
    }

    protected function unidadImpresa(SaleItem $item): string
    {
        if ($item->service) {
            return 'SERV';
        }

        $unidad = trim((string) $item->product?->unidad_medida);

        return self::UNIDADES_IMPRESAS[mb_strtolower($unidad)] ?? mb_strtoupper($unidad);
    }

    /**
     * Momento del último cambio que afecta cómo se ve el comprobante: los
     * datos de la empresa y sus cuentas (Gerente) o una nueva versión de la
     * plantilla instalada con el código.
     */
    protected function ultimoCambioDeDiseno(): int
    {
        $empresa = CompanySetting::current()->updated_at?->getTimestamp() ?? 0;
        $cuentas = CompanyBankAccount::query()->max('updated_at');
        $plantilla = max((int) @filemtime(resource_path('views/pdf/comprobante.blade.php')), (int) @filemtime(__FILE__));

        return max($empresa, $cuentas ? Carbon::parse($cuentas)->getTimestamp() : 0, $plantilla);
    }
}
