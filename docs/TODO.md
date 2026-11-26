# TODO.md — SysTec GMB

> **Estado:** v1.6 · **Actualizado:** 2026-11-26.
> Fuente de verdad del estado técnico y documental. Un ítem es ✅ solo cuando cumple su criterio de aceptación y está verificado.

## Leyenda

- 🔲 Por hacer · 🧪 Parcial/en pruebas · ✅ Hecho · ⛔ Bloqueado · ⚠️ Deuda aceptada

## 1. Estado documental

| Documento | Estado | Nota |
|---|---|---|
| `README.md` | ✅ | Índice y proceso v1.6 |
| `PROJECT.md` | ✅ | Alcance as-built actualizado |
| `ARCHITECTURE.md` | ✅ | Componentes, repositorios y request |
| `DOMAIN.md` | ✅ | Usuarios/perfiles, tickets e informes de rendición |
| `DATABASE.md` | ✅ | Esquema 001–011 y snapshot 2026-11-26 |
| `API.md` | ✅ | HTML, JSON interno, Markdown y contratos actuales |
| `SECURITY.md` | ✅ | Controles, cuentas seed, CSP y pendientes |
| `BACKUP.md` | ✅ | Script real, cifrado y límites operativos |
| `CONVENTIONS.md` | ✅ | PHP 8.2, TablePaginator y drawers |
| `DECISIONS.md` | ✅ | ADR-001–023 y vigencia |
| `CHANGELOG.md` | ✅ | Entradas hasta 2026-11-26 |
| `grafo/` | ✅ | Lógica, modelo y JSON actualizados |
| `documentation.php` | ✅ | Guía publicada ampliada: 22 secciones, fundamentos por módulo, estados, límites, glosario, FAQ y vigencia; render verificada para cinco roles e impresión |
| `anexos/` | ✅ histórico | Baseline pre-F1/F2; no sustituye el estado vigente |
| `templatesOthersApp/` | Referencia externa | No describe SysTec |

## 2. Estado técnico por área

| Área | Estado | Evidencia | Riesgo principal |
|---|---|---|---|
| Inventario | ✅ Operativo | 627 bienes | Estado manual; sin auditoría persistente |
| Mantenimientos | ✅ Operativo | 324 registros | Campo técnico texto; catálogo híbrido |
| Tickets | ✅ Operativo | 8 tickets, 19 eventos | Política de `consulta` inconsistente entre lista y detalle |
| Portal Personal | ✅ Operativo | `personal/` | Sin cambios de estado/eliminación por diseño |
| Usuarios y perfiles | ✅ Operativo | 29 usuarios, 29 perfiles | Seeder falla si falta un username canónico |
| Informes de rendición | ✅ Operativo | 1 informe aprobado | Salvaguarda DDL runtime; datos enviados a IA |
| Persistencia | ✅ Migraciones 001–011 | `schema_migrations` | FK solo en `profile`; backups antiguos incompatibles |
| Seguridad | 🧪 Parcial | Auth, roles, CSRF, rate limiting, CSP | HTTPS/HSTS, auditoría y revocación de sesión pendientes |
| Pruebas | 🧪 Unitarias/locales | 115 pruebas, 1095 aserciones | Faltan E2E y medición de cobertura |
| Backups | 🧪 Parcial | `bin/backup.sh` | Cron, destino separado y restore operativo pendientes |

## 3. Backlog priorizado

### P0 — Seguridad

| ID | Tarea | Criterio de aceptación | Estado |
|---|---|---|---|
| P0-01 | Autenticación con sesión PHP | Login, cookies y regeneración de ID | ✅ |
| P0-02 | Roles y autorización | Matriz aplicada en servidor | 🧪 Inconsistencia conocida en tickets para `consulta` |
| P0-03 | SQL parametrizado | Sin concatenación de valores | ✅ |
| P0-04 | Escape de salida | `e()`/`js()` en vistas y datos | ✅ |
| P0-05 | Protección de datos | `storage/` denegado por router/htaccess | ✅; verificar reglas Apache y mover fuera del docroot |
| P0-06 | CSRF y POST | Toda mutación con token | ✅ |
| P0-07 | Endurecer despliegue | Headers, debug off, HTTPS/HSTS | 🧪 HTTPS/HSTS pendiente |
| P0-08 | Unificar política de contraseñas | Mismo mínimo en UI, backend y CLI | 🔲 Backend alta web acepta 4; UI/CLI exigen 6 |
| P0-09 | Revocación de sesiones | Cambio de rol/estado elimina sesiones activas | 🔲 La sesión conserva el rol del login |
| P0-10 | Rotar clave inicial de cuentas seed | Ninguna cuenta conserva la clave común de la migración 009 | 🔲 Debe rotarse antes de exponer el sistema |
| P0-11 | Rate limiting de importación, sincronización e IA | Límites por usuario/IP y respuesta 429 | 🔲 Solo el login tiene throttle |

