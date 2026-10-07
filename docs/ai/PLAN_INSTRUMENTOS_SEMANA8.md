# Instrumentos de recolección de datos — Plan de elaboración

> **Para quien retome:** ejecutar secuencialmente con `executing-plans`, guardar cada bloque y comprobarlo antes de continuar. Primero leer este plan, `docs/ai/PLAN.md` y la última entrada de `BITACORA.md`. El usuario pidió planificar primero; este turno no autoriza presentar la elaboración como terminada. No se necesitan agentes adicionales ni una nueva ronda de decisiones ya resueltas.

**Objetivo:** actualizar el Word de instrumentos de Semana 8 para recoger datos de exactamente cinco indicadores VI y cinco VD, con ocho instrumentos principales utilizables y resultados pendientes hasta aplicación real.

**Estructura:** un único DOCX con introducción breve, matriz de relación, ocho instrumentos (ficha técnica + instrucciones + formatos de registro + cálculo), apoyos de diagnóstico y validación separados, referencias y control de versiones. Compartir un formato no mezcla mediciones: I02 separa sus secciones y I05 separa ventas/cotizaciones.

**Herramientas:** skill `documents:documents`, Python del runtime con python-docx/lxml; Word para exportación y revisión de campos si sigue disponible, Poppler para inspección visual. Usar `humanizer` al revisar la redacción final. La imagen generada es una propuesta visual, no fuente de datos ni plantilla normativa.

**Especificación:** acuerdos de este chat y documentos finales verificados el 07/10/2026. Estado actual de instrumentos: borrador anterior A1–A13 (21 páginas), aún con SUS VI06, PTR y catorce días; requiere concordancia con métricas v1.2.

## Archivos y fuentes

- Destino: `D:/TiomiguelonGgs/Documents/BRUCE FIRE/Entregables/Semana 8/Instrumentos de medición - BRUCE FIRE - Semana 8.docx`.
- Métricas vigentes: `D:/TiomiguelonGgs/Documents/BRUCE FIRE/Entregables/Semana 8/Métricas de Calidad - BRUCE FIRE - Semana 8.docx`.
- Informe: `D:/TiomiguelonGgs/Documents/BRUCE FIRE/Entregables/Semana 6/Informe del Proyecto Final - BRUCE FIRE.docx`, principalmente 3.7–3.10 y 5.14.
- Matriz: `D:/TiomiguelonGgs/Documents/BRUCE FIRE/Entregables/Semana 2/Matriz_de_Consistencia_Experimental_BRUCE_FIRE.docx`.
- Cronograma, DRS v3.1, Business Case y entregables previos: localizar sus archivos actuales bajo `D:/TiomiguelonGgs/Documents/BRUCE FIRE/Entregables/`; conservar los 122 códigos REQ verificados y revisar los 11 RNF por separado.
- Investigación ya guardada: `C:/Users/migue/.codex/visualizations/2026/10/07/01a11448-9708-7930-933f-5d7d8b01b516/investigacion-semana8/Investigacion previa Semana 8.md` y `semana8-metricas-instrumentos-v2/Investigacion instrumentos tesis y etapa de medicion.md` dentro del mismo directorio base.
- Área de elaboración: crear `instrumentos-semana8-v3/` dentro de ese directorio base; conservar allí originales, scripts, avances, revisiones y comprobación final.
- Plan persistente: `D:/TiomiguelonGgs/Documents/BRUCE FIRE/BruceFireSacv2/docs/ai/PLAN_INSTRUMENTOS_SEMANA8.md`.

## Reglas comunes

