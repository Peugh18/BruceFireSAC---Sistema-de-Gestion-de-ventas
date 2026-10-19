# APIs externas que usa Bruce Fire

> Revisado el **2026-10-18** contra el código y `composer show`. Aquí van **solo los nombres** de las variables de `.env`, nunca sus valores.
> Reglas de SUNAT (qué y cuándo enviar): [SUNAT_FACTURACION.md](SUNAT_FACTURACION.md).

| API                   | Para qué                                                                           | Paquete / servicio           | Código                                                                |
| --------------------- | ---------------------------------------------------------------------------------- | ---------------------------- | --------------------------------------------------------------------- |
| **Greenter**          | Factura, boleta y notas: arma el XML UBL 2.1, lo firma y lo envía por SOAP a SUNAT | `greenter/lite` **5.3.0**    | `app/Services/Billing/GreenterService.php`, `GreenterSunatClient.php` |
| **Greenter GRE API**  | Guía de remisión por la API REST de SUNAT                                          | `greenter/gre-api` **1.0.2** | `app/Services/Billing/GreApiClient.php`                               |
| **APIsPeru (dniruc)** | Autocompletar clientes con los datos de RENIEC (DNI) y SUNAT (RUC)                 | HTTP directo                 | `app/Services/Sunat/RucLookupService.php`                             |

Librerías de apoyo, sin servicio externo: `barryvdh/laravel-dompdf` 3.1 (PDF), `endroid/qr-code` 5.1 (QR del comprobante), `phpoffice/phpword` 1.1 (Word del certificado) y `picqer/php-barcode-generator` 3.3 (código de barras de los stickers).

---

## 1. Greenter — factura, boleta y notas

- **Documentación oficial:** [greenter.dev](https://greenter.dev/) (uso, producción y FAQ) · [GitHub greenter/lite](https://github.com/thegreenter/lite).
- **Flujo:**
    1. `GreenterService` arma el comprobante (`Invoice`, `Note`, `SaleDetail`, `FormaPago`, `Detraction`, leyendas).
    2. Lo firma con el certificado.
    3. `GreenterSunatClient` lo envía con `BillSender`.
    4. SUNAT responde con el **CDR**, que se guarda junto al comprobante.
- **Por qué hay un cliente propio:** el `See` de Greenter abre el SOAP con `verify_peer=false` y no deja cambiarlo. `GreenterSunatClient` arma el mismo SoapClient **verificando el certificado TLS** de SUNAT (probado en beta y producción el 2026-10-06).
- **Endpoints** (constantes de `Greenter\Ws\Services\SunatEndpoints`):

    | Ambiente        | Comprobantes                                                           |
    | --------------- | ---------------------------------------------------------------------- |
    | Beta (pruebas)  | `https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService`         |
    | Producción      | `https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService`           |
    | Consulta de CDR | `https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService` |

- **Variables de `.env`** (`config/billing.php`):
    - `SUNAT_BETA`: `true` para pruebas, `false` para producción.
    - `SUNAT_RUC`, `SUNAT_USUARIO_SOL` y `SUNAT_CLAVE_SOL`: usuario SOL **secundario** con permiso de facturación.
    - `SUNAT_CERT_PATH` y `SUNAT_CERT_PASSWORD`: certificado `.pem` (clave privada y pública). Si SUNAT entrega un `.pfx`, se convierte antes; ver [greenter.dev/production](https://greenter.dev/production/).
    - `BILLING_COMPANY_*`: RUC, razón social, nombre comercial, ubigeo y dirección del emisor.
    - `BILLING_ENVIO_DIFERIDO_HORAS`: horas antes del envío automático (6 por defecto).
- **Pruebas:** usan `tests/Fixtures/certificates/test-certificate.pem` y nunca envían a SUNAT. Los certificados reales no se tocan.
- **Errores comunes:**
    - "Could not connect to host" o "Bad Gateway": casi siempre faltan certificados raíz o `ca-certificates` está desactualizado, no es un error del código (FAQ de Greenter).
    - "El usuario no tiene permiso": el usuario SOL secundario no está habilitado o aún no se activó (puede tardar 24 h).
    - Rechazo con un código de error: el mensaje viene en el CDR. Se corrige con una nota o se emite de nuevo, según el caso.

## 2. Greenter GRE API — guía de remisión

- **Documentación:** [GitHub greenter/gre-api](https://github.com/thegreenter/gre-api) · [greenter.dev](https://greenter.dev/).
- **Flujo:**
    1. Se obtiene un token OAuth2 (`AuthApi::getToken`, grant `password`, scope `https://api-cpe.sunat.gob.pe`). El usuario es RUC + usuario SOL y se usan el `client_id` y el `client_secret` de la aplicación.
    2. Se envía la GRE (`CpeApi`), que devuelve un **ticket**.
    3. Se consulta el ticket hasta tener el CDR.

    El token se guarda en caché 55 minutos.

- **Credenciales propias:** `client_id` y `client_secret` se generan en el menú SOL. Son **distintas** de la Clave SOL de facturación.
- **Hosts:**
    - Por defecto, `config/billing.php` apunta al **sandbox** `https://gre-test.nubefact.com/v1`.
    - Producción de SUNAT: autenticación en `https://api-seguridad.sunat.gob.pe/v1` y envío en `https://api-cpe.sunat.gob.pe/v1`. Confirmar en la guía oficial de la GRE de [cpe.sunat.gob.pe](https://cpe.sunat.gob.pe/guias-y-manuales) antes de pasar a producción.
- **Variables de `.env`:**
    - `SUNAT_GRE_CLIENT_ID` y `SUNAT_GRE_CLIENT_SECRET`;
    - `SUNAT_GRE_AUTH_HOST` y `SUNAT_GRE_API_HOST`;
    - `SUNAT_GRE_SIMULADO`: solo para desarrollo, acepta sin enviar;
    - `GRE_MOTIVO_ORDENES` (catálogo 20), `GRE_DESCRIPCION_RECOJO` y `GRE_DESCRIPCION_ENTREGA`, pendientes de confirmar con la contadora.
- **Uso en el negocio:** es una excepción (despacho por bus u otros supuestos que confirme la contadora), no parte del delivery diario.

## 3. APIsPeru — consulta de DNI y RUC

- **Documentación:** [apisperu.com](https://apisperu.com/) (servicio de terceros, no de SUNAT).
- **Endpoints** (GET, con `?token=`):
    - DNI de 8 dígitos: `https://dniruc.apisperu.com/api/v1/dni/{dni}`
    - RUC de 11 dígitos: `https://dniruc.apisperu.com/api/v1/ruc/{ruc}`
- **Variable de `.env`:** `APISPERU_TOKEN` (`config/services.php` → `services.apisperu.token`).
- **Cómo lo usa el sistema:**
    - Si el cliente ya está en la base, no consulta (salvo que se pida actualizar).
    - Espera 3 s para conectar y 8 s para responder.
    - Un 404 o 422 significa "el documento no existe".
    - La respuesta se recuerda 1 hora, y al guardar el cliente su estado SUNAT sale de esa consulta del servidor, no del formulario.
- **Si la API no responde:** se puede registrar al cliente igual y completar sus datos después, nunca inventarlos. El estado "activo/habido" de un RUC se vuelve a consultar con "Consultar SUNAT" en la ficha del cliente.
- **Riesgo:** es un proveedor externo. Si cambia sus condiciones o su URL, solo hay que tocar `RucLookupService`.
