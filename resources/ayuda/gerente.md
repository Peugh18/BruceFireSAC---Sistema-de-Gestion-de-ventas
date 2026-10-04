# Manual del Gerente — Bruce Fire

## Productos y servicios

- **Productos**: crea cada producto con código, nombre, precio (con IGV incluido), **categoría** y si es **con serie** (extintores) o sin serie (bases, repuestos, EPP). El stock no se escribe aquí: entra por Recepciones del Almacén.
- **EPP y repuestos con código de barras**: registra cada modelo y talla como un producto sin serie y escanea su **código de barras del fabricante**. Desde ahí Almacén y los vendedores solo escanean. Un código no se puede repetir en dos productos.
- **Servicios**: crea los servicios (recarga, mantenimiento, prueba hidrostática, instalación, capacitación) con su precio. Son los que los vendedores eligen en ventas y órdenes de servicio.

## Usuarios, roles y sedes

- **Usuarios y Roles**: crea a cada trabajador con su rol (Vendedor, Almacén, Técnico de Planta, Técnico de Campo) y su **sede**. Cada uno solo opera lo de su sede.
- **Sedes**: almacenes y tiendas; una tienda vende con el stock del almacén que tenga asignado.

## Caja, cobranzas y reportes

- **Caja Consolidada**: movimientos de caja de todas las sedes; faltantes y sobrantes del mes por separado.
- **Cobranzas**: cuotas pendientes y vencidas de las ventas emitidas, por el saldo que falta cobrar.
- **Inicio**: solo cuenta ventas emitidas (ni borradores ni anuladas); el stock de productos sin serie sale del Kardex, igual que en Almacén.
- **Reportes**: comercial e inventario, exportables a PDF.

## Configuración de la empresa

- Datos de la empresa, logo, cuentas bancarias, firmantes de los certificados (técnico, administrador, ingeniero y CIP) e instructor de capacitación.
- **Diseño del comprobante** (Configuración → Empresa): color de la marca, página web, mensaje de agradecimiento, condiciones de venta o garantía y leyenda de pie. Vale para todas las facturas y boletas; pulsa **Vista previa de la factura** para verla antes de emitir.
- **Logo**: PNG con fondo transparente (o JPG), entre 150×60 y 3000 px por lado. Ideal horizontal, unos 600×200 px. En el comprobante se ajusta solo, sin deformarse.
- Si cambias el diseño, las facturas ya emitidas salen con el diseño nuevo la próxima vez que se descargan (el XML enviado a SUNAT no cambia).

## Auditoría

- Registro de acciones sensibles: quién creó, anuló o corrigió ventas, comprobantes y certificados.