- Cinco VI: VI01 PRI, VI02 PCF, VI03 PEU, VI04 SUS, VI05 TRP. SUS pertenece a los cinco; PTR no es una sexta ficha.
- Cinco VD: VD01 TRV, VD02 TEC, VD03 PCP, VD04 PDS, VD05 PCR. Sus metas son reducciones relativas de 30%, 25%, 40%, 40% y aumento de 20 puntos porcentuales, respectivamente.
- Nombre de VD04 aprobado después de la entrega v1.2: **Porcentaje de productos con diferencias de stock en el inventario (PDS)**. En la ejecución ajustar solo esta redacción donde corresponda en métricas/informe/matrices; no cambiar comparación, unidad, fórmula u OE4.
- Diseño durante desarrollo: registrar versión, alcance y fecha. El protocolo preparado, una aplicación real y un resultado consolidado son estados diferentes.
- Cada ficha técnica explica el instrumento; el cuestionario/lista/tabla de registro recoge los datos. Ambos son partes del mismo instrumento.
- Mantener los campos que se llenan al aplicar disponibles y rotulados. Los procedimientos, casos previstos, fórmulas y criterios deben estar redactados, no quedar vacíos.
- Sin resultados, respuestas, firmas, credenciales, aprobaciones ni evidencias inventadas. Solo incorporar registros reales si existen y se verifican; los ejemplos didácticos irán identificados y fuera de los resultados.
- No interpretar un caso pendiente como fallido ejecutado ni aprobado; informar cobertura y pendientes aparte. Denominador cero significa no evaluable.
- Cronograma vigente: C1 16/10–16/11; C2 27/11–03/12; cierre posterior según cronograma. Confirmar con el archivo actual al ejecutar. Semana 13 fue una consulta, no cambio de fechas.
- Seguimiento de clientes: quitar catorce días del borrador antiguo. El equipo fijará una duración común viable antes de aplicar C1/C2; se dejará un campo de protocolo a aprobar, no se elegirá una duración ficticia.
- Muestras planificadas: E1 216, E2 88, E3 las mismas 216 ventas/comprobantes, E4 343, E5 295; total distinto planificado 942. Distinguir muestra prevista, real y denominador elegible; confirmar marco de cada periodo.
- Fuentes PMI/ISO explican medición y calidad; no atribuirles una plantilla obligatoria de ocho instrumentos o fichas técnicas. El número es una decisión de organización del proyecto.

## Modelo común de ficha técnica

Código/nombre y versión; indicador(es) y objetivo específico relacionado; propósito; técnica y fuentes; unidad de análisis/población/muestra prevista y efectiva; datos/campos; quién aplica y quién revisa (roles, nombres solo si confirmados); momento/condiciones; procedimiento; criterio de validez y tratamiento de incidencias; cálculo/interpretación y evidencia; estado de revisión/validación. Referenciar el procedimiento o el formato para evitar duplicar páginas.

## Los ocho instrumentos y sus formatos

