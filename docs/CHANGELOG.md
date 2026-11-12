# CHANGELOG docs/ — SysTec GMB

> **Nota:** antes del baseline del **2026-11-09** no había bitácora de cambios. Las cifras son snapshots de su fecha; el estado vigente vive en `TODO.md` y `DATABASE.md`.
>
> Convención: cada entrada lleva fecha, alcance y evidencia. No se reescriben entradas pasadas; se agrega una nueva.

---

## 2026-11-11 — Mejora narrativa asistida con NVIDIA Build

- `bin/improve-maintenance-narrative.php` propone mejoras en descripción, motivo de falla y observaciones mediante la API de NVIDIA Build y el modelo `nvidia/nemotron-3.5-lightning-30b-a3b`.
- `API_KEY_NVIDIA_BUILD` se carga desde el entorno o `.env`; solo se transmiten los tres campos narrativos, sin IDs, bien, fecha, código, técnico ni estado.
- El modo de vista previa no modifica la base; `--execute` guarda las propuestas en una transacción. Se pueden limitar los registros con `--id`, `--property-id` o `--limit`.
- Las solicitudes están sujetas a la disponibilidad y cuota de prueba del servicio NVIDIA.
- **Verificación:** `composer test` (40 pruebas), lint PHP y una solicitud de prueba al modelo con texto sintético; no se enviaron registros de la base de datos.

---

## 2026-11-11 — Limpieza controlada de mantenimientos duplicados

- Se agregó `bin/deduplicate-maintenances.php`: detecta mantenimientos del mismo bien con igual fecha, tipo, descripción, técnico, estado y observación, conservando el ID menor. Diferencias en duración, código importado, causa de falla o próxima fecha no mantienen duplicados visibles.
- Por defecto solo presenta una vista previa. La eliminación requiere `--execute` y se ejecuta en una transacción; eventos con observaciones o fechas distintas se conservan.
- **Verificación:** prueba de integración para vista previa, campos no visibles, conservación de fechas/observaciones distintas y eliminación confirmada.

---

## 2026-11-11 — Wizard de tickets, toast reutilizables y documentación

**Alcance:** se consolidó el registro reutilizable de tickets en el área personal y en la bandeja de soporte.

- `include/ticket_register_modal.php` y `assets/js/ticket-register-modal.js` comparten el formulario modal de tres pasos: selección de bien, detalles y resumen. Se conserva el registro independiente por ruta.
- El selector carga bienes/direcciones al abrirse, filtra por categoría y dirección, y permite buscar por código/descripción.
- Los endpoints JSON de personal y soporte (`personal/actions/properties.php`, `tickets/actions/properties.php`) conservan permisos por ámbito y comparten `include/property_picker_api.php`; soporte ya no usa el endpoint restringido al rol personal.
- `toast_notification()` y `include/toasts.php` ofrecen avisos reutilizables de tipo `success`, `info`, `warning` y `danger`.
- La navegación del rol `personal` deja una sola entrada «Tickets» hacia `/personal/index.php`.
- Se sincronizaron `README`, `PROJECT`, `ARCHITECTURE`, `DOMAIN`, `DATABASE`, `API`, `SECURITY`, `CONVENTIONS` y `TODO` con las rutas, roles, datos y componentes vigentes. Los anexos de baseline y `templatesOthersApp/` se mantienen como referencias históricas/externas.
- **Verificación:** PHPUnit, 34 pruebas y 69 aserciones. La verificación visual del flujo en el navegador local no forma parte de esta actualización documental.

---

## 2026-11-11 — Mejoras del área personal de tickets

**Alcance:** se optimizó la bandeja `/personal/index.php` y el registro de tickets.

