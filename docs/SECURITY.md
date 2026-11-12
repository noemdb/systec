# SECURITY.md — SysTec GMB

> **Estado:** v1.4 (ámbito y rutas de tickets actualizados) · **Actualizado:** 2026-11-11.
> **Resumen ejecutivo:** F1 implementó autenticación, roles, CSRF, escape, SQL parametrizado y protección de `storage/`. F2 agregó transacciones y límites de importación, normalizó datos aprobados, aplicó triggers e índices. Siguen pendientes HTTPS/HSTS en el servidor real, rate limiting, auditoría, calendario/cifrado de backups y pruebas automatizadas. No exponer fuera de la red de confianza hasta cerrar el checklist de despliegue.

---

## 1. Activos a proteger

| Activo | Ubicación | Sensibilidad |
|---|---|---|
| Inventario de bienes | `storage/db.db` → `properties` | Media |
| Historial de mantenimientos | `storage/db.db` → `maintenances` | Media |
| Nombres de técnicos y notas | `storage/db.db` → `maintenances` | Baja/Media (PII laboral) |
| Usuarios y hashes | `storage/db.db` → `users` | Alta |
| Base completa | `storage/db.db` | Alta (agregado) |
| Backups | `storage/backups/2026-11-09/db-before-f2-normalization.db` | Alta |
| CSV legacy | `storage/properties.csv`, `storage/maintenances.csv` | Alta |
| Logs de PHP | `storage/logs/php-error.log` | Media |
| Código fuente | `*.php` | Media (revela lógica y rutas) |
| Credencial NVIDIA Build | `.env` → `API_KEY_NVIDIA_BUILD` | Alta |

### Redacción asistida por NVIDIA Build

`bin/improve-maintenance-narrative.php` usa la API de NVIDIA Build con `nvidia/nemotron-3.5-lightning-30b-a3b`. Solo transmite `description`, `failure_reason` y `notes`; no envía IDs, códigos de bienes, fechas, técnicos ni estados. Los textos podrían contener datos sensibles por sí mismos. La vista previa también realiza una solicitud externa, aunque no persiste cambios. `API_KEY_NVIDIA_BUILD` se carga desde el entorno o `.env`, y se envía en el encabezado Bearer sin registrarse. El endpoint está sujeto a la cuota y disponibilidad del servicio de prueba de NVIDIA. La generación de todas las propuestas termina antes de iniciar la transacción; si la API falla, no se guardan cambios parciales.

---

## 2. Modelo de amenazas

| Amenaza | Probabilidad | Impacto | Mitigación actual | Estado |
|---|---|---|---|---|
| Acceso no autorizado a la app | Media | Alto | Login, sesión y `require_login()` | ✅ |
| Descarga directa de `db.db` | Baja | Crítico | `storage/` + router/.htaccess | ✅ |
| Inyección SQL vía GET/POST | Baja | Crítico | Prepared statements + listas blancas | ✅ |
| XSS almacenado en descripciones/notas | Baja | Alto | Escape con `e()` + CSP | ✅ |
| CSRF en cambios de estado | Baja | Medio | Token por sesión + POST obligatorio | ✅ |
| Pérdida de datos por borrado/fallo | Media | Alto | Propuesta diaria 02:00, retención 30/12/12, RPO 24 h y RTO 1 día hábil; falta aprobación TI, destino separado, cifrado, cron y prueba trimestral operativa (`BACKUP.md`) | ⚠️ |
| Suplantación de técnico | Media | Medio | Usuario autenticado; técnico aún es catálogo fijo | ⚠️ |
| Modificación silenciosa de datos | Media | Alto | Sin auditoría (`audit_events`) | ⛔ |
| Fuga por backups/CSV | Baja | Alto | `storage/` + denegación | ✅ |
| DoS por importación masiva | Baja | Medio | Límite de 5 MB/5000 filas; sin rate limiting | ⚠️ |
| Clickjacking | Baja | Medio | `X-Frame-Options: DENY` + CSP | ✅ |
| Fuerza bruta | Media | Medio | Login existe; sin rate limiting | ⚠️ |

