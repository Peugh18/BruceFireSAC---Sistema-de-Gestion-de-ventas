# GUÍA TÉCNICA MAESTRA: FACTURACIÓN ELECTRÓNICA CON GREENTER & SUNAT (PERÚ)

> **Normativa vigente 2026 y estado real del código:** ver `docs/ai/SUNAT.md`. Esta guía explica cómo se usa Greenter; si algo de aquí choca con `docs/ai/SUNAT.md`, manda `docs/ai/SUNAT.md`.

## Bruce Fire S.A.C. — Estándar UBL 2.1

Este documento constituye la fuente técnica oficial y completa para la integración de Facturación Electrónica con **Greenter (UBL 2.1)** y los servicios de **SUNAT** en el sistema Bruce Fire.

---

## 1. INSTALACIÓN Y REQUERIMIENTOS TÉCNICOS

### 1.1 Dependencias Composer

```bash
composer require greenter/lite
```

_(Para códigos QR y utilitarios adicionales: `simplesoftwareio/simple-qrcode`)._

### 1.2 Requisitos del Servidor / PHP

- **PHP:** 8.2 o superior.
- **Extensiones obligatorias en `php.ini`:**
    ```ini
    extension=soap
    extension=openssl
    extension=curl
    extension=fileinfo
    ```

---

## 2. CONFIGURACIÓN Y CERTIFICADO DIGITAL

### 2.1 Endpoints de SUNAT

Greenter provee los endpoints oficiales en `Greenter\Ws\Services\SunatEndpoints`:

- **Beta / Pruebas:** `SunatEndpoints::FE_BETA` (`https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService`)
- **Producción:** `SunatEndpoints::FE_PRODUCCION` (`https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService`). Ojo: `ol-it-wscontain/billConsultService` es el servicio de **consulta**, no el de envío.
- **Consultas CDR:** `SunatEndpoints::FE_CONSULTA_CDR` (`https://e-factura.sunat.gob.pe/ol-it-wsconscdr/billConsultService`)
- **Guías de Remisión (API REST 2022+):**
    - Auth: `https://api-seguridad.sunat.gob.pe/v1`
    - CPE: `https://api-cpe.sunat.gob.pe/v1`

### 2.2 Carga de Certificado Digital (.pem vs .pfx)

#### Caso A: Certificado en formato `.pem` (Recomendado para producción)

```php
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;

$see = new See();
$see->setService(SunatEndpoints::FE_BETA); // o FE_PRODUCCION
$see->setCertificate(file_get_contents(storage_path('app/certificates/certificate.pem')));
$see->setClaveSOL('20600000001', 'USUARIOSOL', 'CONTRASEÑASOL');
```

#### Caso B: Certificado en formato `.pfx` / `.p12`

Si el cliente provee el certificado emitido en `.pfx` con contraseña:

```php
use Greenter\XMLSecLibs\Certificate\X509Certificate;
use Greenter\XMLSecLibs\Certificate\X509ContentType;

$pfxContent = file_get_contents(storage_path('app/certificates/certificate.pfx'));
$password = config('billing.sunat.cert_password');

$certificate = new X509Certificate($pfxContent, $password);
$see->setCertificate($certificate->export(X509ContentType::PEM));
```

---

## 3. FACTURA ELECTRÓNICA (UBL 2.1)

### 3.1 Estructura Estándar Completa (Venta Gravada)

