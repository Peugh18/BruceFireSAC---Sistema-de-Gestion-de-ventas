# Prompt para volver a auditar BRUCE FIRE

Actúa como auditor independiente del sistema web BRUCE FIRE S.A.C. Revisa el código y el funcionamiento real de la versión actual. Tu tarea es auditar y presentar evidencia; no implementar propuestas ni cambiar reglas de negocio por tu cuenta.

Repositorio: `D:\TiomiguelonGgs\Documents\BRUCE FIRE\BruceFireSacv2`. Rama de trabajo: `fix/flujo-cotizacion-auditoria`. Revisa primero el estado de Git: estos cambios pueden estar sin commit. No audites solamente el SHA base. Stack Laravel 13/PHP 8.3, Inertia 3/React 19, MySQL y Pest.

## Antes de probar

Lee `docs/ai/PROYECTO.md`, `docs/ai/PLAN.md`, las últimas entradas de `docs/ai/BITACORA.md` y `.ai/rules/`. Consulta la auditoría anterior como hipótesis, no como evidencia de que el fallo sigue vigente. Usa únicamente SUNAT beta. No hagas commit, push, instalación de dependencias ni cambio a producción.

Los datos actuales son de prueba. Usa registros identificables con un prefijo de auditoría y conserva evidencia; no borres datos históricos ajenos. Para pruebas destructivas usa registros creados para ese caso. Las pruebas automatizadas usan `bruce_fire_testing`, nunca deben limpiar la base de desarrollo. Verifica servidor y assets actuales, sin reiniciar procesos que no pertenezcan a este proyecto.

## Reglas de negocio confirmadas directamente por el dueño

- Recarga: cotización primero; desde ella se inicia la orden de taller sin vender todavía. El vendedor incorpora los adicionales detectados por el técnico y confirma con el cliente. Cuando todo está definido, convierte la cotización en factura, boleta o nota de venta interna. Contado o crédito se decide en ventas.
- Precios del catálogo incluyen IGV; vendedor puede corregir precios excepcionales y retirar o cambiar líneas, con historial y sin una aprobación adicional del gerente. Corregir importe no borra movimientos físicos ni permite alterar comprobantes ya enviados a SUNAT.
- Registrar quién autorizó, canal, fecha y precio del adicional. Si cambia lo acordado, se confirma otra vez con el cliente. El importe autorizado corresponde a esa línea, no al total de toda la cotización.
- Vendedor puede registrar un hallazgo con foto desde la orden; el diagnóstico y aptitud del equipo corresponden al técnico.
- Código de barras en el extintor: BF-EQ identifica el registro con año, agente, capacidad, marca y serie de fabricante. QR únicamente en el certificado para su verificación pública y vigencia. Etiquetas físicas de vencimiento/instrucciones quedan fuera del software.
- Mantener intervalo hidrostático de cinco años. No renovar esa fecha por una recarga sin prueba realizada. No confundir el texto del régimen de pruebas con la afirmación de que se ejecutó una prueba.
- Detracción por servicio: 022 para recarga, 020 para mantenimiento/reparación, 037 para otros servicios que realmente correspondan; tasa actual 12% cuando aplica. No escoger un código universal por conveniencia. Paquetes combinados o clasificación desconocida requieren revisión, no una asignación automática inventada.
- Cuenta Banco de la Nación existente; vendedor recibe/revisa constancia y comparte captura al gerente por WhatsApp de forma manual. El sistema no envía mensajes. El depósito acreditado debe reducir deuda sin entrar en caja como ingreso recibido por el vendedor.
- Caja de más de 24 horas avisa, no bloquea. Arqueo ciego: no revelar el efectivo esperado antes del cierre.
- Offline está pendiente de definir e implementar; costo/margen está diferido; no mora ni penalidades. No incorporar RFID, QR de equipo ni nuevos módulos como resultado de esta auditoría.

## Casos obligatorios