---

## 3. Hallazgos priorizados

### P0 — Bloqueantes para exponer el sistema

#### H-01 · Sin autenticación ni autorización — ✅ Resuelto en F1

- **Evidencia original:** no existía login, sesión ni comprobación de permisos.
- **Implementación:** `include/auth.php` con `password_hash`/`password_verify`, sesión `HttpOnly`/`SameSite=Lax`, `require_login()`, `require_role()` y roles `admin`/`operador`/`tecnico`/`consulta`/`personal`. `login.php`, `logout.php` y `bin/create-user.php` completan el flujo.
- **Evidencia de verificación:** `/` → 302 a `login.php`; `consulta` recibe 403 al intentar cambiar estado; el formulario de estado no se renderiza para ese rol; `personal` es redirigido al ámbito `/personal/*` y bloqueado fuera de ese prefijo.
- **Ámbito personal:** el rol `personal` tiene un subespacio propio en `/personal/` con sus pantallas, acciones y permisos. Cualquier acceso a `/dashboard.php`, `/maintenances.php`, `/tickets.php` o cualquier ruta ajena al prefijo personal se redirige al área personal o al login, según la sesión.
- **Tickets:** `admin`, `operador` y `tecnico` acceden a la bandeja y a los endpoints de registro/carga de bienes de soporte. `personal` usa endpoints bajo `/personal/`, con consultas limitadas a tickets propios. La baja lógica desde soporte se limita a `admin` y `operador`; todas las mutaciones requieren POST + CSRF.
- **Pendiente:** auditoría de cambios (`audit_events`) y rate limiting de login (F3).

#### H-02 · Inyección SQL por concatenación — ✅ Resuelto en F1

- **Evidencia original:** `index()`, `getFirstForId()`, `getFirstForConditions()`, `getCodeId()` y `update()` concatenaban valores.
- **Implementación:** `conn.php` usa `prepare()`/`bindValue()` en todas las consultas; tabla y columnas se validan contra listas blancas (`$schema`); IDs con `filter_var(..., FILTER_VALIDATE_INT)`; orden con regex + lista blanca.
- **Evidencia de verificación:** payload `property_id=1 OR 1=1` devuelve “bien no encontrado”; `php -l` limpio.
- **Pendiente:** suite automatizada de SQLi (F3).

#### H-03 · XSS por salida sin escape — ✅ Resuelto en F1

- **Evidencia original:** se imprimían sin escape `description`, `notes`, `failure_reason`, `model`, `serial`, `color`, `code` y `technician` en dashboard, mantenimientos, historial e importadores.
- **Implementación:** helper `e()` y `js()` en `include/helpers.php`; todas las vistas escapan datos; `data-detail` usa `json_encode` + `htmlspecialchars`; CSP en `include/security.php`.
- **Evidencia de verificación:** `search=<script>alert(1)</script>` se serializa como `\u003Cscript...` y no se inserta como etiqueta.
- **Pendiente:** revisión manual de nuevas vistas y pruebas automatizadas (F3).

#### H-12 · Base, backups y CSV bajo el webroot — ✅ Resuelto en F1

- **Evidencia original:** `db.db`, `bck/2025-04-09/db.db`, `properties.csv` y `maintenances.csv` estaban en la raíz servida.
- **Implementación:** datos movidos a `storage/` (`storage/db.db`, `storage/backups/...`, `storage/*.csv`); `router.php` deniega `storage/`, `bck/`, `docs/`, `grafo/`, `spec/`, `bin/`, `include/` y extensiones `.db`, `.csv`, `.log`, `.md`; `.htaccess` aplica reglas equivalentes en Apache.
- **Evidencia de verificación:** `/storage/db.db`, `/properties.csv`, `/docs/README.md` y `/conn.php` devuelven 403.
- **Pendiente:** confirmar la configuración del servidor real (Apache/Nginx) y que `storage/` quede fuera del `DocumentRoot`.