| Código y relación                          | Formato que se elaborará                                                                                                                                                                                                                                                                      | Comprobación propia                                                                                                                                                                                       |
| ------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| I01 · Requisitos → VI01                    | Lista de los 122 REQ actuales con módulo, requisito, versión, implementado/parcial/no implementado, verificación pendiente y evidencia; consolidación RI/122.                                                                                                                                 | Conjunto de códigos idéntico al DRS, sin duplicados ni RNF sumados; solo completo demostrado cuenta en RI.                                                                                                |
| I02 · Pruebas funcionales → VI02 y VI03    | Plan/lista de casos trazado a REQ; registro por caso con precondiciones, pasos, datos de prueba, esperado, obtenido, fecha/versión, aprobado/fallido/pendiente, evidencia y defecto. Sección de diez intentos inválidos y controles válidos del borrador, con estado antes/después y mensaje. | PCF=CA/CE×100 y PEU=EP/EI×100 con consolidaciones separadas. CP, ejecutados, pendientes y cobertura visibles; error500/bloqueo silencioso no acredita prevención.                                         |
| I03 · SUS → VI04                           | Cuestionario de diez ítems, escala1–5, participante codificado/rol/versión/tareas/fecha; hoja de puntuación individual y consolidación por versión.                                                                                                                                           | Mantener alternancia y fuente Brooke; incompletos no se imputan. Recodificar impares x−1 y pares5−x, suma×2,5; puntos0–100, no porcentaje.                                                                |
| I04 · Respuesta → VI05                     | Registro por escenario y versión: entorno/red/carga/datos; cinco intentos preparatorios separados y treinta medidos como propuesta; inicio/fin, segundos, resultado, error/timeout y evidencia.                                                                                               | TRP por escenario sobre respuestas válidas; conservar todos los intentos y fallos aparte. Revisar cada lectura frente al RNF-05, además del promedio.                                                     |
| I05 · Cronometraje comercial → VD01 y VD02 | Dos formatos diferenciados para ventas y cotizaciones: etapa, operación codificada, complejidad, operador/observador, inicio/fin, duración, interrupciones, validez/exclusión y evidencia; dos consolidaciones por etapa.                                                                     | Minutos, límites de inicio/fin iguales a métricas; no mezclar tareas. No reconstruir tiempos históricos sin registros de inicio/fin verificables.                                                         |
| I06 · Cobranzas → VD03                     | Comprobante/venta codificados, fecha de emisión/corte, contado/crédito, monto facturado ajustado, pagos aplicados, saldo, notas/ajustes y evidencia; resumen C1/C2.                                                                                                                           | PCP=MP/MF×100; periodo/antigüedad comparables. Incluye saldo pendiente, no solo vencido ni cantidad de comprobantes; MF>0.                                                                                |
| I07 · Inventario → VD04                    | Registro producto–almacén con corte, saldo registrado, conteo físico, diferencia, movimientos/conciliación y evidencia; resumen por producto distinto.                                                                                                                                        | PDS=PD/PT×100: producto cuenta una vez aunque tenga diferencia en varios almacenes; no sumar unidades faltantes/sobrantes. Mismos productos/almacenes en C1/C2.                                           |
| I08 · Clientes → VD05                      | Cliente codificado único, elegibilidad, vencimiento, aviso y evidencia, inicio/fin de ventana común, recompra/comprobante, estado de seguimiento y evidencia; consolidación por etapa.                                                                                                        | PCR=CR/CA×100 solo con ventanas cerradas y datos verificables; sin baseC1 verificable no se inventa0. Comparación en puntos porcentuales. Aviso no equivale a recompra ni presupone mensajes automáticos. |

## Reutilización del borrador y apoyos

Reusar A1→I01; A2+A9→I02; A4→I03; A3→I04; A5→I05; A6→I06; A7→I07; A8→I08. A10/A11 permanecen como apoyos de diagnóstico (entrevista y observación), separados del conteo de instrumentos principales. A12/A13 se reorganizan como formatos de revisión de expertos y piloto, con ítems/campos de los ocho instrumentos y resultados pendientes.

Conservar el diseño de 3.10: revisión prevista de tres expertos, valoración por criterio y V de Aiken≥0,80 como criterio declarado del proyecto; para SUS, piloto con recodificación antes de evaluar consistencia y alfa≥0,70 como criterio previsto. El piloto de lecturas objetivas usa repeticiones/conciliación y tolerancias justificadas, no alfa aplicado a todo. No afirmar que cualquier umbral por sí solo certifica validez.

## Bloques de ejecución y puntos para retomar

### Bloque 1 · Base, relación y fichas técnicas

- [ ] Leer fuentes vigentes, calcular SHA y copiar originales antes de editar. Confirmar dónde aparecen las referencias al antiguo A1–A13/VI06/PTR/catorce días.
- [ ] Preparar contrato del DOCX y matriz diez indicadores→ocho instrumentos→datos→fórmula; preparar ocho fichas técnicas con instrucciones concretas.
- [ ] Conservar el inventario de 122 REQ y las partes de plantilla utilizables. Guardar borrador y lista de pendientes en `instrumentos-semana8-v3/estado.md`.
- [ ] Comprobar diez indicadores, ocho instrumentos, tres dimensiones VI y nombres/objetivos concordantes. Registrar bloque terminado en PLAN/BITACORA.

