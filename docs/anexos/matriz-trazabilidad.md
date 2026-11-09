# Matriz de trazabilidad — SysTec GMB

> **Estado:** v1.0 · **Actualizado:** 2026-11-09.
> Conecta cada regla de negocio y cada hallazgo con su evidencia en código, el documento que lo explica y la tarea que lo resuelve. Conserva el estado del baseline F0/F1; para el estado actual de las tareas consulta `docs/TODO.md`.

---

## 1. Cómo usar esta matriz

1. Busca el `ID` en `grafo/` o en `docs/TODO.md`.
2. Abre la evidencia para ver el código exacto.
3. Lee el documento de dominio o seguridad para el contexto.
4. Ejecuta la tarea asociada y actualiza el estado.
5. Si no hay prueba, créala antes de cerrar la tarea.

---

## 2. Reglas de negocio

| ID | Regla | Evidencia | Documento | Tarea | Prueba |
|---|---|---|---|---|---|
| R-01 | Código visible `grupo-subgrupo-seccion-ident` | `conn.php::getCodeId` | `DOMAIN.md` §2.1 | P2-01 | Pendiente |
| R-02 | Mantenimiento pertenece a un bien | `maintenances.property_id` | `DOMAIN.md` §2.2 | P1-10, P2-04 | Pendiente |
| R-03 | Código snapshot al registrar/editar | `register.php`, `update.php` | `DOMAIN.md` §2.2 | P2-04 | Pendiente |
| R-04 | Alta manual nace `iniciado` | `maintenances/register.php:19` | `DOMAIN.md` §4.1 | P1-06 | Pendiente |
| R-05 | Importación nace `finalizado` | `maintenances/table/import.php:51` | `DOMAIN.md` §4.1 | P1-07 | Pendiente |
| R-06 | `time_taken` mide horas | `conn.php::restarFechasEnHoras` | `DOMAIN.md` §1.2 | P1-05 | Pendiente |
| R-07 | Vencido = no finalizado + fecha pasada | `maintenances/table/crud.php:88-91` | `DOMAIN.md` §4.3 | P1-01, P1-06 | Pendiente |
| R-08 | Reporte de dañados por estado + nota | `conn.php::indexWithMaintenances` | `DOMAIN.md` §2.4 | P1-08 | Pendiente |
| R-09 | Historial ordenado por fecha ASC | `historials.php:13` | `DOMAIN.md` §2.4 | P1-09 | Pendiente |
| R-10 | Importación de inventario actualiza solo estado | `conn.php::createOrUpdate` | `DOMAIN.md` §2.1 | P1-07, B-06 | Pendiente |
| R-11 | Tipo con etiqueta/color | `include/helpers.php` | `DOMAIN.md` §1.2 | P1-02 | Pendiente |
| R-12 | Estado de mantenimiento con etiqueta/color | `include/helpers.php` | `DOMAIN.md` §1.3 | P1-01 | Pendiente |
| R-13 | Zona horaria `America/Caracas` | `conn.php:3` | `DOMAIN.md` §3 | P1-04 | Pendiente |
| R-14 | Login obligatorio y roles | `include/auth.php` | `SECURITY.md` §3 | P0-01, P0-02 | ✅ F1 |

---

## 3. Hallazgos técnicos y de seguridad