### P1 — Altos

#### H-06 · Transiciones de estado sin guardia — ✅ Resuelto en F2

- **Evidencia original:** `maintenances/actions/status.php` aceptaba cualquier `status` por GET; `update.php` permitía cualquier estado.
- **Implementación:** `maintenance_transition_allowed()` valida `iniciado→revision|finalizado`, `revision→finalizado`, `finalizado→revision`; `update.php` y `actions/status.php` rechazan estados fuera de catálogo y transiciones no permitidas (422); ambos usan POST + CSRF.
- **Evidencia de verificación:** `finalizado→iniciado` devuelve 422 con mensaje de transición no permitida.
- **Implementación F2:** normalización de `FINALIZADO`; triggers SQLite rechazan estados y transiciones inválidos.
- **Evidencia:** triggers ejercitados en base temporal; pruebas automatizadas persistentes siguen pendientes (F3).

#### H-13 · Migraciones y pruebas automatizadas parciales — 🧪 Parcial

- **Evidencia original:** esquema base en `conn.php`; no había `tests/`, `composer.json`, `phpunit.xml` ni CI.
- **Implementación:** migraciones versionadas y suite PHPUnit básica en `tests/`, ejecutable con `composer test`.
- **Impacto pendiente:** cobertura incompleta, sin CI y sin pruebas automatizadas de seguridad end-to-end.
- **Implementación F2:** `bin/migrate.php`, `migrations/` y `schema_migrations`; las migraciones corren transaccionalmente y requieren backup verificado.
- **Pendiente:** ampliar la suite, automatizar pruebas de seguridad y configurar CI local (F3).

#### H-15 · Importación sin transacción ni reporte — ✅ Resuelto en F2

- **Evidencia original:** `updates.php` y `imports.php` escribían al abrir la página; las filas sin bien se omitían en silencio.
- **Implementación:** ambas rutas exigen `admin`/`operador`, usan POST + CSRF + pantalla de confirmación y muestran conteos de insertados/omitidos/erróneos por fila. `imports.php` ya no usa `rand()`: `time_taken` se guarda nulo. `updates.php` reporta filas procesadas y errores.
- **Evidencia de verificación:** importación de 1 fila insertó 1 y reportó 0 omitidas/0 errores; el backup se restauró después.
- **Implementación F2:** cada archivo se procesa en una transacción, con límite de 5 MB/5000 filas; el informe por fila se confirma junto con el commit.
- **Pendiente:** pruebas automatizadas de seguridad/importación (F3).

#### H-16 · `match` (PHP 8) con entorno PHP 7.4 — ✅ Resuelto en F1

- **Evidencia original:** `include/helpers.php` usaba `match`.
- **Implementación:** reemplazado por `switch`; `php -l` limpio con PHP 7.4.33 y pruebas de humo de dashboard, mantenimientos, historial y reporte.
- **Pendiente:** declarar la versión mínima de PHP en el despliegue.

### P2 — Medios

| ID | Hallazgo | Evidencia | Remediación | Estado |
|---|---|---|---|---|
| H-04 | `update.php` valida con `register.php` | `maintenances/update.php` | Incluye `validations/update.php` y valida `id`/`status` | ✅ F1 |
| H-05 | `time_taken` mal calculado | `conn.php::restarFechasEnHoras` | Diferencia total en horas (`abs(timestamp diff)/3600`) | ✅ F1 |
| H-07 | Catálogos incompletos | `include/helpers.php`, `conn.php`, migración F2 | `evaluativo` aprobado; estados/técnicos normalizados y protegidos | ✅ F2 |
| H-08 | Reporte de dañados duplica | `conn.php::indexWithMaintenances` | `SELECT DISTINCT`; verificado con varias notas | ✅ F2 |
| H-10 | Fechas/nombres hardcodeados | `historials.php`, `historials/membrete.php` | Usar fecha actual y técnico real | ✅ F1 |
| H-11 | Cierre de conexión dentro de `DB` | `conn.php::create`, `createOrUpdate` | No cerrar dentro; cerrar en el llamador | ✅ F1 |
| H-14 | Traducción DataTables no usada | `include/es-ES.json` | Archivo eliminado | ✅ F1 |