- La lista consulta tickets con sus bienes en bloque, muestra 25 por página y filtra por búsqueda, categoría, estado, adscripción/unidad y fecha; el historial y detalle se solicitan al abrir cada ticket.
- Se agregó `migrations/005_personal_ticket_lookup.sql` para cubrir con un índice compuesto la consulta paginada de tickets activos por creador.
- Los indicadores permiten filtrar por estado; se localizaron fechas, se distinguió el estado vacío de los filtros sin resultados y se conservaron los datos del formulario ante errores.
- El selector de bienes se carga al abrir el modal; las adscripciones de SIA se cachean cinco minutos y se advierte cuando se usa un cache vencido.
- Se evitó desmarcar silenciosamente un bien seleccionado al cambiar filtros, se añadieron confirmación de selección y búsqueda con debounce.
- Se documentaron las rutas JSON para cargar bienes/direcciones y el detalle del ticket.

---

## 2026-11-11 — Baja lógica de tickets desde soporte

**Alcance:** se agregó una acción de eliminación confirmada en `/tickets.php`, exclusiva de administrador y operador.

- La acción requiere POST + CSRF y actualiza `tickets.deleted_at`; el ticket desaparece de listas y ya no admite cambios ni seguimientos.
- El ticket y sus eventos previos se conservan; se registra también el actor/fecha de la baja en `ticket_histories`.
- Se agregó `migrations/004_soft_delete_tickets.sql`; aplicarla con un backup verificado antes de usar la acción en una base existente.
- El dashboard cuenta solo tickets activos; detalle, área personal y acciones rechazan tickets dados de baja.

---

## 2026-11-11 — Adscripción por unidad operativa en el dashboard

**Alcance:** el inventario del dashboard puede filtrarse por la adscripción vigente de cada bien, obtenida de SIA sin escribir en PostgreSQL.

- `legacy_postgres_inventory_snapshot()` devuelve los bienes vigentes para sincronización y resuelve las adscripciones de los identificadores locales en la misma transacción de solo lectura.
- La asignación enlaza `BIEN015.cod_dependencia`, `cod_direccion` y `cod_departamento` con `BIEN001`, `BIEN005` y `BIEN006`; la etiqueta usa el departamento y aplica fallback a dirección/dependencia.
- Se agrega la columna y filtro “Adscripción / unidad operativa”; se puede compartir el estado mediante `?adscription=...`.
- Se incorpora un indicador de tickets registrados con enlace a `/tickets.php`, visible solo para roles con acceso a tickets.
- `properties.adscription` permanece sin persistir; los bienes no encontrados en SIA usan el valor local o “Sin adscripción”.
- Se actualizaron `DOMAIN.md`, `DATABASE.md`, `API.md`, `ARCHITECTURE.md`, `TODO.md` y los diagramas de dominio.

---

## 2026-11-09 — Importación del reporte de inventario del sistema de bienes

**Alcance:** `/updates.php` ahora permite cargar el CSV de inventario o el reporte `.xls` descargado directamente del sistema de bienes.

- `include/inventory_import.php` detecta la estructura por encabezados, acepta el XLS exportado como HTML y normaliza sus filas al importador transaccional existente.
- Se conservan el límite de 5 MB/5000 bienes, el reporte de errores por fila y la regla de actualizar solo el estado cuando el identificador ya existe.
- `MUY BUENO` se mapea a `BUENO`; material y color se mantienen en la descripción cuando no aparecen allí.
- `VALOR INCORP.` no se importa porque el esquema actual no tiene un campo equivalente. El XLS binario no compatible y los códigos mal formados se rechazan.
- El archivo de muestra trae el carácter de reemplazo `�` en el material de 12 bienes; se preserva tal como aparece, sin reconstruir el dato perdido.

---

## 2026-11-09 — Importación directa desde PostgreSQL legacy

**Alcance:** se agregó a `/updates.php` la importación directa de bienes desde la base PostgreSQL del sistema de bienes, sin modificar la fuente.

