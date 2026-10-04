# Manual de Almacén — Bruce Fire

## Recepción de proveedor

1. **Recepciones → Nueva recepción**: proveedor, guía o factura, fecha y sede.
2. Agrega cada producto con cantidad recibida y cantidad conforme. Si hay no conformes, escribe el motivo. Con el lector de barras: escanea en **Escanear código de barras** y cada lectura suma una unidad (sirve el código del fabricante de los EPP y repuestos). Si sale "no está registrado", pide al Gerente que lo agregue al producto.
    - Solo registras recepciones en **tu almacén**. Al corregir una recepción no se pueden retirar unidades que ya se vendieron.
3. Para productos con serie, llena por unidad: **capacidad, N° de serie del fabricante, marca y año**. Usa **Copiar fila 1 a todas** si son iguales.
4. Al guardar se generan los códigos internos **BF-EQ-XXXXXX** y el stock entra al almacén.

## Stock y Kardex

- **Stock y Kardex**: stock de tu almacén y todos los movimientos. Las salidas (ventas, traslados, consumos del taller) salen en negativo.
- **Ajustes de Stock**: corrige el stock de tu almacén con un motivo de al menos 10 caracteres. No se puede dar de baja más de lo que hay, ni reingresar un extintor vendido; los extintores nuevos entran por Recepciones.
- **Traslados**: no se traslada más de lo que hay. El Gerente elige el almacén de origen.
- **EPP** (cascos, guantes, lentes, botas, chalecos): cada modelo y talla es un producto sin serie con su código de barras; se cuentan por cantidad.

## Stickers y consulta

- **Stickers de Barras**: imprime las etiquetas con código de barras de las unidades.
- **Consulta Rápida**: busca una serie para ver dónde está y en qué estado.

## EPP con lote y vencimiento

- Los EPP y consumibles que el Gerente marcó con **Lote y vencimiento** (guantes, mascarillas, filtros) se reciben indicando el **lote** y la fecha de **vencimiento** que trae la caja. No se recibe nada ya vencido.
- Si el producto se compra por **caja** (por ejemplo, 1 CAJA = 50 pares), en Recepciones escribe cuántas cajas llegaron: el stock sube en pares o unidades.
- Al vender, el sistema saca solo **lo que vence primero** y **nunca** un lote vencido. Si anulan la venta, vuelve al mismo lote.
- En **Stock** cada producto muestra sus lotes; en **Inicio** ves los lotes vencidos o que vencen en 60 días.
- Un lote vencido se da de baja en **Ajustes**: elige el producto y el lote.
- Si te equivocaste en el vencimiento, corrígelo editando la recepción.