### P3 — Bajos

| ID | Hallazgo | Remediación | Estado |
|---|---|---|---|
| H-09 | `$property_id` indefinido en `batch.php` | Inicializar antes de la consulta | ✅ F1 |
| H-17 | Orden de carga DataTables/jQuery dudoso | Verificado: `datatables.min.js` incluye jQuery 3.7.0 | ✅ Falso positivo |
| — | Sin headers de seguridad | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, CSP | ✅ F1 |
| — | Sin límites de importación | Límite 5 MB/5000 filas | ✅ F2 |
| — | Sin logs | Log de PHP en `storage/logs/` sin PII | 🧪 F1 (rotación pendiente) |
| — | Sin CSRF | Token por sesión en todos los POST y acciones de estado | ✅ F1 |
| — | Sin rate limiting | Limitar intentos de login e importación | 🔲 F3 |

---

## 4. Controles recomendados (objetivo)

### 4.1 Autenticación

- Sesión PHP nativa o implementación mínima con `password_hash(PASSWORD_DEFAULT)`.
- Cookie `HttpOnly`, `Secure` (bajo HTTPS), `SameSite=Lax`.
- Expiración por inactividad y absoluta.
- Recuperación de acceso asistida por administrador.
- Rate limiting en login (por ejemplo, 5 intentos/minuto/IP).

### 4.2 Autorización

| Acción | Admin | Operador | Técnico | Consulta | Personal |
|---|---|---|---|---|---|
| Ver dashboard/historial/reportes | ✅ | ✅ | ✅ | ✅ | — |
| Cambiar estado de bien | ✅ | ✅ | — | — | — |
| Registrar/editar mantenimiento | ✅ | ✅ | ✅ | — | — |
| Cambiar estado de mantenimiento | ✅ | ✅ | ✅ | — | — |
| Importar CSV | ✅ | ✅ | — | — | — |
| Ver bandeja global de tickets | ✅ | ✅ | ✅ | — | — |
| Registrar ticket vinculado a bien | ✅ | ✅ | ✅ | — | ✅ (solo ámbito personal) |
| Consultar/agregar seguimiento de ticket | ✅ | ✅ | ✅ | — | ✅ (solo tickets propios) |
| Cambiar estado del ticket | ✅ | ✅ | ✅ | — | — |
| Dar de baja ticket | ✅ | ✅ | — | — | — |
| Gestionar usuarios | ✅ | — | — | — | — |

**Nota:** `personal` no accede a `/tickets.php`; sus consultas y acciones personales validan el propietario del ticket. El endpoint de propiedades del wizard no cachea su respuesta en el navegador y exige el mismo rol que su área.

### 4.3 Validación y codificación

- Validar en servidor: tipos, longitudes, catálogos, existencia de bien, fechas.
- Escapar toda salida HTML.
- Parametrizar toda consulta SQL.
- Usar `filter_var` para IDs y fechas.
- Rechazar métodos distintos de GET/POST.

### 4.4 CSRF

- Token por sesión en formularios y acciones de estado.
- Convertir cambios de estado a `POST`.
- Verificar `Origin`/`Referer` como defensa adicional.

### 4.5 Despliegue