### P1 — Datos y operación

| ID | Tarea | Criterio de aceptación | Estado |
|---|---|---|---|
| P1-01…P1-04 | Normalización legacy | Estados, tipos, técnicos y fechas | ✅ F2 |
| P1-05 | `time_taken` | Horas totales; prueba >24 h | ✅ |
| P1-06 | Máquina de estados | Servidor + trigger SQLite | ✅ |
| P1-07 | Importación segura | POST, confirmación, transacción, reporte y límites | ✅ |
| P1-08 | Reporte de dañados sin duplicados | `DISTINCT`/`EXISTS` | ✅ |
| P1-09 | Migraciones versionadas | Runner, backup y `schema_migrations` | ✅ 001–011 |
| P1-10 | Índices mínimos y específicos | Inventario, tickets, perfiles e informes | ✅ |
| P1-11 | Backups operativos | Política aprobada, cifrado, destino separado, cron y restore | 🧪 Script listo; despliegue TI pendiente |
| P1-12 | Compatibilidad de restores | Backup antiguo se restaura aislado y se migra antes de servir | 📝 Documentado; falta procedimiento automatizado |
| P1-13 | Reconciliar seed canónico | `abrizzuela` en migración/seeder y DB | 🔲 La DB contiene `abrizuela` |

### P2 — Funcionalidad y mantenibilidad

| ID | Tarea | Criterio de aceptación | Estado |
|---|---|---|---|
| P2-01 | Catálogo de técnicos | Históricos + usuarios `tecnico` activos | ✅ Híbrido; persistencia sigue en texto |
| P2-02 | Estado del bien | Selector explícito, motivo y auditoría | 🧪 Selector/POST/log hechos; motivo y `audit_events` pendientes |
| P2-03 | Auditoría general | `audit_events` en misma transacción | 🔲 |
| P2-04 | Separar capa de datos | Repositorios; páginas sin SQL directo | 🧪 Repositorios existen; varias páginas conservan SQL |
| P2-05 | Validación centralizada | Create/update/transition | ✅ Mantenimientos; perfiles tienen validación propia |
| P2-06 | Pruebas y CI | Reglas críticas y flujos HTTP | 🧪 PHPUnit/CI listos; E2E pendiente |
| P2-07 | Logs sin PII | Rotación y niveles | ✅ |
| P2-08 | Búsqueda y filtros | Dashboard, mantenimientos, tickets y usuarios | ✅ |
| P2-09 | Exportaciones | CSV, impresión y PDF/snapshot | 🧪 CSV/impresión/Markdown listos; PDF servidor y snapshot legacy pendientes |
| P2-10 | Gestión de técnicos | Alta/baja y selector | ✅ Con catálogo híbrido |
| P2-11 | Gestión de usuarios/perfiles | CRUD, roles, perfiles y avatar | ✅ Drawer + CLI + seeder |
| P2-12 | Snapshot de reportes | `report_versions` reproducibles | 🧪 Informes de rendición persistentes; reportes legacy siguen en vivo |
| P2-13 | Limpiar código muerto | Archivos sin uso eliminados o justificados | 🔲 `properties/table/properties.php`, `historials/chart.php` e `include/es-ES.json` siguen presentes |
| P2-14 | Política única para `consulta` en tickets | Listado, detalle y endpoints coherentes | 🔲 Listado/drawer permitidos; detalle/acciones 403 |
| P2-15 | Paginación servidor de tickets | `LIMIT/OFFSET` real | 🔲 La vista carga todos y pagina en cliente |
| P2-16 | DDL runtime de informes | Ningún `CREATE TABLE` en GET normal | 🔲 `RenditionReportRepository::ensureTableExists()` sigue creando tabla/índices |
| P2-17 | Alinear router y `.htaccess` | Mismas reglas para `conn.php`, `router.php` y extensiones | 🔲 |
| P2-18 | Política de avatares | Lista blanca, proxy o almacenamiento local | 🔲 CSP permite cualquier host HTTP/HTTPS |
| P2-19 | Separar estado de informe y filtro de mantenimiento | `save-rendition-report.php` no reutiliza `status` para dos fines | 🔲 El alcance guardado puede diferir del generado |