```php
use DateTime;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Company;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;

// 1. Emisor (Bruce Fire S.A.C.)
$address = (new Address())
    ->setUbigueo(config('billing.company.ubigeo')) // Ej: 150101
    ->setDepartamento(config('billing.company.departamento'))
    ->setProvincia(config('billing.company.provincia'))
    ->setDistrito(config('billing.company.distrito'))
    ->setUrbanizacion('-')
    ->setDireccion(config('billing.company.direccion'))
    ->setCodLocal('0000'); // Código de establecimiento anexo SUNAT (0000 = Principal)

$company = (new Company())
    ->setRuc(config('billing.company.ruc'))
    ->setRazonSocial(config('billing.company.razon_social'))
    ->setNombreComercial(config('billing.company.nombre_comercial'))
    ->setAddress($address);

// 2. Cliente Adquiriente (RUC obligado para facturas)
$client = (new Client())
    ->setTipoDoc('6') // Catálogo 06: 6 = RUC
    ->setNumDoc('20123456789')
    ->setRznSocial('CLIENTE EMPRESA S.A.C.')
    ->setAddress((new Address())->setDireccion('Av. Los Claveles 123'));

// 3. Detalle de Ítems
$item = (new SaleDetail())
    ->setCodProducto('EXT-PQS-6KG')
    ->setUnidad('NIU') // Catálogo 03: NIU = Unidad, ZZ = Servicio
    ->setCantidad(2)
    ->setDescripcion('RECARGA Y MANTENIMIENTO EXTINTOR PQS 6KG')
    ->setMtoValorUnitario(50.00)       // Precio unitario sin IGV
    ->setMtoValorVenta(100.00)         // Valor total sin IGV (50 * 2)
    ->setMtoBaseIgv(100.00)            // Base imponible del IGV
    ->setPorcentajeIgv(18.00)          // 18%
    ->setIgv(18.00)                    // Monto de IGV (100 * 0.18)
    ->setTipAfeIgv('10')               // Catálogo 07: 10 = Gravado - Operación Onerosa
    ->setTotalImpuestos(18.00)         // Suma de impuestos en la línea
    ->setMtoPrecioUnitario(59.00);      // Precio unitario con IGV (50 * 1.18)

// 4. Cabecera de la Factura
$invoice = (new Invoice())
    ->setUblVersion('2.1')
    ->setTipoOperacion('0101')         // Catálogo 51: 0101 = Venta Interna
    ->setTipoDoc('01')                 // Catálogo 01: 01 = Factura
    ->setSerie('F001')                 // 4 caracteres (F###)
    ->setCorrelativo('1')              // Hasta 8 dígitos
    ->setFechaEmision(new DateTime('now'))
    ->setFormaPago(new FormaPagoContado())
    ->setTipoMoneda('PEN')             // Catálogo 02: PEN = Soles, USD = Dólares
    ->setCompany($company)
    ->setClient($client)
    ->setMtoOperGravadas(100.00)       // Subtotal gravado
    ->setMtoIGV(18.00)                 // Total IGV
    ->setTotalImpuestos(18.00)
    ->setValorVenta(100.00)            // Total valor venta
    ->setSubTotal(118.00)              // Valor venta + IGV
    ->setMtoImpVenta(118.00)           // Importe Total a pagar
    ->setDetails([$item])
    ->setLegends([
        (new Legend())
            ->setCode('1000')          // Catálogo 52: 1000 = Monto en letras
            ->setValue('SON CIENTO DIECIOCHO CON 00/100 SOLES')
    ]);
```

---

## 4. DETRACCIONES (SPOT) EN SERVICIOS

> **REGLA SUNAT OBLIGATORIA:**
> Los servicios de mantenimiento, recarga y reparación de extintores están sujetos al Sistema de Pago de Obligaciones Tributarias (SPOT) con una tasa del **12%** cuando el importe total del comprobante supera los **S/ 700.00**.

> ⚠️ **PENDIENTE DE CONFIRMAR CON CONTADOR ANTES DE LA ETAPA 3** — el código de bien/servicio del Catálogo 54 usado abajo (`022`) es una hipótesis, no un hecho verificado. Investigando el Anexo 3 de la R.S. 183-2004/SUNAT hay al menos tres códigos candidatos, todos al 12%, y la elección correcta depende de cómo el contador de BRUCE FIRE ya viene declarando estos servicios:
>
> - **`020` — Mantenimiento y reparación de bienes muebles**: el más literal, porque un extintor es un bien mueble y el servicio es justamente mantenimiento/reparación/recarga del mismo.
> - **`022` — Otros servicios empresariales**: el que ya estaba puesto aquí; es más genérico, no específico a "mantenimiento de bienes muebles".
> - **`037` — Demás servicios gravados con IGV**: catálogo residual, solo si ninguno de los anteriores aplica.
>   No se puede decidir por documentación pública genérica — esto se confirma con el contador o con el historial de detracciones que BRUCE FIRE ya viene depositando, antes de codificarlo en `CreditDebitNoteBuilder`/`SaleDocumentBuilder`.

