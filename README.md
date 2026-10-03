# Bruce Fire S.A.C. — Sistema de Gestión de Ventas

Sistema web a medida para **Bruce Fire S.A.C.**, empresa de Trujillo (Perú) dedicada a la venta, recarga y mantenimiento de extintores y equipos contra incendios. Centraliza todo el ciclo comercial y operativo: desde la cotización y la venta con **facturación electrónica ante SUNAT**, hasta el control de inventario por número de serie, el trabajo técnico en planta y en campo, y la predicción de recompra de clientes con inteligencia artificial.

> Proyecto real para una empresa en operación, validado contra el ambiente BETA de SUNAT y construido con Laravel 13, React 19 e Inertia 3.

---

## ✨ Funcionalidades principales

### 🧾 Ventas y facturación electrónica

- **Cotizaciones** con vigencia, envío al cliente y conversión directa a venta.
- **Ventas por escaneo de series**: cada extintor vendido se identifica por su código interno `BF-EQ-XXXXXX` y se descuenta del stock en tiempo real.
- **Facturas y boletas electrónicas** firmadas y enviadas a SUNAT con [Greenter](https://greenter.dev) (XML UBL 2.1, CDR y representación impresa en PDF con QR).
- **Reglas SUNAT aplicadas en el sistema**: la factura solo se emite a RUC _Activo y Habido_; boletas a _Clientes varios_ hasta S/ 700; detracción automática en servicios.
- **Envío diferido con ventana de revisión**: el comprobante queda _por enviar_ unas horas (configurable) y se puede corregir —factura ↔ boleta, cliente o anulación— **sin emitir nota de crédito**. Un proceso programado lo envía y reintenta si SUNAT no responde.
- **Notas de crédito y débito**, notas de venta internas, cobranzas y control de caja.
- El comprobante agrupa las unidades del mismo producto en una sola línea; las series quedan para el control interno.

### 👥 Clientes

- Búsqueda por RUC, DNI, nombre o razón social (por palabras, en cualquier orden).
- Alta de clientes con **autocompletado desde RENIEC / SUNAT** (dirección, estado y condición del contribuyente), consultando la API solo cuando se registra un cliente nuevo.

### 📦 Almacén e inventario

- **Recepciones de proveedor** con control de mercadería conforme / no conforme.
- Registro por unidad: capacidad, número de serie del fabricante, marca y año de fabricación.
- **Kardex** completo, ajustes de stock con motivo, consulta rápida por serie y **stickers con código de barras** en PDF.
- Multi-sede: cada trabajador opera solo el stock de su sede.

### 🔧 Técnicos de planta y de campo

- Órdenes de servicio, recojos, recepción en taller, checklist técnico, registro de deficiencias y trabajos adicionales.
- Instalaciones, inspecciones y actas de entrega en campo.
- **Certificados** (operatividad, prueba hidrostática, instalación) generados en PDF.
- **Alertas de vencimiento** de recargas y pruebas hidrostáticas para la venta proactiva.

### 📊 Gerencia e inteligencia de negocio

- Dashboard con KPIs, reportes comerciales y de inventario.
- Gestión de usuarios, roles y permisos, sedes y configuración de la empresa.
- **Auditoría** de las acciones sensibles del sistema.
- **Modelo predictivo de recompra**: puntúa a cada cliente según su probabilidad de volver a comprar, entrenado con el histórico real de ventas.

---

## 👤 Roles del sistema

| Rol                   | Qué hace                                                                       |
| --------------------- | ------------------------------------------------------------------------------ |
| **Gerente**           | Supervisa KPIs, reportes, usuarios, sedes, catálogo, auditoría e IA            |
| **Vendedor**          | Clientes, cotizaciones, ventas, facturación, cobranzas, alertas y certificados |
| **Almacén**           | Recepciones, stock y Kardex, ajustes y stickers                                |
| **Técnico de Planta** | Recepción de equipos, checklist, deficiencias y mantenimiento                  |
| **Técnico de Campo**  | Recojos, inspecciones, instalaciones y entregas                                |

---

## 🛠️ Stack tecnológico

| Capa        | Tecnologías                                                                                    |
| ----------- | ---------------------------------------------------------------------------------------------- |
| Backend     | PHP 8.3 · **Laravel 13** · Laravel Fortify (autenticación, 2FA, passkeys) · Spatie Permission  |
| Frontend    | **React 19** · **Inertia.js 3** · TypeScript · **Tailwind CSS 4** · Vite 8 · Laravel Wayfinder |
| Facturación | Greenter (SUNAT) · DomPDF · códigos QR y de barras                                             |
| Datos       | SQLite (desarrollo) / MySQL (producción)                                                       |
| Calidad     | **Pest 4** (más de 370 pruebas automatizadas) · Laravel Pint · PHPStan · GitHub Actions        |

---

## 🏗️ Arquitectura

- **Acciones de dominio** (`app/Actions/*`): cada operación de negocio vive en su propia clase —`CreateSale`, `ConfirmSale`, `EmitElectronicDocument`, `EditarVentaEmitida`, `CreateReception`…— para mantener los controladores delgados y la lógica testeable.
- **Servicios** (`app/Services/*`): integración con SUNAT, generación de PDF, cálculo de detracciones y clasificación de respuestas.
- **Rutas por rol** (`routes/vendedor.php`, `routes/almacen.php`, `routes/gerente.php`, …) con permisos por equipo y restricción por sede.
- **Frontend SPA sin API REST**: Inertia renderiza páginas React desde los controladores; Wayfinder genera funciones TypeScript tipadas para cada ruta.
- **Tareas programadas**: envío de comprobantes, vencimiento de cotizaciones, recálculo de alertas y puntuación de clientes.

---

## 🚀 Instalación local

**Requisitos:** PHP 8.3+, Composer, Node.js 20+ y npm.

```bash
git clone https://github.com/Peugh18/BruceFireSAC---Sistema-de-Gestion-de-ventas.git
cd BruceFireSAC---Sistema-de-Gestion-de-ventas

composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate --seed
npm run build
```

Levantar el entorno de desarrollo (servidor, Vite y colas):

```bash
composer run dev
```

Para el envío automático de comprobantes a SUNAT, en otra terminal:

```bash
php artisan schedule:work
```

### Usuarios de demostración

El seeder crea un usuario por rol, todos con la contraseña `password`:

| Rol               | Correo                        |
| ----------------- | ----------------------------- |
| Gerente           | `gerente@brucefire.pe`        |
| Vendedor          | `vendedor@brucefire.pe`       |
| Almacén           | `almacen@brucefire.pe`        |
| Técnico de Planta | `tecnico.planta@brucefire.pe` |
| Técnico de Campo  | `tecnico.campo@brucefire.pe`  |

### Facturación electrónica

Por defecto el sistema trabaja contra el **ambiente BETA de SUNAT**. Las credenciales y el certificado digital se configuran en el `.env` (`SUNAT_*`, `BILLING_*`). La ventana de revisión antes del envío se ajusta con `BILLING_ENVIO_DIFERIDO_HORAS` (por defecto 6 horas).

---

## ✅ Pruebas

```bash
php artisan test
```

La suite cubre ventas, facturación y envío diferido a SUNAT, notas de crédito y débito, cotizaciones, clientes, recepciones de almacén, permisos por rol y por sede, entre otros módulos.

---

## 👨‍💻 Autor

**Jose Miguel Urcia Guevara** — Gerente de Proyecto y desarrollador.

Proyecto desarrollado para Bruce Fire S.A.C. (Trujillo, Perú) en el marco del curso de Gestión de Proyectos de Software de la Universidad Privada del Norte.