### P3 — Futuro

| ID | Tarea | Nota |
|---|---|---|
| P3-01 | Adjuntos por mantenimiento | Evidencia visual |
| P3-02 | Avisos de vencimiento | Correo/panel persistente |
| P3-03 | API pública | Solo si hay integración externa |
| P3-04 | Multi-institución | Requiere modelo de datos y despliegue |
| P3-05 | App móvil/PWA | Solo si la operación lo exige |
| P3-06 | Dashboard de indicadores | Tendencias y cumplimiento |

## 4. Bloqueos de definición

| Bloqueo | Decisión / pregunta | Estado |
|---|---|---|
| B-01 | Escala de estado del bien | ✅ Resuelto |
| B-02 | `MUY BUENO` no válido | ✅ Resuelto |
| B-03 | `evaluativo` válido | ✅ Resuelto |
| B-04 | `time_taken` en horas totales | ✅ Resuelto |
| B-05 | ¿El estado del bien se deriva de mantenimientos? | ⛔ Pendiente |
| B-06 | Importación de existentes: solo estado, salvo PostgreSQL | ✅ Resuelto |
| B-07 | ¿Quién autoriza cambios de estado? | ⛔ Pendiente |
| B-08 | ¿PDF firmado o impresión basta? | ⛔ Pendiente |
| B-09 | ¿Exposición fuera de la red local? | ⛔ Pendiente |
| B-10 | Política de acceso de `consulta` a tickets | ⛔ Pendiente |
| B-11 | Hosts permitidos para avatares | ⛔ Pendiente |

## 5. Plan por fases

### F0 — Documentación baseline ✅

- Inventario técnico, grafo, matriz de trazabilidad y plan inicial.

### F1 — Seguridad 🧪

- Auth, roles, CSRF, SQL parametrizado, escape y protección de datos.
- Rate limiting de login y gestión web de usuarios.
- Pendiente: HTTPS/HSTS, revocación centralizada y auditoría.

### F2 — Integridad de datos 🧪

- Normalización, triggers, importación transaccional, migraciones e índices.
- Backup de trabajo verificado; operación periódica pendiente de TI.

### F3 — Producto y mantenibilidad 🧪

- Tickets, Portal Personal, repositorios, validación, CI, informes de rendición, usuarios y perfiles.
- Pendiente: cerrar inconsistencias de autorización, DDL runtime y paginación.

### F4 — Evolución

- Auditoría general, snapshots legacy, E2E, adjuntos, API e indicadores.

## 6. Métricas de seguimiento

| Métrica | Línea base 2026-11-09 | Estado 2026-11-26 |
|---|---|---|
| Rutas funcionales sin sesión | 100 % | 0 % |
| Consultas con valores concatenados | 5 | 0 |
| Vistas sin escape identificadas | ≥ 7 | 0 |
| Datos bajo webroot sin protección | 4 rutas | 0; `storage/` sigue en el árbol pero denegado |
| Migraciones registradas | 0 | 11 |
| Usuarios / perfiles | 4 / 0 | 29 / 29 |
| Bienes / mantenimientos | 617 / 547 | 627 / 324 |
| Pruebas PHPUnit | Sin baseline | 115 pruebas, 1095 aserciones |
| Backups con restore probado | 0 | 1 histórico + copias operativas recientes verificadas |
| Cobertura HTTP E2E | 0 | 0 |

## 7. Checklist por cambio

- [ ] Funcionalidad y errores verificados.
- [ ] SQL parametrizado y salida escapada.
- [ ] Mutaciones con POST + CSRF.
- [ ] `API.md` actualizada si cambió ruta/parámetro.
- [ ] `DATABASE.md`, `DOMAIN.md` y `grafo/` actualizados si corresponde.
- [ ] `documentation.php` actualizada si cambió la guía funcional.
- [ ] `TODO.md`, `CHANGELOG.md` y ADR actualizados.
- [ ] Backup/restore probado si el cambio toca datos.

---

Ver también: `README.md`, `PROJECT.md`, `SECURITY.md`, `DECISIONS.md`, `CHANGELOG.md`.