```php
use Greenter\Model\Sale\Detraction;

// Si la factura supera S/ 700 y contiene servicios de mantenimiento/recarga:
$porcentajeDetraccion = 12.00;
$montoTotalFactura = 1180.00;
$montoDetraccion = round($montoTotalFactura * ($porcentajeDetraccion / 100), 2); // 141.60

$detraction = (new Detraction())
    ->setCodBienDetraccion('020') // Catálogo 54: 020 = Mantenimiento y reparación de bienes muebles (VERIFICAR con contador, ver aviso arriba)
    ->setCodMedioPago('001')      // Catálogo 59: 001 = Depósito en cuenta
    ->setCtaBanco('00-000-123456')// Cuenta de Detracciones del Banco de la Nación
    ->setPercent($porcentajeDetraccion)
    ->setMount($montoDetraccion);

$invoice->setDetraccion($detraction);

// Leyenda obligatoria SPOT (Catálogo 52)
$legend = (new \Greenter\Model\Sale\Legend())
    ->setCode('2006')
    ->setValue('Operación sujeta al Sistema de Pago de Obligaciones Tributarias');
$invoice->setLegends([$legend]);
```

---

## 5. FORMAS DE PAGO: CONTADO VS. CRÉDITO (RS 193-2020)

### 5.1 Venta al Contado

```php
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;

$invoice->setFormaPago(new FormaPagoContado());
```

### 5.2 Venta al Crédito con Cuotas

> **REGLA SUNAT:** Si la venta es al crédito, se debe consignar el **Monto Neto Pendiente de Pago** (Total venta menos detracciones/retenciones si aplican) y el detalle de cada cuota con su fecha de vencimiento e importe.

```php
use DateTime;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Cuota;

$totalVenta = 1180.00;
$montoDetraccion = 141.60;
$montoNetoPendiente = $totalVenta - $montoDetraccion; // S/ 1038.40 a pagar al crédito

$invoice->setFormaPago(new FormaPagoCredito($montoNetoPendiente, 'PEN'));

// Desglose en 2 cuotas:
$invoice->setCuotas([
    (new Cuota())
        ->setMoneda('PEN')
        ->setMonto(519.20)
        ->setFechaPago(new DateTime('2026-10-15')),
    (new Cuota())
        ->setMoneda('PEN')
        ->setMonto(519.20)
        ->setFechaPago(new DateTime('2026-11-15')),
]);
```

---

## 6. BOLETA DE VENTA ELECTRÓNICA (UBL 2.1)

Las boletas siguen la misma estructura que la factura con las siguientes diferencias:

- `tipoDoc` = `'03'` (Catálogo 01).
- `serie` = Inicia con `B` (ejemplo: `B001`).
- `client->tipoDoc` = `'1'` para DNI, `'4'` Carnet Extranjería, `'7'` Pasaporte, `'0'` Sin documento (solo hasta S/ 700.00).

```php
// Envío individual directo (Permitido por RS 114-2019/SUNAT, sin necesidad de resumen diario)
$boleta = (new Invoice())
    ->setUblVersion('2.1')
    ->setTipoOperacion('0101')
    ->setTipoDoc('03')
    ->setSerie('B001')
    ->setCorrelativo('1')
    ->setFechaEmision(new DateTime('now'))
    ->setFormaPago(new FormaPagoContado())
    ->setTipoMoneda('PEN')
    ->setCompany($company)
    ->setClient($clientNatural)
    // ... detalles e importes
;
$result = $see->send($boleta);
```

