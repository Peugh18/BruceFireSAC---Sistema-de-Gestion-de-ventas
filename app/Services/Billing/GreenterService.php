<?php

namespace App\Services\Billing;

use App\Models\CompanySetting;
use App\Models\ElectronicDocument;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use Greenter\Model\Client\Client as GreenterClient;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\DocumentInterface;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\Cuota;
use Greenter\Model\Sale\Detraction;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\See;
use RuntimeException;

class GreenterService
{
    /**
     * Catálogo 03 (Unidad de Medida) simplificado a los casos que BRUCE FIRE
     * usa hoy. Si aparece una unidad nueva, se agrega aquí; por defecto cae
     * a NIU (unidad) para no romper la emisión.
     */
    protected const UNIDADES_SUNAT = [
        'und' => 'NIU',
        'unidad' => 'NIU',
        'servicio' => 'ZZ',
        'metro' => 'MTR',
        'kilogramo' => 'KGM',
        'kg' => 'KGM',
        'litro' => 'LTR',
        'galon' => 'GLL',
    ];

    public function __construct(
        protected DetraccionCalculator $detraccionCalculator,
        protected NumeroEnLetrasService $numeroEnLetras,
    ) {}

    /**
     * Construye el objeto Greenter real (Invoice) para Factura/Boleta a
     * partir de los datos ya persistidos en la Venta. Notas de crédito/
     * débito no se construyen aquí todavía: `electronic_documents` no
     * guarda el desglose de montos de la nota (ver Documento Maestro §80),
     * así que emitir una ahora produciría un comprobante con datos
     * inventados. Se lanza una excepción clara en vez de fabricar montos.
     */
    public function buildInvoice(Sale $sale, ElectronicDocument $document): Invoice
    {
        if (! in_array($document->tipo, ['factura', 'boleta'], true)) {
            throw new RuntimeException(
                "GreenterService::buildInvoice solo soporta factura/boleta por ahora (recibido: {$document->tipo}). ".
                'Las notas de crédito/débito aún no persisten su desglose de montos.'
            );
        }

        $sale->loadMissing('client', 'items.product', 'items.service', 'installments');

        $companySetting = CompanySetting::current();

        $company = (new Company)
            ->setRuc($companySetting->ruc)
            ->setRazonSocial($companySetting->razon_social)
            ->setNombreComercial($companySetting->nombre_comercial ?? $companySetting->razon_social)
            ->setAddress(
                (new Address)
                    ->setUbigueo((string) $companySetting->ubigeo)
                    ->setDepartamento((string) $companySetting->departamento)
                    ->setProvincia((string) $companySetting->provincia)
                    ->setDistrito((string) $companySetting->distrito)
                    ->setUrbanizacion('-')
                    ->setDireccion((string) $companySetting->direccion)
                    ->setCodLocal('0000')
            );

        $client = (new GreenterClient)
            ->setTipoDoc($this->tipoDocCatalogo06($sale->client->tipo_documento))
            ->setNumDoc($sale->client->numero_documento)
            ->setRznSocial($sale->client->razon_social)
            ->setAddress((new Address)->setDireccion($sale->client->direccion_fiscal ?? '-'));

        $details = $sale->items->map(fn (SaleItem $item) => $this->buildDetail($item))->values()->all();

        $esServicio = $sale->items->contains(fn (SaleItem $item) => $item->esServicio());
        $detraccionCalc = $this->detraccionCalculator->calcular((float) $sale->total, $esServicio);

        $invoice = (new Invoice)
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101')
            ->setTipoDoc($document->tipo === 'factura' ? '01' : '03')
            ->setSerie($document->serie)
            ->setCorrelativo((string) $document->correlativo)
            ->setFechaEmision($sale->fecha)
            ->setFormaPago($this->buildFormaPago($sale))
            ->setTipoMoneda('PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas((float) $sale->subtotal)
            ->setMtoIGV((float) $sale->igv)
            ->setTotalImpuestos((float) $sale->igv)
            ->setValorVenta((float) $sale->subtotal)
            ->setSubTotal((float) $sale->total)
            ->setMtoImpVenta((float) $sale->total)
            ->setDetails($details)
            ->setLegends([
                (new Legend)
                    ->setCode('1000')
                    ->setValue($this->numeroEnLetras->convertir((float) $sale->total)),
            ]);

        if ($sale->condicion_pago === 'credito_30') {
            $invoice->setCuotas(
                $sale->installments->map(fn ($installment) => (new Cuota)
                    ->setMoneda('PEN')
                    ->setMonto((float) $installment->monto)
                    ->setFechaPago($installment->fecha_vencimiento))->values()->all()
            );
        }

        if ($detraccionCalc['aplica']) {
            $invoice->setDetraccion(
                (new Detraction)
                    ->setCodBienDetraccion($detraccionCalc['codigo_bien'])
                    ->setCodMedioPago((string) config('billing.detraccion.cod_medio_pago'))
                    ->setPercent((float) config('billing.detraccion.tasa') * 100)
                    ->setMount($detraccionCalc['monto'])
            );

            $invoice->setLegends([
                ...$invoice->getLegends(),
                (new Legend)
                    ->setCode('2006')
                    ->setValue('Operación sujeta al Sistema de Pago de Obligaciones Tributarias'),
            ]);
        }

        return $invoice;
    }

    /**
     * Firma el documento con el certificado SUNAT configurado y devuelve el
     * XML firmado real (no un JSON simulando una firma). Solo necesita el
     * certificado, no las credenciales de Clave SOL: esas las usa
     * GreenterSunatClient al momento de enviar.
     */
    public function sign(DocumentInterface $document): string
    {
        $certPath = config('billing.sunat.cert_path');

        if (! is_string($certPath) || ! file_exists($certPath)) {
            throw new RuntimeException('Certificado SUNAT no configurado (billing.sunat.cert_path).');
        }

        $see = new See;
        $see->setCertificate(file_get_contents($certPath));

        $xmlSigned = $see->getXmlSigned($document);

        if (! is_string($xmlSigned) || $xmlSigned === '') {
            throw new RuntimeException('Greenter no pudo generar el XML firmado.');
        }

        return $xmlSigned;
    }

    protected function buildDetail(SaleItem $item): SaleDetail
    {
        $productOrService = $item->product ?? $item->service;
        $valorVenta = (float) $item->subtotal;
        $igvLinea = round($valorVenta * 0.18, 2);
        $valorUnitario = (float) $item->precio_unitario;

        $detail = (new SaleDetail)
            ->setCodProducto($productOrService->codigo)
            ->setUnidad($this->unidadCatalogo03($productOrService))
            ->setCantidad((float) $item->cantidad)
            ->setDescripcion($productOrService->nombre)
            ->setMtoValorUnitario($valorUnitario)
            ->setMtoValorVenta($valorVenta)
            ->setMtoBaseIgv($valorVenta)
            ->setPorcentajeIgv(18.00)
            ->setIgv($igvLinea)
            ->setTipAfeIgv('10')
            ->setTotalImpuestos($igvLinea)
            ->setMtoPrecioUnitario(round($valorUnitario * 1.18, 2));

        if ((float) $item->descuento > 0) {
            $detail->setDescuentos([
                (new Charge)
                    ->setCodTipo('00')
                    ->setMontoBase((float) $item->cantidad * $valorUnitario)
                    ->setMonto((float) $item->descuento),
            ]);
        }

        return $detail;
    }

    protected function buildFormaPago(Sale $sale): FormaPagoContado|FormaPagoCredito
    {
        if ($sale->condicion_pago !== 'credito_30') {
            return new FormaPagoContado;
        }

        $montoNetoPendiente = (float) $sale->total;

        return new FormaPagoCredito($montoNetoPendiente, 'PEN');
    }

    protected function tipoDocCatalogo06(string $tipoDocumento): string
    {
        return match ($tipoDocumento) {
            'ruc' => '6',
            'dni' => '1',
            default => throw new RuntimeException("Tipo de documento de cliente no soportado para SUNAT: {$tipoDocumento}"),
        };
    }

    protected function unidadCatalogo03(Product|Service $item): string
    {
        if ($item->esServicio()) {
            return 'ZZ';
        }

        $clave = mb_strtolower(trim($item->unidad_medida));

        return self::UNIDADES_SUNAT[$clave] ?? 'NIU';
    }
}