- PDO exige configuración por variables de entorno y lee dentro de una transacción `REPEATABLE READ READ ONLY`; se exige el formato verificado de empresa `38`.
- Se importan hasta 5000 bienes no desincorporados (`BIEN015.desincorporado = 'N'`), con estados de `BIEN004`; `MUY BUENO` se normaliza a `BUENO`.
- Se valida el código compuesto, la reconstrucción contra `cod_bien_mue` y la unicidad del identificador antes de escribir en SQLite.
- Los bienes nuevos se insertan; para los existentes se conserva la regla vigente de actualizar solo el estado.
- El resultado muestra totales de insertados, estados cambiados, sin cambios y errores, además del resultado por fila.
- La configuración usa `SYSTEC_LEGACY_PG_USER`, `SYSTEC_LEGACY_PG_PASSWORD` y variables opcionales de host/puerto/base. No se guardan credenciales en el código.

---

## 2026-11-09 — F2-16: política inicial de backups registrada

**Alcance:** se capturaron los parámetros operativos solicitados; la automatización queda deshabilitada hasta completar los requisitos de TI.

- Diario a las 02:00 `America/Caracas`; retención propuesta de 30 diarias, 12 semanales y 12 mensuales.
- Incluir `storage/db.db` y los CSV fuente; exigir cifrado en reposo.
- Prueba trimestral en entorno aislado; RPO de 24 horas y RTO de 1 día hábil.
- `cron` en Linux; destino lógico propuesto `code/bck/systec`.
- Pendiente: aprobación de TI; confirmar ruta absoluta y separación del host, mecanismo/custodia de cifrado, definición de semana, host/usuario real y monitoreo.
- `docs/BACKUP.md` contiene el procedimiento y los criterios para habilitar la tarea. No se instaló cron ni se generó una copia sin cifrar.

---

## 2026-11-09 — F2-16: selección de retención semanal/mensual

- Se registró promover la última copia diaria de cada semana y del mes como snapshots semanales/mensuales.
- La propuesta aún requiere aprobación de TI y definición de semana, destino absoluto/separación del host, cifrado/custodia, host y cuenta de cron, y canal de alertas.
- No se activó ninguna programación mientras esos controles sigan pendientes.

---

## 2026-11-09 — Avance F2: normalización y migraciones

**Alcance:** se aplicaron las reglas de datos aprobadas por el dueño después de crear y verificar un backup restaurable.

### Agregado

- `bin/migrate.php` — runner CLI que exige un backup legible con integridad y conteos coincidentes.
- `migrations/001_normalize_legacy_data.sql` — normaliza estados, tipos, técnico y fechas; agrega triggers de catálogo y transición.
- `migrations/002_add_indexes.sql` — índices para `ident`, `property_id` y `(status, next_maintenance_date)`.
- `schema_migrations` — registra las migraciones aplicadas.

### Cambiado

- `conn.php`, `include/helpers.php` — `evaluativo` forma parte del catálogo y la UI.
- `storage/db.db` — 2 bienes `MUY BUENO` pasaron a `BUENO`; 194 estados `FINALIZADO` quedaron como `finalizado`; 153 técnicos `ndomiguez` se corrigieron a `ndominguez`; las fechas se normalizaron y los campos de próxima fecha vacíos pasaron a `NULL`.
- Los valores históricos de `time_taken` y sus 15 nulos se conservaron; no hay fechas de inicio/fin para recalcularlos.

### Evidencia

- Backup `storage/backups/2026-11-09/db-before-f2-normalization.db`: conteos 617/547/4, `integrity_check=ok` y restore aislado con los mismos conteos.
- Migraciones ejecutadas en la base activa y probadas sobre una copia y una base temporal recién inicializada; segunda ejecución no repite cambios.
- Verificado: cero estados/tipos fuera de catálogo, cero técnicos con typo, cero fechas no interpretables o futuras; 3 índices creados.
- Triggers probados en SQLite temporal: rechazan estado de bien inválido y transición no permitida; permiten `evaluativo`.

### Pendiente en F2

