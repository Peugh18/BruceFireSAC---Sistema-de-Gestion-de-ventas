# Prompt: corregir los hallazgos del auditor externo (2026-10-07)

Pega esto en Codex o Gemini, con el proyecto `D:\TiomiguelonGgs\Documents\BRUCE FIRE\BruceFireSacv2` abierto en la rama `fix/correcciones-auditoria-completa`.

```
Lee CLAUDE.md, .ai/rules/general.md, docs/ai/PROYECTO.md §4 y docs/ai/SUNAT.md.

PRIMERO: hay 2 correcciones de Claude sin commit: app/Actions/Sales/RevertSale.php (bloqueo e idempotencia: dos anulaciones a la vez no duplican el stock) y tests/Feature/FaseDEvidenciasYVisitasTest.php (helper renombrado a usuarioDeVisita: el nombre usuarioConRol repetido rompía TODA la suite con un error fatal). Verifica con `php -l` y haz commit de esos 2 archivos.

LUEGO, para CADA hallazgo del auditor: verifica en el código si es real (cita archivo:línea). Si es real, corrígelo con el cambio mínimo correcto y una prueba Pest. Si no lo es, explica por qué en una línea.
1. Doble emisión ante SUNAT: si se pierde el CDR tras un timeout, EditarVentaEmitida emitiría un segundo comprobante por la misma venta. Un comprobante ya enviado (aunque sin CDR) nunca se reemplaza: primero se consulta su estado (ConsultCdrService de Greenter, con el cliente TLS de GreenterSunatClient).
2. Comprobantes 'pendiente' o 'excepcion' por error de red que billing:enviar-programados nunca reintenta: reintentar con el MISMO XML firmado o consultar su CDR, para no vencer el plazo legal.
3. GRE (GreApiClient y su servicio): un error de comunicación no es un rechazo; solo codRespuesta 99 lo es. Lo demás queda pendiente y se reintenta.
4. Detracción `> 700` vs `>= 700`: la norma (Anexo 3 de la R.S. 183-2004/SUNAT) dice "mayor a S/ 700". Si el código ya usa "mayor a", NO lo cambies; solo infórmalo.
5. Carrera al tomar órdenes de trabajo (dos técnicos, la misma orden): tomarla con lockForUpdate o una condición atómica.
6. Envío a SUNAT no idempotente (un doble clic envía dos veces): bloqueo por documento (lock en la BD o Cache::lock).
7. Revisa que ninguna consulta de Cobranzas o del dashboard calcule el saldo sin restar monto_acreditado (Installment::saldo() sí lo resta).
8. Token de APIsPeru filtrado al navegador vía el mensaje de una excepción (RucLookupService y su controlador): al usuario, un mensaje genérico; al log, sin el token.
9. Aislamiento por sede roto en las consultas de inventario o escaneo: aplica el filtro de sede existente (visiblePara, almacenRestringidoId, sedeRestringidaId).

Reglas: no corras la suite completa; solo las pruebas del área, y solo si MySQL responde. Al final: pint --dirty, phpstan (0 errores, sin baseline) y `npx tsc --noEmit` si tocaste el frontend. Commit "Fix: hallazgos del auditor externo" (sin push) y una entrada en docs/ai/BITACORA.md.
```

Después, con MySQL encendido en Laragon, corre `composer ci:check`. Ahora que el error fatal está corregido, las pruebas deberían poder ejecutarse.