- Datos en `storage/` (fuera del webroot lógico) y denegados por `router.php` y `.htaccess`.
- En Apache, confirmar `AllowOverride` o mover las reglas a la configuración del virtual host; en Nginx, replicar las denegaciones.
- HTTPS obligatorio; HSTS (pendiente de configuración del servidor).
- Headers implementados en `include/security.php`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `Permissions-Policy` y CSP básica.
- `display_errors = Off` salvo `SYSTEC_DEBUG=1`; logs a `storage/logs/php-error.log`.
- Backups cifrados y fuera del servidor.

### 4.6 Auditoría

- Tabla `audit_events` con `id`, `actor`, `action`, `entity`, `entity_id`, `before`, `after`, `occurred_at`.
- Escribir en la misma transacción que el cambio.
- No permitir `UPDATE`/`DELETE` sobre la tabla desde el usuario de la app.
- Registrar importaciones con conteos y errores.

---

## 5. Secretos y configuración

| Ítem | Situación |
|---|---|
| Credenciales en código | No hay contraseñas en código; los hashes viven en `storage/db.db` (`users`) |
| Variables de entorno | `SYSTEC_STORAGE`, `SYSTEC_DB`, `SYSTEC_DEBUG` (opcionales) |
| `.env` | No existe; `.gitignore` lo excluye por si se agrega |
| Claves/tokens | Token CSRF por sesión (`$_SESSION['csrf']`) |
| Zona horaria | `America/Caracas` en `conn.php` |
| Errores en pantalla | Ocultos por defecto; visibles solo con `SYSTEC_DEBUG=1` |
| Usuarios seed | Temporales (`admin`, `operador`, `tecnico`, `consulta`); deben cambiarse antes de operar |

**Regla:** las credenciales y claves deben ir en variables de entorno o en configuración fuera del webroot, nunca en el repositorio. Los usuarios iniciales son solo para arranque local.

---

## 6. Privacidad y retención

| Dato | Tratamiento actual | Recomendación |
|---|---|---|
| Nombres de técnicos | Texto libre visible | Minimizar, controlar acceso, no exponer en reportes públicos |
| Notas de mantenimiento | Texto libre visible | Revisar antes de publicar; pueden contener datos personales |
| Inventario | Visible sin auth | Restringir a personal autorizado |
| Backups | Sin cifrar | Cifrar si salen del servidor; retención definida |

---

## 7. Checklist de endurecimiento

- [x] Autenticación obligatoria en todas las rutas.
- [x] Roles y permisos aplicados en servidor.
- [x] CSRF en POST y acciones de estado.
- [x] Consultas 100 % parametrizadas.
- [x] Escape de salida en todas las vistas.
- [x] `db.db`, `bck/` y CSV fuera del webroot o denegados.
- [x] Headers de seguridad (`include/security.php`).
- [x] `display_errors = Off` en producción (por defecto).
- [ ] HTTPS + HSTS en el servidor real.
- [ ] Backups cifrados y restore periódico probado.
- [ ] Auditoría de cambios.
- [ ] Rate limiting en login e importación.
- [x] Pruebas manuales de seguridad (SQLi, XSS, CSRF, acceso directo).
- [ ] Pruebas automatizadas de seguridad (F3).

---

## 8. Respuesta a incidentes

1. **Contener:** retirar la aplicación de la red o ponerla en mantenimiento.
2. **Preservar evidencia:** copiar `db.db`, backups y logs (si existen) antes de modificar.
3. **Evaluar:** identificar rutas afectadas, datos expuestos y cambios no autorizados.
4. **Restaurar:** desde un backup verificado; validar conteos e integridad.
5. **Corregir:** aplicar los hallazgos P0/P1 antes de reexponer.
6. **Documentar:** registrar el incidente en `CHANGELOG.md` y crear un ADR si cambia la arquitectura.
7. **Comunicar:** informar a la Oficina de Informática y a las partes afectadas según la política institucional.

---

Ver también: `README.md`, `ARCHITECTURE.md`, `DATABASE.md`, `API.md`, `TODO.md`, `docs/anexos/matriz-trazabilidad.md`.