| ID | Tipo | Hallazgo | Evidencia | Impacto | Documento | Tarea | Estado |
|---|---|---|---|---|---|---|---|
| H-01 | Seguridad | Sin autenticación ni autorización | `include/auth.php`, `login.php` | Crítico | `SECURITY.md` §3 | P0-01, P0-02 | ✅ F1 |
| H-02 | Seguridad | SQL por concatenación | `conn.php` con prepared statements | Crítico | `SECURITY.md` §3 | P0-03 | ✅ F1 |
| H-03 | Seguridad | XSS por salida sin escape | `e()`/`js()` en vistas | Alto | `SECURITY.md` §3 | P0-04 | ✅ F1 |
| H-04 | Lógica | `update.php` valida con `register.php` | `maintenances/update.php` | Medio | `API.md` §3.4 | P1-12 | ✅ F1 |
| H-05 | Lógica | `time_taken` solo toma `DateInterval->h` | `conn.php::restarFechasEnHoras` | Medio | `DOMAIN.md` §1.2 | P1-05 | ✅ F1 |
| H-06 | Lógica | Transiciones de estado sin guardia | `maintenance_transition_allowed()` | Alto | `DOMAIN.md` §4.1 | P1-06 | 🧪 F1 (servidor); 🔲 BD |
| H-07 | Datos | Catálogos incompletos | `include/helpers.php`, `conn.php` | Medio | `DATABASE.md` §6.4 | P1-01…P1-03 | 🔲 F2 |
| H-08 | Lógica | Reporte de dañados duplica bienes | `conn.php::indexWithMaintenances` | Medio | `DOMAIN.md` §5 | P1-08 | 🔲 F2 |
| H-09 | Lógica | `$property_id` indefinido en `batch.php` | `batch.php` | Bajo | `API.md` §3.6 | P1-13 | ✅ F1 |
| H-10 | Lógica | Fechas/nombres hardcodeados | `historials.php`, `historials/membrete.php` | Medio | `DOMAIN.md` §5 | P1-09 | ✅ F1 |
| H-11 | Arquitectura | Cierre de conexión dentro de `DB` | `conn.php::create`, `createOrUpdate` | Medio | `ARCHITECTURE.md` §Acceso a datos | P2-04 | ✅ F1 |
| H-12 | Seguridad | `db.db`, backups y CSV bajo webroot | `storage/`, `router.php`, `.htaccess` | Crítico | `SECURITY.md` §3 | P0-05 | ✅ F1 |
| H-13 | Calidad | Sin migraciones ni pruebas | ausencia de `migrations/`, `tests/` | Alto | `DATABASE.md` §7 | P1-10, P2-06 | 🔲 F2/F3 |
| H-14 | Config | `es-ES.json` no referenciado | archivo eliminado | Bajo | `CONVENTIONS.md` §5 | P2-04 | ✅ F1 |
| H-15 | Datos | Importación sin transacción ni reporte | `updates.php`, `imports.php` | Medio | `API.md` §3.7 | P1-07 | 🧪 F1 (POST/reporte); 🔲 transacción |
| H-16 | Compatibilidad | `match` (PHP 8) con entorno PHP 7.4 | `include/helpers.php` | Alto | `CONVENTIONS.md` §3.1 | P1-15 | ✅ F1 |
| H-17 | Frontend | Orden de carga DataTables/jQuery dudoso | `vendor/datatables/datatables.min.js` incluye jQuery | Medio | `CONVENTIONS.md` §3.4 | P1-16 | ✅ F1 (falso positivo) |
| H-18 | Código muerto | `properties/table/properties.php` sin uso | archivo eliminado | Bajo | `anexos/inventario-tecnico.md` | P2-13 | ✅ F1 |
| H-19 | Código muerto | `historials/chart.php` sin uso | archivo eliminado | Bajo | `anexos/inventario-tecnico.md` | P2-13 | ✅ F1 |
| H-20 | Código muerto | `validations/update.php` sin uso | ahora usado por `update.php` | Medio | `anexos/inventario-tecnico.md` | P1-12 | ✅ F1 |
| H-21 | Config | `.gitignore` con patrón `/.` | `.gitignore` corregido | Bajo | `anexos/inventario-tecnico.md` | P2-13 | ✅ F1 |
| H-22 | Documentación | `README.md` sin información funcional | `README.md` reescrito | Bajo | `README.md` | ✅ | ✅ F1 |

---

## 4. Hallazgos de datos