---

## 7. NOTAS DE CRÉDITO Y DÉBITO ELECTRÓNICAS

> **REGLA SUNAT:** La Nota de Crédito nunca es una venta independiente; debe referenciar un CPE previamente emitido y aceptado. Para devoluciones de bienes, se debe incluir el detalle de los ítems exactos que reingresan al inventario.

```php
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;

$note = (new Note())
    ->setUblVersion('2.1')
    ->setTipoDoc('07')                // Catálogo 01: 07 = Nota de Crédito
    ->setSerie('FC01')                // F### o FC## para Facturas; B### o BC## para Boletas
    ->setCorrelativo('1')
    ->setFechaEmision(new DateTime('now'))
    ->setTipDocAfectado('01')         // Catálogo 01: 01 = Factura, 03 = Boleta
    ->setNumDocfectado('F001-123')    // Serie y correlativo afectado
    ->setCodMotivo('01')              // Catálogo 09: 01 = Anulación de la operación
    ->setDesMotivo('ERROR EN DATOS / ANULACIÓN DE SERVICIO')
    ->setTipoMoneda('PEN')
    ->setCompany($company)
    ->setClient($client)
    ->setMtoOperGravadas(100.00)
    ->setMtoIGV(18.00)
    ->setTotalImpuestos(18.00)
    ->setValorVenta(100.00)
    ->setSubTotal(118.00)
    ->setMtoImpVenta(118.00)
    ->setDetails([$item]);

$result = $see->send($note);
```

### Principales Motivos de Nota de Crédito (Catálogo 09)

- `01`: Anulación de la operación.
- `02`: Anulación por error en el RUC.
- `03`: Corrección por error en la descripción.
- `04`: Descuento global.
- `05`: Descuento por ítem.
- `06`: Devolución total.
- `07`: Devolución parcial.

---

## 8. GUÍAS DE REMISIÓN ELECTRÓNICA REMITENTE (GRE 2022+ API REST)

Desde diciembre de 2022, la GRE se envía por la **API REST de SUNAT** con credenciales de aplicación creadas en Clave SOL:

```php
use Greenter\Api;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\DespatchDetail;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Despatch\Driver;
use Greenter\Model\Despatch\Shipment;
use Greenter\Model\Despatch\Vehicle;

$api = new Api([
    'auth' => 'https://api-seguridad.sunat.gob.pe/v1',
    'cpe' => 'https://api-cpe.sunat.gob.pe/v1',
]);
$api->setCertificate(file_get_contents(storage_path('app/certificates/certificate.pem')));
$api->setClaveSOL('20600000001', 'USUARIOSOL', 'CONTRASEÑASOL');
$api->setApiCredentials(config('billing.sunat.client_id'), config('billing.sunat.client_secret'));

$envio = (new Shipment())
    ->setCodTraslado('01')            // Catálogo 20: 01 = Venta, 08 = Traslado para recojo/servicio
    ->setDesTraslado('TRASLADO DE EXTINTORES PARA MANTENIMIENTO')
    ->setModTraslado('02')            // Catálogo 18: 02 = Transporte privado, 01 = Público
    ->setFecTraslado(new DateTime('tomorrow'))
    ->setPesoTotal(60.00)
    ->setUndPesoTotal('KGM')
    ->setVehiculo((new Vehicle())->setPlaca('ABC-123'))
    ->setChoferes([
        (new Driver())
            ->setTipo('Principal')
            ->setNombres('JUAN PEREZ')
            ->setLicencia('Q12345678')
    ])
    ->setPartida(new Direction('150101', 'AV. LOS HEROES 100'))
    ->setLlegada(new Direction('150115', 'JR. INDUSTRIAL 200'));

$despatch = (new Despatch())
    ->setVersion('2022')
    ->setTipoDoc('09')                // Catálogo 01: 09 = Guía de Remisión Remitente
    ->setSerie('T001')                // Serie inicia con T
    ->setCorrelativo('1')
    ->setFechaEmision(new DateTime('now'))
    ->setCompany($company)
    ->setDestinatario($client)
    ->setEnvio($envio)
    ->setDetails([
        (new DespatchDetail())
            ->setCodigo('EXT-01')
            ->setDescripcion('EXTINTOR PQS 6KG')
            ->setUnidad('NIU')
            ->setCantidad(5)
    ]);

$result = $api->send($despatch);
```

