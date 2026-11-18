# Plan y Registro de Oportunidades de Mejora — SysTec GMB

> **Estado:** Activo · **Creado:** 2026-11-18 · **Objetivo:** Registro centralizado de las oportunidades de mejora técnicas, de arquitectura, seguridad, rendimiento y UX identificadas en la auditoría general de SysTec para permitir su ejecución progresiva y trazable.

---

## 1. Resumen Ejecutivo y Matriz de Priorización

Las oportunidades identificadas complementan y refinan el backlog histórico de `docs/TODO.md` (fases F1 a F4). Se priorizan según su impacto operativo, riesgo de seguridad y esfuerzo técnico requerido.

| ID | Área | Oportunidad | Prioridad | Esfuerzo | Estado |
|---|---|---|---|---|---|
| **SEC-01** | Seguridad | Eliminar archivos sensibles del webroot y git (`db.db`, `.xls`, `bck/`) | P0 | Bajo | ✅ Completado |
| **SEC-02** | Seguridad | Rate limiting y bloqueo por fuerza bruta en login | P0 | Bajo | ✅ Completado |
| **PERF-01** | Rendimiento | Desacoplar sincronización PostgreSQL de peticiones GET en dashboard | P1 | Medio | ✅ Completado |
| **PERF-02** | Rendimiento | Resolver consulta N+1 y paginar en servidor en `tickets.php` | P1 | Medio | ✅ Completado |
| **PERF-03** | Rendimiento | Retirar DDL `ensureSchema()` del constructor de `DB` | P1 | Bajo | ✅ Completado |
| **ARCH-01** | Arquitectura | Modularizar monolitos en repositorios y servicios (ADR-014) | P2 | Alto | ✅ Completado |
| **ARCH-02** | Arquitectura | Catálogos dinámicos en base de datos (técnicos y estados) | P2 | Medio | ✅ Completado |
| **UI-01** | UI/UX | Selector de estado del bien con confirmación y motivo (ADR-007) | P2 | Medio | ✅ Completado |
| **UI-02** | UI/UX | Corregir técnico vacío en firma de historial (`historials.php`) | P2 | Bajo | ✅ Completado |
| **UI-03** | UI/UX | Subsanar accesibilidad y etiquetas semánticas (h1, text-body-secondary, caption) | P2 | Bajo | ✅ Completado |
| **TEST-01** | Testing/CI | Pruebas de integración para auth, roles, CSRF y transiciones | P2 | Medio | ✅ Completado |
| **TEST-02** | Testing/CI | Pipeline de integración continua (GitHub Actions / CI local) | P2 | Bajo | ✅ Completado |
| **OPS-01** | Operaciones | Automatización de backups vía cron con rotación y verificación | P3 | Medio | ✅ Completado |
| **OPS-02** | Operaciones | Interfaz de administración de usuarios y cambio de contraseña | P3 | Medio | ✅ Completado |

---

## 2. Detalle de Oportunidades por Área

### Área 1: Seguridad y Superficie de Exposición

#### SEC-01 · Eliminación de artefactos sensibles en la raíz del webroot
* **Diagnóstico:** En la raíz del repositorio se encuentran archivos físicos rastreados en Git: `db.db` (274 KB), `dataFile/Rpt_Inventario_bienes_muebles.xls`, `maintenances.csv`, `properties.csv` y la carpeta `bck/`. Además, `.htaccess` no deniega archivos `.xls`, dejando el reporte expuesto a descargas no autenticadas en servidores Apache.
* **Archivos:** `db.db`, `dataFile/`, `bck/`, `.htaccess`, `maintenances.csv`, `properties.csv`.
* **Solución propuesta:**
  1. Quitar los archivos del índice de Git (`git rm --cached`).
  2. Mover copias necesarias a `storage/backups/`.
  3. Actualizar la regla `FilesMatch` en `.htaccess` para incluir `xls`.
* **Criterio de aceptación:** `git status` no rastrea copias en la raíz; petición HTTP directa a `dataFile/Rpt_Inventario_bienes_muebles.xls` devuelve 403.

