# Recolección de datos: formatos específicos para BRUCE FIRE

Investigación del 07/10/2026. Se complementa la investigación previa y el plan de instrumentos. Alcance de este turno: investigar y explicar cómo adaptar los instrumentos; no se elaboró ni aplicó una nueva versión de Word/Excel.

## Hallazgos y fuentes

1. **UPN, tesis de Gamboa Trujillo, anexo 2, página impresa98.** El texto indexado relaciona indicadores del proceso de ventas con ficha de observación y usa cuestionario para satisfacción; declara elaboración propia en su matriz. Es evidencia de instrumentos distintos según indicador, no una guía institucional vigente ni un modelo que deba copiarse literalmente.
   https://repositorio.upn.edu.pe/bitstream/handle/11537/27741/Gamboa%20Trujillo%2C%20Katherine%20Jennyfer.pdf?isAllowed=y&sequence=1
2. **UPN, Ramírez Bazán/Lázaro Calderón, control de inventarios en Rasecc, página impresa55.** La guía de análisis documental indexada incluye empresa, área, periodo, objetivo, documentos específicos (reporte de inventarios, Kardex, cotizaciones, órdenes y operaciones), existencia y resultado. Sirve como ejemplo de personalización de fuentes al proceso estudiado.
   https://repositorio.upn.edu.pe/backend/api/core/bitstreams/acc8448e-3753-4fd4-b057-32599b62a795/content
3. **Zéniz et al.,2024, Universidad Nacional de Trujillo, publicado en Innovación y Software/Universidad La Salle.** El artículo sobre un sistema web de gestión comercial identifica guía de entrevista, ficha de registro, cronómetro y cuestionario; en página impresa195 describe registros y pretest/postest. Se abrió el PDF de24páginas y se consultó el texto pertinente. Sus tamaños muestrales y tablas presentan discrepancias internas, por lo que no se adoptan cifras ni análisis como patrón; solo la organización de recolección.
   https://revistas.ulasalle.edu.pe/innosoft/article/download/150/261/
4. **Brooke,1996, fuente original de SUS.** Diez ítems, alternancia de positivos/negativos, respuestas1–5 y cálculo con recodificación y multiplicación por2,5. Aplicación después de uso y antes de discusión. Personalizar portada, instrucciones, versión, roles y tareas; mantener contenido de la escala. Una traducción operativa no se declara validada por estar basada en SUS.
   https://hci-studies.org/methods-and-measures/downloads/SUS_Brooke1996.pdf
5. **Galicia, Balderrama y Edel,2017, Apertura/Universidad de Guadalajara.** Artículo de investigación sobre revisión de instrumentos por expertos: organizar ítems por dimensión, explicar propósito y valorar claridad, coherencia, relevancia y suficiencia; ajustar según observaciones. Sustenta revisión de contenido, no exige el mismo coeficiente o número de expertos para toda investigación.
   https://apertura.cugdl.udg.mx/index.php/apertura/article/view/993/852

**Alcance de acceso:** en las dos tesis UPN se consultaron extractos indexados con tablas y páginas identificadas. La apertura directa de los PDFs y del registro devolvió error en la herramienta; no se afirma haber revisado las tesis completas. No se usó Scribd como prueba de una norma UPN actual. Los PDFs del artículo y de SUS y el texto del artículo UDG sí fueron accesibles.

## Decisión de diseño para nuestro proyecto

Siete instrumentos de elaboración propia/adaptación metodológica (I01,I02,I04,I05,I06,I07,I08), con campos definidos por las fórmulas y procedimientos aprobados. I03 usa SUS con fuente y versión lingüística identificadas. «Personalizado» significa datos y pasos específicos del estudio, no únicamente logo/colores. Es propuesta del proyecto sustentada en los ejemplos; ocho instrumentos no es una exigencia universal de tesis o PMBOK.

| Instrumento      | Recolección prevista                                                                       | Personalización necesaria                                                                                                                                                            |
| ---------------- | ------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| I01 requisitos   | Demostración y revisión de evidencia por requisito                                         | Los122REQ del DRS actual, módulo, versión, estado, evidencia; RNF separados.                                                                                                         |
| I02 pruebas      | Ejecutar caso previamente definido y registrar obtenido contra esperado                    | Casos de ventas, cotizaciones, cobranzas, stock y alertas; REQ, datos, precondiciones, pasos, evidencia; sección inválidos/controlesválidos para PEU, consolidación separada de PCF. |
| I03 SUS          | Respuestas de usuarios tras usar tareas y versión identificadas                            | Portada BRUCE FIRE, participante codificado, rol, tareas, fecha y versión; conservar diezítems y puntuación.                                                                         |
| I04 respuesta    | Medir desde acción hasta respuesta utilizable, conservando fallos                          | Escenarios, entorno/red/carga, inicio/fin, segundos, error/timeout, evidencia; promedio por escenario y comprobación individualRNF05.                                                |
| I05 cronometraje | Observador registra inicio/fin de cada venta o cotización                                  | Límites de tarea de VD01/VD02, complejidad, interrupciones, etapa; dosregistros y dosconsolidaciones.                                                                                |
| I06 cobranzas    | Revisar comprobantes, pagos y saldo al corte                                               | Contado/crédito, fechas/corte y antigüedad comparable, soles, ajustes; cociente de montos, no opiniones ni conteo de deudores.                                                       |
| I07 inventario   | Conteo físico y contraste con saldo documental al mismo corte                              | Producto–almacén, corte, movimientos/conciliación, diferencia y evidencia; consolidación por producto distinto.                                                                      |
| I08 clientes     | Seguir clientes únicos desde aviso verificable hasta compra documentada o cierre del plazo | Aviso, ventana común aprobada antes de aplicar, comprobante, elegibilidad y seguimiento; sin asumir envío automático o inventarC1.                                                   |

## Proceso de aplicación que deben contener los formatos

1. Definir unidad de registro, muestra/marco del periodo, versión o etapa y reglas del protocolo.
2. Recoger observaciones/respuestas/registros originales con fecha, responsable y referencia de evidencia.
3. Revisar completitud, duplicados, coherencia, exclusiones justificadas y datos faltantes sin alterarlos para mejorar resultados.
4. Consolidar manteniendo separados escenarios, versiones, ventas/cotizaciones y C1/C2 según cada instrumento.
5. Calcular la métrica con el denominador aprobado y reportar pendientes/no evaluables. Promedio de respuesta no es tiempo de trabajo humano; SUS son puntos; recuperación cambia en puntos porcentuales.
6. Someter contenido al proceso de revisión previsto en3.10 y probar el protocolo antes de recolección oficial; registrar ajustes de versión. No afirmar revisión, piloto o medición efectuados sin evidencia.

## Herramientas y entrega

Propuesta: un Word con fichas técnicas breves, instrucciones y todos los instrumentos completos en anexos; un Excel de apoyo opcional para registrar/consolidar datos con las mismas columnas y reglas. Word/Excel son soportes: no convierten una opinión en conteo ni una ficha descriptiva en registro de observación. Un formulario de encuesta sirve para SUS, mientras pruebas y fichas documentales necesitan sus propios registros. No se identificó una obligación docente de ochoWord o de un servicio de formularios web.

La ficha técnica acompañará al instrumento, sin sustituir preguntas, casos o tablas de registro. El documento puede prepararse durante desarrollo; la entrega de diseño no implica que las mediciones hayan terminado. Continuar Bloque1 del plan; luego elaborar los ocho por bloques y verificar su concordancia.