---

## 9. CICLO DE ENVÍO, PROCESAMIENTO Y LECTURA DEL CDR

Este es el diagrama de flujo y código canónico para evitar errores de conexión y de rechazo:

```php
try {
    $result = $see->send($invoice);
    $xmlSigned = $see->getFactory()->getLastXml();

    // Guardar siempre el XML generado
    Storage::disk('local')->put("xml/{$invoice->getName()}.xml", $xmlSigned);

    // 1. Error de Conexión / Timeout / SoapFault
    if (! $result->isSuccess()) {
        $error = $result->getError();

        return [
            'estado' => 'error_comunicacion',
            'codigo_error' => $error->getCode(),
            'mensaje_error' => $error->getMessage(),
            'puede_reintentar' => true,
        ];
    }

    // 2. SUNAT Respondió y entregó CDR
    $cdrZip = $result->getCdrZip();
    Storage::disk('local')->put("cdr/R-{$invoice->getName()}.zip", $cdrZip);

    $cdr = $result->getCdrResponse();
    $code = (int) $cdr->getCode();
    $descripcion = $cdr->getDescription();
    $hash = $cdr->getHash(); // DigestValue para el QR

    // CLASIFICACIÓN OFICIAL DE RESPUESTAS SUNAT:
    if ($code === 0) {
        $notes = $cdr->getNotes();
        if (! empty($notes)) {
            // Aceptada pero con advertencias legales que deben corregirse a futuro
            return [
                'estado' => 'observado',
                'codigo_cdr' => $code,
                'descripcion' => $descripcion,
                'observaciones' => implode(' | ', $notes),
                'hash' => $hash,
            ];
        }

        return [
            'estado' => 'aceptado',
            'codigo_cdr' => $code,
            'descripcion' => $descripcion,
            'hash' => $hash,
        ];
    }

    if ($code >= 2000 && $code <= 3999) {
        // RECHAZO TRIBUTARIO FORMAL POR SUNAT
        // Regla: Este correlativo NO tiene valor legal y NO puede volverse a enviar.
        // Se debe corregir el motivo y emitir una nueva factura con nuevo correlativo.
        return [
            'estado' => 'rechazado',
            'codigo_cdr' => $code,
            'descripcion' => $descripcion,
            'puede_reintentar' => false,
        ];
    }

    // Código 0100 a 1999: Excepciones del sistema SUNAT
    return [
        'estado' => 'error_comunicacion',
        'codigo_cdr' => $code,
        'descripcion' => $descripcion,
        'puede_reintentar' => true,
    ];

} catch (\Throwable $e) {
    return [
        'estado' => 'error_comunicacion',
        'mensaje_error' => $e->getMessage(),
        'puede_reintentar' => true,
    ];
}
```

---

## 10. GENERACIÓN DEL CÓDIGO QR TRIBUTARIO OFICIAL

Para la representación impresa (PDF o ticket), SUNAT exige que el QR contenga los siguientes datos separados por `|`:

```
RUC_EMISOR|TIPO_DOC|SERIE|CORRELATIVO|TOTAL_IGV|TOTAL_VENTA|FECHA_EMISION|TIPO_DOC_CLIENTE|NUM_DOC_CLIENTE|HASH_XML|
```

**Ejemplo de cadena para el QR:**

```text
20600000001|01|F001|00000001|18.00|118.00|2026-09-18|6|20123456789|t1k2j3h4k5l6=|
```

---

## 11. TABLA RESUMEN DE CATÁLOGOS SUNAT UTILIZADOS

