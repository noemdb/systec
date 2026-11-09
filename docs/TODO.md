# TODO.md — SysTec GMB

> **Estado:** v1.3 (F2 en ejecución) · **Actualizado:** 2026-11-09.
> Fuente de verdad del estado técnico y documental. Un ítem es ✅ solo cuando cumple su criterio de aceptación y está verificado. Las prioridades son `P0` (bloqueante de seguridad), `P1` (calidad/operación), `P2` (mejora), `P3` (futuro).

## Leyenda

- 🔲 Por hacer · 🧪 En pruebas · ✅ Hecho · ⛔ Bloqueado · ⚠️ Deuda aceptada

## 1. Estado documental

| Documento | Estado | Nota |
|---|---|---|
| `README.md` | ✅ | Mapa y proceso |
| `PROJECT.md` | ✅ | Alcance as-built |
| `ARCHITECTURE.md` | ✅ | Componentes, request y deudas |
| `DOMAIN.md` | ✅ | Glosario, entidades, estados y casos límite |
| `DATABASE.md` | ✅ | Esquema, datos normalizados, migraciones y calidad |
| `API.md` | ✅ | Superficie HTTP real |
| `SECURITY.md` | ✅ | Hallazgos P0–P3 y checklist |
| `CONVENTIONS.md` | ✅ | Estilo y patrones |
| `DECISIONS.md` | ✅ | ADR-001–017 |
| `TODO.md` | ✅ | Este archivo |
| `CHANGELOG.md` | ✅ | Baseline |
| `anexos/inventario-tecnico.md` | ✅ | Análisis archivo por archivo |
| `anexos/matriz-trazabilidad.md` | ✅ | Hallazgo → código → doc → tarea |
| `anexos/plan-documentacion.md` | ✅ | Snapshot inicial; plan vigente en `spec/documentations/plan-documentacion.md` |
| `grafo/` | ✅ | Lógica de negocio, modelo y JSON |

## 2. Estado técnico por área

| Área | Estado | Evidencia | Riesgo principal |
|---|---|---|---|
| Inventario | ✅ Operativo | 617 bienes | Estado manual; sin selector explícito |
| Mantenimientos | ✅ Datos F2 normalizados | 547 registros | Sin catálogo formal de técnicos ni auditoría |
| Historial | ✅ Operativo | `historials.php` | Bien inexistente controlado |
| Reporte de dañados | ✅ F2 | `report.php` | Deduplicado por consulta |
| Importación | ✅ F2 | `updates.php`, `imports.php` | POST, confirmación, transacción y límite |
| Persistencia | 🧪 F2 | `storage/db.db`, `migrations/`, `BACKUP.md` | Migraciones e índices; política de backup definida parcialmente, destino/cifrado/crontab pendientes; FK pendiente |
| Seguridad | 🧪 F1 | `SECURITY.md` | Controles de aplicación implementados; HTTPS/HSTS, rate limiting y auditoría pendientes |
| Pruebas | ⚠️ Manuales | `php -l` + curl | Sin suite automatizada |
| Documentación | ✅ Actualizada | `docs/` | Debe mantenerse viva |

## 3. Backlog priorizado

### P0 — Seguridad (bloqueante para exponer)

| ID | Tarea | Criterio de aceptación | Depende | Estado |
|---|---|---|---|---|
| P0-01 | Autenticación con sesión PHP | Sin sesión, toda ruta redirige a login; cookie `HttpOnly`, `Secure`, `SameSite=Lax` | — | ✅ F1 |
| P0-02 | Roles y autorización | Matriz de `SECURITY.md` aplicada en servidor; `consulta` no muta | P0-01 | ✅ F1 |
| P0-03 | Parametrizar todo el SQL | Ningún método de `DB` concatena valores; pruebas SQLi verdes | — | ✅ F1 (suite automatizada pendiente) |
| P0-04 | Escape de salida | Helper `e()` en todas las vistas; `<script>` no ejecuta | — | ✅ F1 |
| P0-05 | Mover `db.db`, `bck/` y CSV fuera del webroot | `/storage/db.db`, `/bck/...`, `/*.csv` devuelven 403/404 | — | ✅ F1 |
| P0-06 | CSRF y acciones por POST | Cambios de estado y formularios con token; GET no muta | P0-01 | ✅ F1 |
| P0-07 | Endurecer despliegue | Headers, `display_errors=Off`, logs fuera del webroot; HTTPS/HSTS en servidor real | P0-05 | 🧪 F1 (HTTPS pendiente) |

### P1 — Calidad de datos y operación