- Calendario, retención y cifrado de backups.
- Las duraciones históricas no se recalculan sin fuente verificable.
- No hay suite automatizada persistente; las pruebas ejecutadas fueron aisladas/manuales.

---

## 2026-11-09 — Avance F2: importaciones y reporte de dañados

**Alcance:** se retomó F2 sin modificar los datos legacy.

### Cambiado

- `include/csv_import.php` — valida archivos CSV de hasta 5 MB y 5000 filas; ejecuta cada importación en una transacción SQLite y descarta el reporte si la transacción falla.
- `imports.php`, `updates.php` — importaciones confirmadas con POST usan el manejador transaccional; los fallos se registran y notifican.
- `conn.php` — el reporte de bienes dañados usa `SELECT DISTINCT` para no repetir un bien con varias notas coincidentes.

### Evidencia

- `php -l` limpio en los archivos PHP afectados.
- Prueba aislada con SQLite temporal: deduplicación con varias notas, commit, rollback y rechazo del límite de 5000 filas.
- No se modificaron `storage/db.db` ni archivos CSV.

### Pendiente en F2

- Normalización de datos requiere resolver los bloqueos de definición y verificar un backup antes de mutar datos.
- HTTPS/HSTS sigue pendiente del despliegue; F1 no se considera cerrada hasta resolverlo.

---

## 2026-11-09 — F1: contención de seguridad (P0)

**Alcance:** implementación de autenticación, autorización, SQL parametrizado, escape de salida, CSRF, datos fuera del webroot y endurecimiento base. No se normalizaron datos legacy ni se agregaron migraciones (F2/F3).

### Agregado

- `include/config.php` — rutas de `storage/`, base de datos y `url()`.
- `include/security.php` — `display_errors`, log fuera del webroot y cabeceras de seguridad.
- `include/auth.php` — sesión, login, roles, CSRF y redirecciones seguras.
- `login.php`, `logout.php` — autenticación y cierre de sesión.
- `bin/create-user.php` — alta/actualización de usuarios por CLI.
- `router.php` — router del servidor embebido y denegación de rutas sensibles.
- `.htaccess` — denegación equivalente para Apache.
- Tabla `users` (`id`, `username`, `password_hash`, `role`, `active`, `created_at`).

### Cambiado

- `conn.php` — `prepare()`/`bindValue()` en todas las consultas; listas blancas de tabla/columna; `db()` singleton; sin cierre interno de conexión; `PRAGMA foreign_keys` y `busyTimeout`.
- `include/helpers.php` — compatible con PHP 7.4 (`switch` en vez de `match`), `e()`, `js()`, `property_next_status()` y `maintenance_transition_allowed()`.
- `include/header.php` — navegación con usuario, rol y cierre de sesión por POST.
- `dashboard.php`, `dashboard/table/index.php`, `dashboard/actions/status.php` — escape, CSRF, POST y autorización.
- `maintenances.php`, `maintenances/form/*`, `maintenances/table/crud.php` — escape, CSRF, POST y validación de transiciones.
- `maintenances/register.php`, `maintenances/update.php`, `maintenances/actions/status.php` — autorización, CSRF, `validations/update.php`, validación de bien/fechas/estado y cálculo de horas totales.
- `historials.php`, `historials/main.php`, `historials/membrete.php`, `historials/report.php` — escape, fechas actuales y bien inexistente controlado.
- `report.php`, `batch.php`, `imports.php`, `updates.php`, `properties/table/propertiesCsv.php`, `maintenances/table/import.php` — escape, auth, POST + confirmación y reporte de importación.
- `include/head.php`, `include/footer.php` — escape y rutas.
- `Makefile` — `php -S localhost:5050 router.php`.
- `.gitignore` — datos, logs y secretos locales.

### Corregido