#### SEC-02 · Protección contra ataques de fuerza bruta en Login
* **Diagnóstico:** `login.php` permite envíos POST ilimitados sin retraso ni bloqueo temporal, permitiendo ataques por diccionario contra cuentas de administración.
* **Archivos:** `login.php`, `include/auth.php`.
* **Solución propuesta:** Implementar bloqueo tras 5 intentos fallidos consecutivos por IP/usuario con ventana de 15 minutos en almacenamiento local o sesión.
* **Criterio de aceptación:** Al sexto intento fallido en menos de 15 minutos se responde con HTTP 429 y mensaje de bloqueo temporal.

---

### Área 2: Rendimiento y Base de Datos

#### PERF-01 · Desacoplar sincronización externa en peticiones GET de `dashboard.php`
* **Diagnóstico:** `dashboard.php` (L85-126) llama a `legacy_postgres_inventory_snapshot()` en cada carga GET, con un timeout de 5s y escrituras automáticas en SQLite. Si la base PostgreSQL está caída o lenta, congela la interfaz.
* **Archivos:** `dashboard.php`, `include/inventory_import.php`, `sync-assignments.php`.
* **Solución propuesta:** Consumir únicamente datos locales de `properties` y la caché local de adscripciones (`inventory-adscriptions-*.json`). La sincronización con PostgreSQL debe ocurrir exclusivamente desde `sync-assignments.php` (POST con confirmación) o script CLI.
* **Criterio de aceptación:** `dashboard.php` carga en menos de 200 ms sin dependencias de red en tiempo real.

#### PERF-02 · Resolver consulta N+1 y paginar en servidor en `tickets.php`
* **Diagnóstico:** Para cada ticket en la lista, se consultan secuencialmente el bien, el usuario creador y el historial con actores. Cien tickets generan más de 300 consultas.
* **Archivos:** `tickets.php`, `conn.php`.
* **Solución propuesta:** Migrar la consulta a un único `JOIN` con `properties`, `users` y `ticket_status`, agregando paginación `LIMIT / OFFSET` similar a la implementada en `personal/index.php`.
* **Criterio de aceptación:** La carga de la bandeja de tickets ejecuta un número constante de consultas (≤ 2).

#### PERF-03 · Retirar DDL `ensureSchema()` del constructor de `DB`
* **Diagnóstico:** Cada request ejecuta 6 sentencias `CREATE TABLE IF NOT EXISTS` y 5 `INSERT OR IGNORE`, sumando sobrecarga innecesaria en SQLite.
* **Archivos:** `conn.php`, `bin/migrate.php`.
* **Solución propuesta:** Trasladar la inicialización exclusivamente a las migraciones versionadas y asegurar que `new DB()` sea ligero.
* **Criterio de aceptación:** Ningún `CREATE TABLE` se ejecuta en peticiones web ordinarias.

---

### Área 3: Arquitectura y Calidad de Código

#### ARCH-01 · Separar capa de datos y modularizar monolitos (ADR-014)
* **Diagnóstico:** Archivos de más de 800-1200 líneas (`maintenances.php`, `conn.php`, `dashboard.php`) agrupan presentación, acceso a datos, validación y assets.
* **Archivos:** `conn.php`, `maintenances.php`, `dashboard.php`.
* **Solución propuesta:** Extraer repositorios especializados (`PropertyRepository`, `MaintenanceRepository`, `TicketRepository`) y desacoplar controladores de vistas.
* **Criterio de aceptación:** Clases con responsabilidades únicas y páginas PHP con menos de 300 líneas de lógica coordinadora.

#### ARCH-02 · Catálogos de técnicos y estados administrables
* **Diagnóstico:** La lista de técnicos autorizados está fija en `conn.php` (`jmedina`, `ndominguez`).
* **Archivos:** `conn.php`, `maintenances/form/fields.php`.
* **Solución propuesta:** Consultar técnicos directamente de la tabla `users WHERE role = 'tecnico'` o tabla de catálogo.
* **Criterio de aceptación:** Crear o desactivar un técnico en el sistema actualiza inmediatamente los selectores de mantenimiento.

---

### Área 4: Experiencia de Usuario (UI/UX) y Reportes