| ID | Tarea | Criterio de aceptación | Depende | Estado |
|---|---|---|---|---|
| P1-01 | Normalizar estados de mantenimiento | `finalizado`/`FINALIZADO` unificados; trigger de catálogo | Backup | ✅ F2 |
| P1-02 | Normalizar tipos | `evaluativo` válido; catálogo aplicado en UI/servidor/BD | Dueño | ✅ F2 |
| P1-03 | Normalizar técnicos | `ndomiguez` → `ndominguez` | Dueño | ✅ F2 |
| P1-04 | Normalizar fechas | Fechas ISO normalizadas; fechas futuras revisadas | Backup | ✅ F2 |
| P1-05 | Corregir `time_taken` | Horas totales; prueba > 24 h | — | ✅ F1 (históricos preservados; 15 nulos) |
| P1-06 | Máquina de estados de mantenimiento | Transiciones válidas en servidor + trigger SQLite | P1-01 | ✅ F2 |
| P1-07 | Importación segura | POST + confirmación + transacción + reporte de válidas/omitidas/erróneas; máximo 5000 filas y 5 MB | P0-03 | ✅ F2 |
| P1-08 | Reporte de dañados sin duplicados | `DISTINCT`/`EXISTS`; test con múltiples notas | — | ✅ F2 |
| P1-09 | Quitar fechas/nombres hardcodeados | Fecha actual y técnico real en historial/membrete | — | ✅ F1 |
| P1-10 | Migraciones versionadas | `migrations/` + `schema_migrations`; backup y rollback | — | ✅ F2 |
| P1-11 | Índices mínimos | `ident`, `property_id`, `(status,next_maintenance_date)` | P1-10 | ✅ F2 |
| P1-12 | Corregir `update.php` | Usa `validations/update.php`; valida `id` y `status` | — | ✅ F1 |
| P1-13 | Corregir `batch.php` | Inicializa `$property_id`; sin avisos | — | ✅ F1 |
| P1-14 | Backups operativos y restore probado | Política aprobada por TI, copia diaria cifrada en destino separado, rotación 30/12/12 y restore trimestral dentro de RPO/RTO | F2-13 | 🧪 F2: propuesta registrada y selección semanal/mensual definida; falta aprobación TI, destino, método/custodia de cifrado, cron y primera prueba operativa (`BACKUP.md`) |
| P1-15 | Resolver compatibilidad PHP 8 (`match`) | `match` reemplazado por `switch` o versión de PHP elevada y documentada | — | ✅ F1 |
| P1-16 | Verificar orden de carga DataTables/jQuery | Las tablas inicializan sin error en todas las vistas | — | ✅ F1 (falso positivo) |

### P2 — Funcionalidad y mantenibilidad

| ID | Tarea | Criterio de aceptación | Depende | Estado |
|---|---|---|---|---|
| P2-01 | Catálogo de estados/tipos/técnicos en BD | Tablas o enums; UI alimentada desde BD | P1-01…P1-03 | 🔲 F3 |
| P2-02 | Selector explícito de estado del bien | Reemplaza botón cíclico; motivo y auditoría | P0-02 | 🔲 F3 |
| P2-03 | Auditoría de cambios | Tabla `audit_events`; escritura en la misma transacción | P0-01 | 🔲 F3 |
| P2-04 | Separar capa de datos | `src/Database.php` + repositorios; páginas sin SQL | P0-03 | 🔲 F3 |
| P2-05 | Validación centralizada | Un validador por contexto (`create`/`update`/`transition`) | P1-12 | 🔲 F3 |
| P2-06 | Pruebas unitarias e integración | PHPUnit o runner propio; CI local | P2-04 | 🔲 F3 |
| P2-07 | Logs sin PII | Archivo fuera del webroot; niveles y rotación | P0-07 | 🧪 F1 (archivo); 🔲 rotación |
| P2-08 | Búsqueda y filtros del dashboard | Búsqueda por código/descripción/serial; filtros persistentes | — | 🔲 F3 |
| P2-09 | Exportación CSV/PDF de reportes | Botón de exportación; PDF generado o impresión guiada | P1-08 | 🔲 F4 |
| P2-10 | Gestión de técnicos | Alta/baja; select desde BD | P2-01 | 🔲 F3 |
| P2-11 | Gestión de usuarios | CRUD y asignación de roles | P0-02 | 🧪 F1 (CLI); 🔲 UI |
| P2-12 | Snapshot de reportes | `report_versions` con hash; reproducibilidad | P2-03 | 🔲 F4 |
| P2-13 | Limpiar código muerto y configuración | Archivos sin uso eliminados o justificados; `.gitignore` corregido | — | ✅ F1 |

### P3 — Futuro

| ID | Tarea | Nota |
|---|---|---|
| P3-01 | Adjuntos/fotos por mantenimiento | Evidencia visual |
| P3-02 | Notificaciones de vencimiento | Correo o panel |
| P3-03 | API JSON | Solo si se integra con otro sistema |
| P3-04 | Multi-institución | Solo si el sistema se comparte |
| P3-05 | App móvil / PWA | Solo si la operación lo exige |
| P3-06 | Dashboard de indicadores | Tendencias, cumplimiento, costos |
| P3-07 | Eliminar `adscription` o poblarlo | Decisión de modelo |

## 4. Bloqueos de definición