- **H-01:** autenticación y roles en todas las rutas.
- **H-02:** SQL parametrizado con listas blancas.
- **H-03:** escape de salida con `e()`.
- **H-04:** `update.php` usa `validations/update.php` y valida `id`/`status`.
- **H-05:** `time_taken` usa la diferencia total de horas.
- **H-12:** `db.db`, backups y CSV movidos a `storage/` y denegados por router/htaccess.
- **H-15:** importación por POST con confirmación, reporte y `time_taken` nulo.
- **H-16:** eliminado `match` de PHP 8.
- **H-17:** falso positivo: `vendor/datatables/datatables.min.js` incluye jQuery 3.7.0 antes de DataTables.
- **H-18/H-19/H-14:** eliminados `properties/table/properties.php`, `historials/chart.php` e `include/es-ES.json`.
- **H-21:** `.gitignore` corregido.
- **H-09:** `batch.php` inicializa `$property_id` y maneja bien inexistente.
- **H-10:** fechas y técnico hardcodeados reemplazados por valores actuales.
- **H-11:** la conexión ya no se cierra dentro de los métodos de `DB`.

### Evidencia

- `php -l` limpio en todos los archivos de aplicación.
- Humo HTTP: `/` → 302 a login; `/storage/db.db`, `/properties.csv`, `/docs/README.md` y `/conn.php` → 403.
- Login admin → dashboard 200; login inválido → mensaje de error.
- POST de estado sin CSRF → 419; POST con rol `consulta` → 403.
- SQLi en `historials.php` → bien no encontrado; XSS en `search` → escape Unicode.
- Mutaciones probadas sobre copia: alta (547→548), edición, `iniciado→revision→finalizado` con `time_taken=1`, importación de 1 fila (548→549, `time_taken` nulo) y restore exacto del backup.

### Hallazgos abiertos

- **H-06:** transiciones ya validadas en servidor; falta `CHECK`/trigger en BD (F2-05).
- **H-07:** catálogos legacy incompletos (`MUY BUENO`, `evaluativo`, `FINALIZADO`).
- **H-08:** el reporte de dañados aún puede duplicar bienes.
- **H-13:** sin migraciones ni pruebas automatizadas.
- **H-20:** `validations/update.php` ahora se usa; el archivo dejó de ser código muerto.
- **D-01…D-10:** normalización de datos pendiente.

### No modificado

- Datos legacy: 617 bienes y 547 mantenimientos (el backup de prueba se restauró y verificó por MD5).
- `docs/templatesOthersApp/` (referencia externa).
- No se agregaron índices, `CHECK`, `FOREIGN KEY` ni `schema_migrations`.

---

## 2026-11-09 — Baseline documental v1.0

**Alcance:** análisis inverso completo del sistema y creación de la estructura documental.

### Agregado

- `docs/README.md` — mapa, lectura por rol y proceso documental.
- `docs/PROJECT.md` — problema, usuarios, alcance, métricas y restricciones.
- `docs/ARCHITECTURE.md` — stack, componentes, request, despliegue y deudas.
- `docs/DOMAIN.md` — glosario, entidades, reglas, estados y casos límite.
- `docs/DATABASE.md` — DDL, diccionario, calidad de datos, migraciones y backups.
- `docs/API.md` — superficie HTTP real (páginas y acciones).
- `docs/SECURITY.md` — hallazgos P0–P3, controles y checklist.
- `docs/CONVENTIONS.md` — estructura, nombres, estilo y patrones.
- `docs/DECISIONS.md` — ADR-001–014 reconstruidos/propuestos.
- `docs/TODO.md` — estado, backlog priorizado y bloqueos.
- `docs/CHANGELOG.md` — este archivo.
- `docs/anexos/inventario-tecnico.md` — análisis folder por folder y archivo por archivo.
- `docs/anexos/matriz-trazabilidad.md` — hallazgo → código → documento → tarea.
- `docs/anexos/plan-documentacion.md` — plan por fases y criterios de aceptación.
- `grafo/README.md` — índice y leyenda del grafo.
- `grafo/logica-negocio.md` — capacidades, flujos, estados y reglas.
- `grafo/modelo-datos.md` — ERD, diccionario y calidad.
- `grafo/grafo.json` — grafo legible por máquina.