1. **Cotización y taller antes del cobro.** Crear cotización de recarga, iniciar orden, repetir la acción y verificar una sola orden y ninguna venta/cuota/cobro anticipado. Comprobar cliente, sede, equipos y servicio.
2. **Adicional de S/15.** Registrar hallazgo, cotizar repuesto desde catálogo, editarlo a un precio excepcional, confirmar con cliente y autorizar. Revisar línea vinculada y totales con IGV. No autorizar un importe monetario sin cotizarlo.
3. **Corrección e historial.** Cambiar cantidad/precio o retirar adicional antes de venta: una sola línea, total correcto, autorización anterior en historial y nueva confirmación cuando cambie el acuerdo. No borrar consumos ni crear devoluciones ficticias. Después de facturarlo, impedir corrección por la cotización de origen.
4. **Venta completa sin duplicados.** Convertir cotización con base y adicionales autorizados/resueltos; preservar descuentos y condición de pago. No crear segunda orden. Intentar omitir un adicional, cambiar su importe en el POST, quitar quote_id al editar o cobrarlo en otra venta: debe rechazarse o corregirse por el flujo permitido.
5. **Stock del repuesto.** Técnico consume una sola vez producto/cantidad autorizados; cobrarlo no genera segunda salida. Editar/anular la venta no devuelve al almacén un repuesto que sigue instalado. Probar producto/cantidad distintos, doble clic y stock insuficiente.
6. **Venta original ya emitida.** Adicional nuevo se cotiza aparte y se convierte a su venta vinculada. No modificar el XML enviado ni crear una cuota sin su documento comercial. Probar boleta, factura beta y nota de venta.
7. **Nueve extintores.** Para local y vehículo: escanear nueve BF-EQ distintos, cantidad uno por unidad, datos técnicos completos, stock correcto y certificado con nueve equipos. Serie fabricante y código interno deben aparecer separados; fabricante desconocido no debe inventarse usando BF-EQ. Probar duplicados, otra sede, equipo vendido y búsqueda por prefijo.
8. **Certificados y QR.** Solo ofrecer/emitir certificados de servicios incluidos en la venta. Bloquear URL/POST de un certificado ajeno. Consultar QR público de vigente, vencido y anulado; comprobar privacidad y consistencia de snapshots. Revisar los PDF/Word realmente renderizados y la vigencia/alertas.
9. **Compatibilidad y órdenes anuladas.** Recarga PQS no acepta CO2 ni capacidad explícitamente incompatible. Alta técnica incompleta debe recibir la validación prevista, sin inventar datos. Orden anulada/cerrada no se toma ni recibe equipos/checklist/finalización. Listados y KPIs solo incluyen órdenes atendibles por sede/técnico.
10. **Detracción.** Facturas S/700 y S/700.01; servicio 020/022/037; boleta; productos; servicio sin clasificación; mezcla de códigos/conceptos. No convertir la limitación del emisor en una prohibición legal. Verificar XML, código, monto, porcentaje y cuenta configurada; cambiar catálogo/tasa después no altera la factura emitida.
11. **Constancia y cobranzas.** Contado y crédito: cobro neto y depósito BN separados. Sin constancia real el depósito sigue pendiente. Registrar una constancia, duplicarla, pagar parcialmente, cancelar un registro erróneo y probar otra sede/vendedor. No registrar dos veces ni alterar un arqueo cerrado. Comparar facturación neta, notas de crédito, cobros y saldos con iguales periodos y definiciones.
12. **Traslado y GRE.** Preparar traslado sin mover stock; crear GRE con origen/destino/bienes del traslado. Borrador/enviada/rechazada/aceptada sin CDR o con archivo inexistente bloquean despacho. Aceptada con CDR existente permite una sola salida; recepción genera una sola entrada en destino. Probar stock cambiado, datos de guía manipulados y acceso desde otra sede. No falsificar una aceptación en el flujo real. Si faltan credenciales beta, declarar la limitación y verificar los gates con pruebas automatizadas.

## Método y entrega

Prioriza Pest y solicitudes HTTP para lógica; usa navegador para recorridos reales, teclado, nombres accesibles, errores y presentación de documentos. Un botón habilitado o una página que carga no prueban que una transacción funcione. No concluyas que hay una venta rota porque `sales.medio_pago` sea null: contrasta `sale_payments`, que conserva los pagos definitivos.

Ejecuta pruebas focalizadas antes de la suite completa `composer ci:check`; conserva comandos, resultado y entorno. No agregues errores al baseline de PHPStan. Para accesibilidad distingue los controles revisados de conformidad WCAG integral; no cambies colores durante una auditoría de solo lectura.

Por hallazgo entrega: gravedad, regla esperada, pasos/datos, resultado observado, respuesta HTTP, archivo/línea, comparación de BD/stock/pagos/documento y evidencia. Separa fallo confirmado, riesgo por revisar, hallazgo ya corregido y nueva propuesta fuera de alcance. Incluye matriz de los doce casos con PASS/FAIL/NO PROBADO y limitaciones reales. No inventes precios, cumplimiento normativo, credenciales ni resultados E2E.