| Bloqueo | Decisión / pregunta | Responsable | Impacta | Estado |
|---|---|---|---|---|
| B-01 | Escala aprobada: `BUENO`, `REGULAR`, `MALO`, `MUY MALO` | Dueño / Oficina | ADR-007, P2-02 | ✅ Resuelto |
| B-02 | `MUY BUENO` no es válido; dos registros se mapearon a `BUENO` | Dueño | P1-01, P1-02 | ✅ Resuelto |
| B-03 | `evaluativo` es un tipo válido | Dueño | P1-02 | ✅ Resuelto |
| B-04 | `time_taken` se mide en horas totales; históricos se conservan | Dueño | P1-05, ADR-011 | ✅ Resuelto |
| B-05 | ¿El estado del bien se deriva de mantenimientos? | Dueño | P2-02 | ⛔ Pendiente |
| B-06 | Importar solo el estado de bienes existentes | Dueño | P1-07, ADR-005 | ✅ Resuelto |
| B-07 | ¿Quién autoriza cambios de estado? | Dueño | P0-02, P2-03 | ⛔ Pendiente |
| B-08 | ¿Se requiere PDF firmado o basta impresión? | Dueño | P2-09, P2-12 | ⛔ Pendiente |
| B-09 | ¿Se expondrá fuera de la red local? | Dueño / TI | P0 completo | ⛔ Pendiente |
| B-10 | Normalizar datos legacy según las reglas aprobadas | Dueño | P1-01…P1-04, ADR-012 | ✅ Resuelto |

## 5. Plan por fases

### F0 — Documentación baseline ✅ (2026-11-09)

- [x] Inventario archivo por archivo.
- [x] Grafo de lógica de negocio.
- [x] Estructura documental completa.
- [x] Matriz de trazabilidad.
- [x] Plan de ejecución.

### F1 — Contención de seguridad (P0) 🧪 2026-11-09

- [x] Autenticación y roles.
- [x] SQL parametrizado y escape.
- [x] Base y backups fuera del webroot.
- [x] CSRF y acciones por POST.
- [ ] Cierre del checklist de despliegue (`SECURITY.md`): HTTPS/HSTS y confirmación de servidor real.

### F2 — Integridad de datos (P1) 🧪 2026-11-09

- [x] Normalizar estados, tipos, técnicos y fechas.
- [x] Migraciones versionadas e índices.
- [x] Importación transaccional con límite de 5000 filas y 5 MB.
- [x] Reporte de dañados sin duplicados.
- [x] Corregir `time_taken` (horas totales; valores históricos preservados).
- [x] Corregir `update.php` y `batch.php`.
- [ ] Reporte de dañados sin duplicados.
- [x] Resolver compatibilidad PHP 7.4 y verificar DataTables/jQuery.
- [x] Backup/restore de trabajo probado; falta calendario y cifrado.

### F3 — Mantenibilidad (P2)

- [ ] Repositorios y servicios.
- [ ] Validación centralizada.
- [ ] Auditoría.
- [ ] Pruebas y CI local.
- [ ] Catálogos en BD.
- [ ] Limpiar código muerto y configuración.

### F4 — Producto (P3)

- [ ] Exportaciones.
- [ ] Notificaciones.
- [ ] API (si aplica).
- [ ] Indicadores.

## 6. Checklist por cambio

- [ ] La funcionalidad funciona en el caso feliz.
- [ ] Los errores y casos límite están cubiertos.
- [ ] El SQL está parametrizado.
- [ ] La salida está escapada.
- [ ] La acción que muta usa POST + CSRF.
- [ ] Se actualizó `API.md` si cambió una ruta/parámetro.
- [ ] Se actualizó `DATABASE.md` si cambió el esquema.
- [ ] Se actualizó `DOMAIN.md` si cambió una regla o estado.
- [ ] Se actualizó `TODO.md` y `CHANGELOG.md`.
- [ ] Se creó un ADR si cambió el rumbo.
- [ ] Se probó el backup/restore si el cambio toca datos.

## 7. Métricas de seguimiento

| Métrica | Línea base 2026-11-09 | Estado actual | Objetivo |
|---|---|---|---|
| Rutas sin auth | 100 % | 0 % | 0 % |
| Consultas concatenadas | 5 métodos de `DB` | 0 | 0 |
| Vistas con salida sin escape | ≥ 7 | 0 | 0 |
| Datos sensibles bajo webroot | 4 rutas | 0 | 0 |
| Estados/tipos fuera de catálogo | 2 (`FINALIZADO`, `MUY BUENO`) + `evaluativo` | 0 | 0 |
| Registros con técnico typo | 153 | 0 | 0 |
| Migraciones registradas | 0 | 2 | Cada cambio de esquema/datos versionado |
| Duraciones históricas verificables | 15 nulos + valores legacy no reconstruibles | Se conservan; no se recalculan sin fuente | Sin valores inventados |
| Filas de importación omitidas sin reporte | Todas | 0 (reporte por fila) | 0 |
| Cobertura de pruebas | 0 % | Manual | ≥ 60 % en reglas críticas |
| Backups con restore probado | 0 | 1 (F2) | 1 por trimestre |

---

Ver también: `README.md`, `PROJECT.md`, `SECURITY.md`, `DECISIONS.md`, `CHANGELOG.md`, `docs/anexos/plan-documentacion.md`.
