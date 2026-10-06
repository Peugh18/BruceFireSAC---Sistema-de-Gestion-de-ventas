<?php

namespace App\Services\Billing;

use App\Models\Client;
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
use Greenter\Model\Sale\Document;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;
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
        // Los códigos SUNAT que ya vienen como unidad del producto.
        'niu' => 'NIU',
        'kgm' => 'KGM',
        'mtr' => 'MTR',
        'ltr' => 'LTR',
        'gll' => 'GLL',
        'gli' => 'GLL',
        'set' => 'SET',
        'servicio' => 'ZZ',
        'metro' => 'MTR',
        'kilogramo' => 'KGM',
        'kg' => 'KGM',
        'litro' => 'LTR',
        'galon' => 'GLL',
        // EPP: guantes y botas por par; cajas, paquetes y docenas.
        'par' => 'PR',
        'pr' => 'PR',
        'caja' => 'BX',
        'bx' => 'BX',
        'paquete' => 'PK',
        'pk' => 'PK',
        'docena' => 'DZN',
        'dzn' => 'DZN',
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

        $company = $this->buildCompany();
        $client = $this->buildClient($sale);

        $details = $sale->lineasComprobante()->map(fn (SaleItem $item) => $this->buildDetail($item))->values()->all();

        $detraccionCalc = $this->detraccionCalculator->paraVenta($sale, $document->tipo);

        $invoice = (new Invoice)
            ->setUblVersion('2.1')
            // 1001 = operación sujeta a detracción (catálogo 51).
            ->setTipoOperacion($detraccionCalc['aplica'] ? '1001' : '0101')
            ->setTipoDoc($document->tipo === 'factura' ? '01' : '03')
            ->setSerie($document->serie)
            ->setCorrelativo((string) $document->correlativo)
            ->setFechaEmision($document->fecha_emision ?? $sale->fecha)
            ->setFormaPago($this->buildFormaPago($sale, $detraccionCalc['monto']))
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

        $observacion = collect([$sale->referencia, $sale->observaciones])->filter()->implode(' | ');

        if ($observacion !== '') {
            $invoice->setObservacion(mb_substr($observacion, 0, 250));
        }

        if ($sale->esCredito()) {
            $invoice->setCuotas(
                collect($this->cuotasNetas($sale, $detraccionCalc['monto']))
                    ->map(fn (array $cuota) => (new Cuota)
                        ->setMoneda('PEN')
                        ->setMonto($cuota['monto'])
                        ->setFechaPago($cuota['fecha']))
                    ->all()
            );
        }

        if ($detraccionCalc['aplica']) {
            $invoice->setDetraccion(
                (new Detraction)
                    ->setCtaBanco($this->cuentaDetraccion())
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

    protected function buildCompany(): Company
    {
        $companySetting = CompanySetting::current();

        return (new Company)
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
    }

    protected function buildClient(Sale $sale): GreenterClient
    {
        return (new GreenterClient)
            ->setTipoDoc($this->tipoDocCatalogo06($sale->client->tipo_documento))
            ->setNumDoc($sale->client->numero_documento)
            ->setRznSocial($sale->client->razon_social)
            ->setAddress((new Address)->setDireccion($sale->client->direccion_fiscal ?? '-'));
    }

    /**
     * Construye la Nota de Crédito (07) o de Débito (08) que afecta a una
     * factura o boleta. Usa el importe persistido de la nota (con IGV) en una
     * sola línea con la descripción del motivo, sin fabricar montos.
     */
    public function buildNote(ElectronicDocument $note): Note
    {
        if (! in_array($note->tipo, ['nota_credito', 'nota_debito'], true)) {
            throw new RuntimeException("GreenterService::buildNote solo soporta notas de credito o debito (recibido: {$note->tipo}).");
        }

        $original = $note->cpeAfectado;

        if (! $original || $note->importe === null) {
            throw new RuntimeException('La nota no tiene comprobante afectado o importe persistido.');
        }

        $note->loadMissing('sale.client');
        $sale = $note->sale;
        $esCredito = $note->tipo === 'nota_credito';

        $importe = (float) $note->importe;
        $descripcion = $esCredito ? $this->descripcionMotivoCredito($note->motivo_catalogo) : $this->descripcionMotivoDebito($note->motivo_catalogo);

        // S10: el motivo 13 (penalidades, R.S. 000048-2026) es inafecto al IGV.
        // Los demas motivos heredan la afectacion gravada (10) del original.
        $esInafecto = $note->motivo_catalogo === '13';
        $base = $esInafecto ? $importe : round($importe / 1.18, 2);
        $igv = $esInafecto ? 0.0 : round($importe - $base, 2);
        $tipAfeIgv = $esInafecto ? '30' : '10';
        $porcentajeIgv = $esInafecto ? 0.0 : 18.00;
        $mtoBaseIgv = $esInafecto ? 0.0 : $base;

        $detail = (new SaleDetail)
            ->setCodProducto($esCredito ? 'NC-01' : 'ND-01')
            ->setUnidad('ZZ')
            ->setCantidad(1)
            ->setDescripcion($descripcion)
            ->setMtoValorUnitario($base)
            ->setMtoValorVenta($base)
            ->setMtoBaseIgv($mtoBaseIgv)
            ->setPorcentajeIgv($porcentajeIgv)
            ->setIgv($igv)
            ->setTipAfeIgv($tipAfeIgv)
            ->setTotalImpuestos($igv)
            ->setMtoPrecioUnitario($importe);

        // S5/S6: usa la fecha persistida en la nota (no now()), para que los
        // reintentos no cambien la fecha de emision del XML.
        $fechaEmision = $note->fecha_emision ?? now();

        return (new Note)
            ->setUblVersion('2.1')
            ->setTipoDoc($esCredito ? '07' : '08')
            ->setSerie($note->serie)
            ->setCorrelativo((string) $note->correlativo)
            ->setFechaEmision($fechaEmision)
            ->setTipDocAfectado($original->tipo === 'factura' ? '01' : '03')
            ->setNumDocfectado("{$original->serie}-{$original->correlativo}")
            ->setCodMotivo($note->motivo_catalogo)
            ->setDesMotivo($descripcion)
            ->setTipoMoneda('PEN')
            ->setCompany($this->buildCompany())
            ->setClient($this->buildClient($sale))
            ->setMtoOperGravadas($esInafecto ? 0.0 : $base)
            ->setMtoOperInafectas($esInafecto ? $importe : 0.0)
            ->setMtoIGV($igv)
            ->setTotalImpuestos($igv)
            ->setMtoImpVenta($importe)
            ->setDetails([$detail])
            ->setLegends([
                (new Legend)
                    ->setCode('1000')
                    ->setValue($this->numeroEnLetras->convertir($importe)),
            ]);
    }

    /**
     * Comunicación de baja de un comprobante aceptado (S7). Las facturas y
     * sus notas (serie F...) van en una Comunicación de Baja (RA); las
     * boletas y sus notas (serie B...) en un Resumen Diario (RC) con estado
     * 3 = anulado. La fecha de generación es la de emisión del comprobante.
     */
    public function buildBaja(ElectronicDocument $document, string $motivo, int $correlativo): Voided|Summary
    {
        $tipoDoc = match ($document->tipo) {
            'factura' => '01',
            'boleta' => '03',
            'nota_credito' => '07',
            'nota_debito' => '08',
            default => throw new RuntimeException("No se da de baja un documento de tipo {$document->tipo}."),
        };
        $fechaEmision = $document->fecha_emision ?? $document->created_at ?? now();

        if (! self::seInformaPorResumen($document)) {
            return (new Voided)
                ->setCorrelativo((string) $correlativo)
                ->setFecGeneracion($fechaEmision)
                ->setFecComunicacion(now())
                ->setCompany($this->buildCompany())
                ->setDetails([
                    (new VoidedDetail)
                        ->setTipoDoc($tipoDoc)
                        ->setSerie($document->serie)
                        ->setCorrelativo((string) $document->correlativo)
                        ->setDesMotivoBaja(mb_substr($motivo, 0, 100)),
                ]);
        }

        // Los montos y el cliente salen del mismo armado que el comprobante.
        $document->loadMissing('sale.client', 'sale.items.product', 'sale.items.service', 'sale.installments', 'cpeAfectado');
        $comprobante = in_array($document->tipo, ['nota_credito', 'nota_debito'], true)
            ? $this->buildNote($document)
            : $this->buildInvoice($document->sale, $document);

        $detalle = (new SummaryDetail)
            ->setTipoDoc($tipoDoc)
            ->setSerieNro("{$document->serie}-{$document->correlativo}")
            ->setClienteTipo($comprobante->getClient()?->getTipoDoc())
            ->setClienteNro($comprobante->getClient()?->getNumDoc())
            ->setEstado('3')
            ->setTotal((float) $comprobante->getMtoImpVenta())
            ->setMtoOperGravadas((float) $comprobante->getMtoOperGravadas())
            ->setMtoOperInafectas((float) $comprobante->getMtoOperInafectas())
            ->setMtoOperExoneradas((float) $comprobante->getMtoOperExoneradas())
            ->setMtoIGV((float) $comprobante->getMtoIGV());

        if ($document->cpeAfectado) {
            $detalle->setDocReferencia((new Document)
                ->setTipoDoc($document->cpeAfectado->tipo === 'factura' ? '01' : '03')
                ->setNroDoc("{$document->cpeAfectado->serie}-{$document->cpeAfectado->correlativo}"));
        }

        return (new Summary)
            ->setCorrelativo((string) $correlativo)
            ->setFecGeneracion($fechaEmision)
            ->setFecResumen(now())
            ->setMoneda('PEN')
            ->setCompany($this->buildCompany())
            ->setDetails([$detalle]);
    }

    /**
     * Boletas y sus notas (series B...) se anulan por Resumen Diario.
     */
    public static function seInformaPorResumen(ElectronicDocument $document): bool
    {
        return str_starts_with(strtoupper($document->serie), 'B');
    }

    public static function descripcionMotivoCredito(?string $codigo): string
    {
        // S9: Catalogo 09 completo (01 al 13) segun SUNAT.
        // El 07 es "devolucion por item" (no "devolucion total", que es el 06).
        return match ($codigo) {
            '01' => 'ANULACION DE LA OPERACION',
            '02' => 'ANULACION POR ERROR EN EL RUC',
            '03' => 'CORRECCION POR ERROR EN LA DESCRIPCION',
            '04' => 'DESCUENTO GLOBAL',
            '05' => 'DESCUENTO POR ITEM',
            '06' => 'DEVOLUCION TOTAL',
            '07' => 'DEVOLUCION PARCIAL',
            '08' => 'BONIFICACION',
            '09' => 'DISMINUCION EN EL VALOR',
            '10' => 'OTROS CONCEPTOS',
            '11' => 'AJUSTES DE OPERACIONES DE EXPORTACION',
            '12' => 'AJUSTES AFECTOS AL IVAP',
            '13' => 'CORRECCION DE LA DESCRIPCION O DEL MONTO NETO PENDIENTE DE PAGO',
            default => 'AJUSTE DEL COMPROBANTE',
        };
    }

    public static function descripcionMotivoDebito(?string $codigo): string
    {
        // S3: Catalogo 10 actualizado por R.S. 000048-2026 (vigente 1/08/2026).
        // 03 = Otros conceptos (ya NO incluye penalidades).
        // 13 = Penalidades, inafectas al IGV.
        return match ($codigo) {
            '01' => 'INTERESES POR MORA',
            '02' => 'AUMENTO EN EL VALOR',
            '03' => 'OTROS CONCEPTOS',
            '11' => 'AJUSTES DE OPERACIONES DE EXPORTACION',
            '12' => 'AJUSTES AFECTOS AL IVAP',
            '13' => 'PENALIDADES',
            default => 'AJUSTE DEL COMPROBANTE',
        };
    }

    protected function buildDetail(SaleItem $item): SaleDetail
    {
        $productOrService = $item->product ?? $item->service;
        $product = $item->product;
        $tipAfeIgv = ($product !== null && $product->tipo_afectacion_igv !== null)
            ? $product->tipo_afectacion_igv
            : ($item->aplica_igv ? '10' : '30');
        $esGravado = $tipAfeIgv === '10';

        if ($esGravado) {
            ['base' => $valorVenta, 'igv' => $igvLinea] = PrecioConIgv::desglosar((float) $item->subtotal);
            $precioUnitario = (float) $item->precio_unitario;
            $valorUnitario = round($precioUnitario / (1 + PrecioConIgv::TASA), 10);
            $porcentajeIgv = 18.00;
            $mtoBaseIgv = $valorVenta;
        } else {
            $valorVenta = (float) $item->subtotal;
            $precioUnitario = (float) $item->precio_unitario;
            $valorUnitario = $precioUnitario;
            $igvLinea = 0.0;
            $porcentajeIgv = 0.0;
            $mtoBaseIgv = 0.0;
        }

        $detail = (new SaleDetail)
            ->setCodProducto($productOrService->codigo)
            ->setUnidad($this->unidadCatalogo03($productOrService))
            ->setCantidad((float) $item->cantidad)
            ->setDescripcion($productOrService->nombre)
            ->setMtoValorUnitario($valorUnitario)
            ->setMtoValorVenta($valorVenta)
            ->setMtoBaseIgv($mtoBaseIgv)
            ->setPorcentajeIgv($porcentajeIgv)
            ->setIgv($igvLinea)
            ->setTipAfeIgv($tipAfeIgv)
            ->setTotalImpuestos($igvLinea)
            ->setMtoPrecioUnitario($precioUnitario);

        if ((float) $item->descuento > 0) {
            $detail->setDescuentos([
                (new Charge)
                    ->setCodTipo('00')
                    ->setMontoBase(round((float) $item->cantidad * $valorUnitario, 2))
                    ->setMonto(round((float) $item->descuento / (1 + PrecioConIgv::TASA), 2)),
            ]);
        }

        return $detail;
    }

    /**
     * Contado o crédito (RS 193-2020). A crédito, el monto neto pendiente no
     * incluye la detracción: esa parte el cliente la deposita en el Banco de
     * la Nación.
     */
    protected function buildFormaPago(Sale $sale, float $detraccion = 0.0): FormaPagoContado|FormaPagoCredito
    {
        if (! $sale->esCredito()) {
            return new FormaPagoContado;
        }

        return new FormaPagoCredito(round((float) $sale->total - $detraccion, 2), 'PEN');
    }

    /**
     * Cuotas que van a SUNAT: deben sumar el monto neto pendiente, así que la
     * detracción se descuenta desde la última cuota hacia atrás. Las cuotas
     * internas de cobranza no cambian.
     *
     * @return list<array{monto: float, fecha: \DateTimeInterface}>
     */
    protected function cuotasNetas(Sale $sale, float $detraccion): array
    {
        $cuotas = $sale->installments
            ->map(fn ($installment) => ['monto' => (float) $installment->monto, 'fecha' => $installment->fecha_vencimiento])
            ->values()
            ->all();
        $porDescontar = round($detraccion, 2);

        for ($i = count($cuotas) - 1; $i >= 0 && $porDescontar > 0; $i--) {
            $descuento = min($cuotas[$i]['monto'], $porDescontar);
            $cuotas[$i]['monto'] = round($cuotas[$i]['monto'] - $descuento, 2);
            $porDescontar = round($porDescontar - $descuento, 2);
        }

        return array_values(array_filter($cuotas, fn (array $cuota) => $cuota['monto'] > 0));
    }

    /**
     * Cuenta de detracciones del Banco de la Nación (va en el XML).
     */
    protected function cuentaDetraccion(): string
    {
        $cuenta = trim((string) CompanySetting::current()->cuenta_detraccion);

        if ($cuenta === '') {
            throw new RuntimeException('Falta la cuenta de detracción del Banco de la Nación: regístrala en Configuración > Datos de la empresa.');
        }

        return $cuenta;
    }

    protected function tipoDocCatalogo06(string $tipoDocumento): string
    {
        return match ($tipoDocumento) {
            'ruc' => '6',
            'dni' => '1',
            Client::TIPO_DOCUMENTO_VARIOS => '0',
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