#### UI-01 · Selector de estado del bien con confirmación y motivo
* **Diagnóstico:** El botón cíclico en `dashboard.php` altera el estado físico (`BUENO → MALO → REGULAR → BUENO`) sin confirmación ni registro de justificación (ADR-007 cuestionado).
* **Archivos:** `dashboard.php`, `dashboard/actions/status.php`, `dashboard/table/index.php`.
* **Solución propuesta:** Sustituir la acción cíclica por un modal o selector con confirmación y campo de motivo opcional.
* **Criterio de aceptación:** El cambio exige selección explícita del estado destino y registra la acción mediante POST con CSRF.

#### UI-02 · Firma en reportes de historial de mantenimientos
* **Diagnóstico:** En `historials.php` la variable `$technician = '';` está vacía, imprimiendo "Realizado por:" en blanco en la firma.
* **Archivos:** `historials.php`, `batch.php`.
* **Solución propuesta:** Usar por defecto el técnico del último mantenimiento asociado o el usuario autenticado que emite el reporte.
* **Criterio de aceptación:** El pie de firma muestra el nombre del responsable y la fecha actual formateada.

#### UI-03 · Subsanar hallazgos de accesibilidad y diseño de `audit-page.sh`
* **Diagnóstico:** `audit-page.sh` detecta falta de encabezado `<h1>` semántico en `dashboard.php`, uso de clases legadas `text-muted` y falta de subtítulos accesibles en tablas de tickets.
* **Archivos:** `dashboard.php`, `dashboard/table/index.php`, `tickets.php`, `personal/index.php`.
* **Solución propuesta:** Ajustar la jerarquía de encabezados (`h1.h3`), reemplazar `text-muted` por `text-body-secondary` (Bootstrap 5.3) y agregar `<caption class="visually-hidden">` en tablas.
* **Criterio de aceptación:** `audit-page.sh` finaliza con 0 fallos (`FAIL`) en todas las vistas auditadas.

---

### Área 5: Pruebas Automatizadas y Operaciones

#### TEST-01 · Suite de pruebas de seguridad y flujos HTTP
* **Diagnóstico:** No hay pruebas unitarias ni de integración para el control de sesiones, roles (`admin`, `tecnico`, `consulta`, `personal`), tokens CSRF ni transiciones de mantenimiento.
* **Archivos:** `tests/Unit/`, `phpunit.xml.dist`.
* **Solución propuesta:** Implementar pruebas PHPUnit que verifiquen denegaciones de roles (403), expiración de CSRF (419) y máquina de estados de mantenimientos.
* **Criterio de aceptación:** Cobertura de pruebas sobre las reglas críticas de autorización.

#### TEST-02 · Integración Continua (CI)
* **Diagnóstico:** No existe pipeline automatizado que impida merges con tests fallidos o errores de sintaxis PHP.
* **Archivos:** `.github/workflows/ci.yml`.
* **Solución propuesta:** Crear workflow de GitHub Actions para ejecutar `php -l` en todos los archivos PHP y correr la suite `composer test`.
* **Criterio de aceptación:** El repositorio ejecuta las pruebas automáticamente en cada push o pull request.

#### OPS-01 · Automatización y verificación periódica de Backups (ADR-018)
* **Diagnóstico:** Las directivas de `docs/BACKUP.md` no cuentan con script programable en `cron` para rotación 30/12/12 con cifrado en reposo.
* **Archivos:** `bin/backup.sh` (por crear), `docs/BACKUP.md`.
* **Solución propuesta:** Script de respaldo con sqlite3 `.backup` o copia segura, compresión y rotación automática.
* **Criterio de aceptación:** Ejecución sin intervención humana con log de verificación de integridad (`PRAGMA integrity_check`).

#### OPS-02 · Gestión de usuarios y contraseñas en interfaz web
* **Diagnóstico:** Los usuarios no pueden cambiar su propia contraseña; los administradores no tienen panel web para crear/desactivar usuarios.
* **Archivos:** `personal/`, `include/auth.php`, `users.php` (por crear).
* **Solución propuesta:** Vista de perfil con cambio de contraseña y módulo administrativo simple para gestión de cuentas.
* **Criterio de aceptación:** Usuario puede actualizar su contraseña cumpliendo requisitos mínimos de seguridad.