| Catálogo | Nombre                      | Valores Frecuentes                                                                   |
| :------: | :-------------------------- | :----------------------------------------------------------------------------------- |
|  **01**  | Tipo de Documento           | `01` = Factura, `03` = Boleta, `07` = Nota Crédito, `08` = Nota Débito, `09` = GRE   |
|  **02**  | Tipo de Moneda              | `PEN` = Soles, `USD` = Dólares Americanos                                            |
|  **03**  | Unidad de Medida            | `NIU` = Unidad / Bien, `ZZ` = Servicio, `KGM` = Kilogramo                            |
|  **06**  | Documento de Identidad      | `6` = RUC (11 dígitos), `1` = DNI (8 dígitos), `4` = Carnet Ext., `7` = Pasaporte    |
|  **07**  | Afectación al IGV           | `10` = Gravado - Op. Onerosa, `20` = Exonerado, `30` = Inafecto                      |
|  **09**  | Motivo Nota de Crédito      | `01` = Anulación de operación, `06` = Devolución total, `07` = Devolución parcial    |
|  **10**  | Motivo Nota de Débito       | `01` = Penalidad / otros cobros, `02` = Aumento de valor                             |
|  **18**  | Modalidad de Traslado (GRE) | `01` = Transporte público, `02` = Transporte privado                                 |
|  **20**  | Motivo de Traslado (GRE)    | `01` = Venta, `08` = Traslado para recojo / reparación / mantenimiento               |
|  **51**  | Tipo de Operación           | `0101` = Venta Interna, `0102` = Exportación, `1001` = Venta sujeta a Detracción     |
|  **54**  | Códigos de Detracción       | `022` = Otros servicios empresariales / Mantenimiento, `020` = Mantenimiento muebles |
|  **59**  | Medios de Pago Detracción   | `001` = Depósito en cuenta del Banco de la Nación                                    |

---

## 12. CASOS ESPECIALES DE GREENTER — CUÁLES APLICAN A BRUCE FIRE (investigado 2026-09-19)