### Bloque 2 · Medición de software I01–I04

- [ ] Elaborar listas, casos, SUS y registros de respuesta previstos en la tabla anterior, corrigiendo la numeración antigua.
- [ ] Cubrir cada REQ mediante relación de casos; ampliar casos cuando un requisito necesite varios. No limitar la cobertura a los escenarios ilustrativos.
- [ ] Comprobar requisitos únicos, estados, denominadores y ejemplos de cálculo fuera de resultados: SUS extremos0/100 y neutral50; pendiente no entra como aprobado; error/timeout no se convierte en0s.
- [ ] Guardar y revisar visualmente las páginas de este bloque antes de seguir.

### Bloque 3 · Procesos operativos I05–I08

- [ ] Elaborar los dos registros de cronometraje y los formatos de cobranzas, inventario y clientes; conservar criterios del documento de métricas.
- [ ] Crear consolidaciones C1/C2 con muestra prevista/observada, casos válidos/exclusiones, evidencia y estado de aplicación.
- [ ] Comprobar unidades minutos/segundos/soles/puntos/porcentajes, ceros, producto duplicado en almacenes, clientes duplicados, ventanas abiertas y datos faltantes.
- [ ] Guardar borrador y actualizar `estado.md`; los resultados reales permanecen pendientes si no hay evidencia.

### Bloque 4 · Apoyos, fuentes y concordancia

- [ ] Incorporar diagnóstico, revisión de expertos y piloto como apoyos; conservar la fuente del SUS y referencias verificadas ya investigadas.
- [ ] Retirar referencias obsoletas a VI06, PTR y catorce días; revisar las referencias de instrumentos de métricas/informe/matrices y corregir exclusivamente nombres/códigos que lo requieran.
- [ ] Aplicar el nuevo nombre VD04 aprobado en el chat en los documentos relacionados con cambio de redacción mínimo, sin reformular OE4 o medición.
- [ ] Revisar redacción con `humanizer` manteniendo términos, fórmulas y criterios técnicos.

### Bloque 5 · Verificación y entrega

- [ ] Comprobar relaciones diez→ocho, requisitos, fórmulas, campos llenables, códigos, fuentes, versiones y estados pendientes. Probar cálculos con ejemplos identificados, no con resultados fabricados.
- [ ] Exportar el DOCX a PDF para QA y revisar todas las páginas: tablas, títulos, ecuaciones, cortes, tamaños y espacio para completar. Usar Word/Poppler si LibreOffice sigue no disponible; corregir y revisar páginas afectadas.
- [ ] Verificar partes de plantilla conservadas, nombres de archivos, hash y ausencia de modificaciones ajenas. No fijar un número artificial de páginas.
- [ ] Instalar el Word con respaldo y hash comprobado; si cambió algún documento relacionado, respaldarlo y verificarlo también.
- [ ] Actualizar PLAN/BITACORA/DECISIONES y Obsidian con archivos entregados, QA y pendientes reales. Abrir el Word y entregar un resumen breve. Sin commit/push ni cambios al sistema.

## Criterio de aceptación

Una persona puede aplicar cada instrumento siguiendo las instrucciones y registrar los datos necesarios para calcular las diez métricas. Se distingue diseño, ejecución y resultados; existe trazabilidad al DRS, objetivos, fórmulas e informe. Hay ocho instrumentos principales, registros separados en I02/I05, apoyos diferenciados y revisión visual completa. Resultados, expertos, piloto y plazo común aún no establecidos no aparecen como ya realizados.

**Estado al guardar este plan:** planificación completada; elaboración v3 pendiente. Primer bloque siguiente: base, matriz de relación y ocho fichas técnicas. Guardar avances por bloque permite retomar tras una interrupción o cambio de contexto.