### Evidencia del análisis inicial (snapshot pre-F1/F2)

| Dato | Valor |
|---|---|
| Archivos PHP analizados | 27 |
| Líneas de código (sin vendor/docs) | ~2.000 |
| Tablas SQLite | 2 (`properties`, `maintenances`) |
| Bienes | 617 |
| Mantenimientos | 547 |
| Bienes sin mantenimiento | 450 |
| Mantenimientos vencidos | 72 |
| Backups disponibles | `bck/2025-04-09/` |
| Versiones vendorizadas | Bootstrap 5.3.2, DataTables 1.13.7, jQuery 3.7.0 |
| PHP del entorno | 7.4.33 |

### Hallazgos principales

- **H-01:** sin autenticación ni autorización.
- **H-02:** SQL por concatenación en cinco métodos de `DB`.
- **H-03:** salida HTML sin escape en múltiples vistas.
- **H-04:** `update.php` usa `validations/register.php`; `validations/update.php` es código muerto.
- **H-05:** `time_taken` usa solo `DateInterval->h`.
- **H-06:** transiciones de estado sin guardia.
- **H-07:** catálogos incompletos (`MUY BUENO`, `evaluativo`, `FINALIZADO`).
- **H-08:** el reporte de dañados puede duplicar bienes.
- **H-09:** `batch.php` usa `$property_id` antes de definirlo.
- **H-10:** fechas y nombres hardcodeados en reportes.
- **H-11:** cierre de conexión dentro de `DB::create()`.
- **H-12:** `db.db`, backups y CSV bajo el webroot.
- **H-13:** sin migraciones ni pruebas.
- **H-14:** `include/es-ES.json` no se referencia.
- **H-15:** importación sin transacción ni reporte de omitidos.
- **H-16:** `include/helpers.php` usa `match` (PHP 8) con entorno reportado PHP 7.4.
- **H-17:** orden de carga de DataTables/jQuery dudoso en `include/head.php`.
- **H-18:** `properties/table/properties.php` sin uso.
- **H-19:** `historials/chart.php` sin uso.
- **H-20:** `validations/update.php` sin uso.
- **H-21:** `.gitignore` con patrón `/.`.
- **H-22:** `README.md` sin información funcional; resuelto por este baseline.

### Decisiones registradas

- ADR-001 a ADR-012: reconstruidas del código.
- ADR-013: propuesta de endurecimiento progresivo.
- ADR-014: propuesta de repositorios + migraciones.

### No modificado

- No se tocó código de aplicación.
- No se modificó `db.db`.
- No se alteraron los CSV ni los backups.
- No se eliminó `docs/templatesOthersApp/` (referencia externa).

### Próximo paso recomendado

Ejecutar la fase **P0** de `TODO.md`: autenticación, SQL parametrizado, escape, CSRF y reubicación de `db.db` fuera del webroot. No exponer el sistema fuera de la red de confianza hasta completar el checklist de `SECURITY.md`.

---

## 2025-04-09 — Evidencia de backup (preexistente)

- `bck/2025-04-09/db.db`: 597 bienes, 321 mantenimientos.
- `bck/csv/properties.csv`: copia del inventario.
- No hay registro de quién ni por qué se generó; se conserva como evidencia.

---

## Cómo agregar una entrada

```markdown
## YYYY-MM-DD — Título corto

**Alcance:** qué se cambió.

### Agregado / Cambiado / Corregido / Eliminado
- ...

### Evidencia
- Archivos, comandos o capturas.

### Hallazgos
- IDs `H-xx` o `D-xx` si aplica.

### No modificado
- ...
```

---

Ver también: `README.md`, `TODO.md`, `DECISIONS.md`, `docs/anexos/plan-documentacion.md`.