Se revisó toda la documentación oficial de [greenter.dev](https://greenter.dev/) (`/usage`, `/starter`, `/production`, `/faq`, `/packages/xml`, y los ejemplos de exonerada, gratuita, descuento-linea, percepcion, anticipo, detraccion, exportacion, icbper, boleta, contingencia, forma-pago) para decidir, caso por caso, qué se implementa y qué no. Regla aplicada: no se construye nada para un escenario que el negocio no tiene hoy (ver Convenciones del proyecto, "no over-engineering").

### 12.1 Aplican al alcance actual — sí se implementan

**Descuento por línea** (`greenter.dev/examples/descuento-linea`) — sí aplica: BRUCE FIRE puede dar descuentos comerciales en ítems de una venta o cotización.

```php
use Greenter\Model\Sale\Charge;

$item->setDescuentos([
    (new Charge())
        ->setCodTipo('00')   // Catálogo 53: 00 = Descuento por ítem
        ->setMontoBase(200)  // Base sobre la que se calcula
        ->setFactor(0.10)    // 10%
        ->setMonto(20),      // Monto final del descuento
]);
```

**Anticipo** (`greenter.dev/examples/anticipo`) — sí aplica: los servicios de instalación (sistema de detección, cámaras, pozo a tierra) suelen cobrar un adelanto antes de ejecutar la obra, y luego facturar el saldo. Greenter soporta esto de forma nativa referenciando el comprobante del anticipo:

```php
use Greenter\Model\Sale\Prepayment;

$invoice->setAnticipos([
    (new Prepayment())
        ->setTipoDocRel('01')      // Catálogo 12: tipo de doc del comprobante del anticipo
        ->setNroDocRel('F001-50')  // Serie-correlativo de la factura de anticipo ya emitida
        ->setTotal(100.00),
]);
$invoice->setTotalAnticipos(100.00);
// setMtoImpVenta() debe reflejar el total YA descontado el anticipo.
```

Esto encaja con la sección 12.2 (Comercial → conversión a venta) del documento maestro: cuando una Cotización de instalación se acepta con anticipo, se emite primero la factura del anticipo y luego la factura final referenciándola.

### 12.2 No aplican hoy — no se implementan (documentado para no reabrir la duda)

- **Exonerada** (`tipAfeIgv = 20`) y **exportación** (`tipoOperacion = 0200`, moneda USD, cliente extranjero): BRUCE FIRE vende y presta servicios dentro del Perú a clientes gravados con IGV; no hay operación exonerada ni exportación en el modelo de negocio descrito. No se implementa.
- **Percepción** (`tipoOperacion = 2001`, objeto `SalePerception`): aplica solo a agentes de percepción designados por SUNAT (normalmente combustibles, importación de bienes específicos). BRUCE FIRE no es agente de percepción. No se implementa.
- **ICBPER** (impuesto a bolsas plásticas): no vende bolsas plásticas. No se implementa.
- **Gratuita** (`setMtoOperGratuitas`, leyenda `1002`): no hay un caso de negocio dictado por el usuario para regalar extintores/servicios. Si en el futuro se decide dar una recarga de cortesía por garantía, ahí se retoma este ejemplo — por ahora no se construye.

### 12.3 Relevante pero no es prioridad de las Etapas 1-4 — queda para cuando se llegue a Facturación SUNAT

- **Contingencia** (emisión offline cuando cae la conexión con SUNAT): serie numérica (`'0001'`) en vez de alfanumérica, y dos leyendas obligatorias en el PDF impreso ("Emisor electrónico obligado" + "Comprobante de Pago emitido en contingencia"). Es una operación de oficina (no de campo), así que la probabilidad de necesitarla es baja, pero es barata de dejar prevista en el diseño del `SaleDocumentBuilder` (un flag `contingencia: bool` que cambia el formato de serie y agrega las leyendas). Se implementa solo si ocurre una caída real de conectividad, no antes — [procedimiento oficial de SUNAT](https://cpe.sunat.gob.pe/informacion_general/procedimiento_contingencia).
- **Resumen diario de boletas vs. envío individual**: Greenter permite ambas (`greenter.dev/faq`); BRUCE FIRE ya decidió enviar boletas individualmente igual que las facturas (más simple, sin lógica de tickets/consulta de estado por lote). No se implementa el flujo de resumen diario a menos que el volumen de boletas lo justifique.

### 12.4 Notas operativas para la Etapa 3 (implementación real)

- **Certificado**: producción exige `.pem` (clave privada + pública). Si SUNAT entrega `.pfx`, convertir antes ([greenter.dev/production](https://greenter.dev/production/)). El certificado público (`.cer`) se registra aparte en el portal SUNAT.
- **Usuario secundario SOL**: debe crearse con permiso de Facturación Electrónica y puede tardar hasta 24 h en activarse — coordinar esto con anticipación, no el mismo día que se quiera salir a producción.
- **GRE usa credenciales distintas**: desde dic. 2022 la Guía de Remisión usa la API REST de SUNAT con credenciales de aplicación propias (no reutiliza `ClaveSOL` de facturación) — ya está reflejado en la sección 8 de este documento y coincide con lo ya implementado en `ShippingService` (ver Documento Maestro §77.1).
- **Errores de conectividad** ("Bad Gateway", "Could not connect to host"): según el FAQ oficial, la causa habitual es el certificado SSL de SUNAT no instalado o `ca-certificates` desactualizado en el servidor, no un bug del código — primer paso de diagnóstico antes de tocar el `GreenterService`.
- **`greenter/xml` vs `greenter/lite`**: el proyecto debe usar `greenter/lite` (ya así en `composer.json`), que incluye generación de XML + firma digital + envío. `greenter/xml` es un paquete de más bajo nivel (solo genera XML sin firmar) que `lite` ya consume internamente — no se instala aparte.