| ID | Hallazgo | Evidencia | Documento | Tarea |
|---|---|---|---|---|
| D-01 | Dos grafías de estado de mantenimiento | 353 `finalizado` + 194 `FINALIZADO` | `DATABASE.md` §6.4 | P1-01 |
| D-02 | Tipo `evaluativo` fuera de catálogo | 1 fila | `DATABASE.md` §6.4 | P1-02 |
| D-03 | Estado `MUY BUENO` fuera de catálogo | 2 filas | `DATABASE.md` §6.4 | P1-01 |
| D-04 | Técnico `ndomiguez` (typo) | 153 filas | `DATABASE.md` §6.4 | P1-03 |
| D-05 | Fechas en formatos mixtos | `YYYY-MM-DD`, `T`, con hora | `DATABASE.md` §6.4 | P1-04 |
| D-06 | Fechas futuras (2026) | 3 filas | `DATABASE.md` §6.4 | P1-04 |
| D-07 | `adscription` nunca poblado | 617/617 vacíos | `DATABASE.md` §6.4 | P3-07 |
| D-08 | `time_taken` aleatorio en importación | `rand(20,100)` | `DATABASE.md` §6.4 | P1-05, P1-07 |
| D-09 | `code` snapshot puede desactualizarse | `maintenances.code` | `DATABASE.md` §5 | P2-04 |
| D-10 | 450 bienes sin mantenimiento | 450/617 | `DATABASE.md` §6.1 | Validar con dueño |

---

## 5. Cobertura documental

| Área | Documento principal | Diagrama | Evidencia |
|---|---|---|---|
| Producto | `PROJECT.md` | — | `index.php`, datos |
| Arquitectura | `ARCHITECTURE.md` | Componentes + request | `conn.php`, `include/` |
| Dominio | `DOMAIN.md` | Estados + vencimiento | `conn.php`, `maintenances/` |
| Datos | `DATABASE.md` | ERD + migración | `db.db` |
| Rutas | `API.md` | — | `*.php` |
| Seguridad | `SECURITY.md` | — | `conn.php`, vistas |
| Convenciones | `CONVENTIONS.md` | — | Todo el código |
| Decisiones | `DECISIONS.md` | — | Historial reconstruido |
| Plan | `TODO.md` | Fases | Backlog |
| Grafo | `grafo/` | Capacidades, flujos, ERD | Código |

---

## 6. Trazabilidad de capacidades

| Capacidad | Pantalla | Proceso | Datos | Documento |
|---|---|---|---|---|
| C1 Inventario | `dashboard.php` | `dashboard/actions/status.php` | `properties` | `DOMAIN.md` §2.1 |
| C2 Mantenimientos | `maintenances.php` | `register.php`, `update.php`, `actions/status.php` | `maintenances` | `DOMAIN.md` §2.2 |
| C3 Historial | `historials.php` | `historials/main.php` | `maintenances` + `properties` | `DOMAIN.md` §2.4 |
| C4 Reportes | `report.php`, `batch.php` | `indexWithMaintenances` | `properties` + `maintenances` | `DOMAIN.md` §2.4 |
| C5 Importación | `updates.php`, `imports.php` | `propertiesCsv.php`, `import.php` | CSV + BD | `API.md` §3.6 |

---

## 7. Estado de pruebas

| Prueba | Estado | Responsable | Evidencia |
|---|---|---|---|
| Sintaxis PHP (`php -l`) | ✅ | Dev | Todos los archivos de aplicación |
| Código de bien | 🔲 | Dev | — |
| Vencimiento | 🔲 | Dev | — |
| Diferencia de horas > 24 h | 🧪 | Dev | `time_taken=1` en transición de prueba |
| Importación con fila inválida | 🧪 | Dev | 1 insertada / 0 omitidas / 0 errores |
| Reporte de dañados sin duplicados | 🔲 | Dev | — |
| SQLi | ✅ | Seguridad | `property_id=1 OR 1=1` rechazado |
| XSS | ✅ | Seguridad | `search=<script>` escapado |
| CSRF | ✅ | Seguridad | POST sin token → 419 |
| Acceso directo a `storage/` | ✅ | Seguridad | `/storage/db.db` → 403 |
| Backup/restore | ✅ | Operación | Restore de trabajo con MD5 idéntico |

---

## 8. Mantenimiento de la matriz

- Al cerrar una tarea, actualiza su estado y agrega la evidencia (test, captura, commit).
- Al crear un hallazgo, asígnale `H-xx` o `D-xx` y enlázalo a un documento y una tarea.
- No elimines filas: marca como `✅` o `Reemplazada por H-xx`.
- Revisa la matriz en cada release o cambio de esquema.

---

Ver también: `docs/TODO.md`, `docs/SECURITY.md`, `docs/DATABASE.md`, `grafo/logica-negocio.md`.
