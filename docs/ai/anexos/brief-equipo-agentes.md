# Auditoría por roles: instrucciones comunes para los 3 agentes

Bruce Fire S.A.C. es un **Sistema Web de Gestión Comercial, Operativa y Técnica** (no un ERP) para la venta, recarga, mantenimiento e inspección de extintores y otros servicios de seguridad en Perú. Así lo define el Documento Maestro. Stack: Laravel 13 (PHP 8.3), Inertia v3 + React 19, Tailwind 4, MySQL, Greenter (SUNAT) y Spatie Permission.

Repositorio: `D:\TiomiguelonGgs\Documents\BRUCE FIRE\BruceFireSacv2`. La app corre en `http://localhost:8000`.

## Reglas

1. **Solo auditoría. No modifiques código, migraciones ni la base de datos.** Lo único que puedes escribir es tu informe.
2. No hagas commit ni push.
3. Si necesitas entrar a la app con un usuario, pídeselo al usuario. No crees cuentas ni cambies contraseñas.
4. Escribe en español claro y sin jerga. Cada hallazgo debe citar `archivo:línea`.

## Lee primero

- `docs/ai/PROYECTO.md`, `docs/ai/PLAN.md` (Fase 5) y `docs/ai/anexos/investigacion-campo-y-comunicacion.md`.
- `documentos/BRUCE_FIRE_Documento_Maestro_v9.md`: la especificación. Busca las secciones de tu rol.
- `documentos/01_MODULOS_ROLES_PERMISOS.md` y `documentos/notas_de_voz_usuario.md`: lo que pidió el dueño.

## Qué revisar en cada funcionalidad de tu rol

1. **¿Existe y funciona?** Ruta (`php artisan route:list --path=...`), controlador, acción y pantalla en `resources/js/pages/...`. Marca los botones o enlaces que no llevan a ningún lado. Ya se encontraron enlaces armados a mano que daban 404.
2. **¿Cumple el documento?** Qué pide el Documento Maestro y qué falta o sobra.
3. **¿Cómo se relaciona con otros módulos?** Qué tablas lee y escribe. Busca **datos duplicados**: el mismo dato guardado en dos lugares, o copiado a mano en vez de leído de su origen. Por ejemplo, el tipo de agente del extintor no existe en el producto, y por eso el certificado pone "PQS-ABC" por defecto.
4. **Buenas prácticas:** valores fijos en el código que deberían ser datos, lógica repetida, consultas N+1, validaciones faltantes y permisos por rol o sede.
5. **Comparación con empresas grandes:** cómo lo resuelven Odoo, SAP Business One, Salesforce, ServiceTitan o un software peruano de facturación (Nubefact, Bsale), según corresponda. Di qué está bien, qué está mal y qué copiar **sin reinventar**.

## Formato del informe

Archivo: `docs/ai/anexos/auditoria-<rol>.md`, por ejemplo `vendedor.md`.

```markdown
# Auditoría: <rol>

## Resumen (5 líneas)

## Funcionalidades

| Funcionalidad | ¿Funciona? | ¿Cumple el doc? | Problema | Archivo:línea |

## Datos duplicados y relaciones con otros módulos

## Comparación con empresas grandes

| Tema | Cómo lo hacen | Bruce Fire | Veredicto |

## Propuestas priorizadas

1. 🔴 Crítico (legal, pérdida de datos o algo que no funciona)
2. 🟠 Importante
3. 🟢 Mejora

## Preguntas para el dueño
```

## Reparto

| Agente      | Roles                                                                                                                                                                                                                                       |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Claude      | Técnico de planta, técnico de campo y comunicación entre roles → `tecnicos.md`                                                                                                                                                              |
| GPT         | Vendedor: clientes, cotizaciones, ventas, caja, facturación SUNAT, notas, guías, cobranzas y certificados → `vendedor.md`                                                                                                                   |
| Antigravity | Almacén (stock, kardex, recepciones, stickers, ajustes, traslados) y Gerente (catálogo de productos y servicios, categorías, unidades de medida, cajas, reportes, sedes, usuarios, auditoría, configuración y menús) → `almacen-gerente.md` |

Si encuentras algo de otro rol, anótalo en tu informe, en "Datos duplicados y relaciones", y no lo audites a fondo.
