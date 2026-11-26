# SECURITY.md — SysTec GMB

> **Actualizado:** 2026-11-26.
> **Resumen:** el sistema aplica autenticación por sesión, roles, CSRF, escape de salida, consultas parametrizadas, headers de seguridad y denegación de datos en el router embebido. Siguen pendientes HTTPS/HSTS en producción, revocación de sesiones, auditoría general, rate limiting de importaciones y reconciliación del seeder de perfiles.

---

## 1. Activos y límites de confianza

| Activo | Ubicación | Sensibilidad |
|---|---|---|
| Base completa | `storage/db.db` | Alta |
| Bienes y mantenimientos | `properties`, `maintenances` | Media |
| Tickets y seguimientos | `tickets`, `ticket_histories` | Media; pueden contener PII laboral |
| Usuarios, hashes y perfiles | `users`, `profile` | Alta |
| Informes de Rendición | `rendition_reports` | Media/Alta por narrativas y técnicos |
| Backups | `storage/backups/...` o destino externo | Alta; incluyen la base completa |
| CSV legacy | `storage/*.csv` | Alta |
| Logs | `storage/logs/...` | Media; pueden incluir usuarios, IDs y mensajes de error |
| Credenciales | Entorno o `.env` local | Alta |
| Código fuente | Archivos PHP, migraciones y scripts | Media |

**Límites actuales:**

- El navegador no es una frontera de confianza: todas las acciones validadas deben volver a validarse en PHP.
- PostgreSQL legacy es una fuente externa de solo lectura.
- NVIDIA Build es un proveedor externo que recibe datos cuando se invoca una acción de IA o un Informe de Rendición.
- Un avatar externo se carga directamente por el navegador desde un tercero.
- `storage/` está dentro del árbol del proyecto, pero debe quedar fuera del `DocumentRoot` o correctamente denegado por el servidor.

---

## 2. Autenticación, sesión y autorización

### 2.1 Controles implementados

- `include/auth.php` usa `password_hash(PASSWORD_DEFAULT)` y `password_verify`.
- La cookie se llama `systec_session` y usa `HttpOnly`, `SameSite=Lax`, `use_strict_mode` y `use_only_cookies`.
- `Secure` se activa cuando PHP detecta HTTPS. No se envía HSTS desde la aplicación.
- El login regenera el ID de sesión y crea un token CSRF nuevo.
- Roles: `admin`, `operador`, `tecnico`, `consulta`, `personal`.
- `require_login()` y `require_role()` se aplican en servidor. El portal personal valida además que el ticket pertenezca al usuario.
- Las mutaciones requieren POST y CSRF. Los endpoints JSON de lectura usan `Cache-Control: private, no-store`.

### 2.2 Riesgo conocido: la sesión no se revoca

`auth_check()` solo comprueba que `$_SESSION['user']` exista. El rol se guarda en la sesión y no se vuelve a leer de `users` en cada petición.

Por tanto:

- Desactivar una cuenta no destruye sus sesiones abiertas.
- Eliminar una cuenta no destruye sus sesiones abiertas.
- Cambiar el rol no actualiza el rol ya almacenado en sesiones existentes.
- Restablecer la contraseña no invalida otras sesiones.

Una sesión ya autenticada puede conservar privilegios hasta que cierre sesión o se elimine la sesión/cookie. Para contención, deben revocarse todas las sesiones de la cuenta y aplicar la medida en el servidor de sesiones; actualmente no hay una tabla central de sesiones ni un botón de “cerrar todas las sesiones”.

### 2.3 Matriz de permisos vigente

| Acción | Admin | Operador | Técnico | Consulta | Personal |
|---|---|---|---|---|---|
| Ver inventario, mantenimientos, historiales y reportes | Sí | Sí | Sí | Sí | No |
| Cambiar estado de bien | Sí | Sí | No | No | No |
| Registrar/editar/cambiar mantenimiento | Sí | Sí | Sí | No | No |
| Ver bandeja global de tickets | Sí | Sí | Sí | Sí, solo listado/resumen | No |
| Detalle completo y acciones de ticket | Sí | Sí | Sí | No | No |
| Registrar/seguir ticket propio | Sí | Sí | Sí | No | Sí |
| Cambiar estado de ticket | Sí | Sí | Sí | No | No |
| Eliminar ticket | Sí | Sí | No | No | No |
| Importar o sincronizar inventario | Sí | Sí | No | No | No |
| Gestionar usuarios | Sí | No | No | No | No |
| Ver documentación | Sí | Sí | Sí | Sí | Sí |

**Inconsistencia documentada:** `tickets.php` admite `consulta` y su offcanvas incluye datos de historial/mantenimientos, pero `/tickets/detail.php` y las acciones de ticket rechazan a ese rol con `403`.

---

## 3. Cuentas iniciales, perfiles y contraseñas

### 3.1 Seed 009

La migración `009_seed_personal_users.sql`:

- Inserta 23 cuentas personales con hashes bcrypt independientes para una **clave inicial común definida en la propia migración** (no se reproduce aquí).
- Usa `INSERT OR IGNORE`; no sobrescribe cuentas existentes ni sus credenciales.
- Crea `zrivas` con `active = 0` porque la ficha indica inactiva.
- Añade `users.email` y un índice único para emails no vacíos.

**Riesgo crítico:** una clave inicial compartida y conocida en el repositorio permite acceso inmediato a las cuentas activas si se despliega la migración sin cambiarla. Debe rotarse antes de exponer el sistema y desactivarse cualquier cuenta que no corresponda.

### 3.2 Snapshot de cuentas y perfiles al 2026-11-26

La base local de referencia tiene **29 usuarios y 29 perfiles**. Son datos de un snapshot y pueden cambiar con el uso. Hay 24 cuentas personales: 23 activas y `zrivas` inactiva.

### 3.3 Error de nombre que rompe el seeder

- La base actual contiene **`abrizuela`** (una `z`).
- `bin/seed-profiles.php`, la migración 010 y el catálogo canónico esperan **`abrizzuela`** (dos `z`).
- La migración 011 corrige otros nombres legacy, pero no incluye esta variante.
- Por ello `bin/seed-profiles.php` falla con `No existe el usuario personal: abrizzuela` y revierte toda la carga de perfiles.

La discrepancia debe reconciliarse de forma controlada antes de ejecutar el seeder; no se debe asumir que una nueva corrida la corrige.

### 3.4 Política de contraseña inconsistente

| Flujo | Mínimo actual |
|---|---|
| Alta web, backend `auth_admin_save_user()` | **4 caracteres** |
| Alta web, `minlength` del formulario | 6 caracteres |
| CLI `bin/create-user.php` | 6 caracteres |
| Cambio de contraseña propio o administrativo | 6 caracteres |

El valor efectivo depende del camino. Unificar la política en backend y UI es requisito antes de endurecerla; mientras tanto, la UI no protege contra un POST manual de alta con 4 caracteres.

### 3.5 Administración de usuarios

- `users.php` usa `LEFT JOIN profile` y muestra cuentas sin perfil.
- `first_name` y `last_name` deben enviarse juntos; máximo 120 caracteres cada uno.
- `avatar_url` debe ser `http`/`https`, máximo 2048 y requiere nombre/apellidos.
- Alta y edición de cuenta/perfil son transaccionales.
- No se puede desactivar, degradar o eliminar la cuenta propia; no se puede eliminar el último administrador activo.
- La eliminación es permanente y tiene doble confirmación. El perfil se elimina por cascada; los JOIN posteriores muestran “Usuario no disponible” en historiales.
- `unlock` borra el archivo de throttle del usuario. Restablecer la contraseña también lo borra.

---

## 4. Controles web

### 4.1 CSRF y métodos

- `verify_csrf()` exige POST y compara el token con `hash_equals`.
- Token ausente/inválido: `419`; método incorrecto: `405`.
- Los endpoints JSON NVIDIA y de Informes de Rendición repiten la comprobación para devolver JSON en lugar de texto plano.

### 4.2 XSS y SQL

- `e()` escapa salida HTML con `ENT_QUOTES | ENT_SUBSTITUTE`.
- `js()` serializa datos para JavaScript con `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS` y `JSON_HEX_QUOT`.
- Las consultas usan parámetros; nombres de tabla/columna y orden se restringen con listas blancas.
- El Markdown de Informes de Rendición se renderiza con un conversor limitado que escapa texto y solo admite un subconjunto de tablas/etiquetas.

### 4.3 CSP y headers reales

`include/security.php` envía, cuando no se han enviado headers:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: no-referrer`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`
- `Cache-Control: no-store, private`
- CSP con nonce por petición.

Política CSP efectiva:

| Directiva | Valor |
|---|---|
| `default-src` | `'self'` |
| `script-src` | `'self' 'nonce-...'`; solo `documentation.php` añade `https://cdn.jsdelivr.net` |
| `style-src` | Normal: `'self' 'nonce-...'`; documentación: `'self' 'unsafe-inline'` |
| `style-src-attr` | `'unsafe-inline'` en todas las páginas |
| `img-src` | `'self' data: http: https:` |
| `font-src` | `'self'` |
| `connect-src` | `'self'` |
| `object-src` | `'none'` |
| `frame-ancestors` | `'none'` |
| `base-uri` | `'self'` |
| `form-action` | `'self'` |

`unsafe-inline` no se habilita para scripts. En documentación sí se permite estilo inline porque Mermaid inyecta SVG con bloques de estilo. La CSP no incluye HSTS ni `upgrade-insecure-requests`.

---

## 5. Privacidad en flujos NVIDIA Build

El cliente HTTP usa `https://integrate.api.nvidia.com/v1/chat/completions` y el modelo configurado en `include/nvidia_maintenance_narrative.php`. La clave `API_KEY_NVIDIA_BUILD` se envía como Bearer y no se registra en los logs.

**La minimización depende del flujo; no es cierto que todos los flujos envíen solo narrativas.**

| Flujo | Datos enviados al proveedor |
|---|---|
| CLI de mantenimiento | Solo `description`, `failure_reason` y `notes`. Los IDs se usan localmente para seleccionar/guardar, no en el payload |
| Mejora web de mantenimiento | Los mismos tres textos narrativos; `id` solo se valida localmente |
| Mejora de ticket | `title`, `description`, `observation` |
| Relato libre de soporte | `narrative` |
| Relato libre personal | `narrative` y `property_context` de hasta 300 caracteres recibido del cliente |
| Mejora de nota | `note` y contexto de hasta 350 caracteres con título del ticket, descripción del bien y estado actual |
| Informe de Rendición | Hasta 40 mantenimientos con **ID, fecha, código, descripción del bien, tipo, estado, técnico, horas** y textos narrativos truncados; hasta 25 tickets con **ID, fecha, título, estado, descripción y observación**; también período, alcance, unidad, destinatario, preparado por, notas y estadísticas |

Los textos libres pueden contener PII aunque no haya campos estructurados. Antes de usar IA o informes debe evitarse información innecesaria y revisar el payload cuando se trate de datos sensibles.

Comportamiento:

- Las mejoras directas de mantenimiento/ticket devuelven `502` si NVIDIA falla; no guardan nada.
- El procesamiento personal de relatos, la mejora personal de notas y la generación de informes pueden usar fallback local y devolver `200` con indicador de fallback.
- El usuario revisa y guarda manualmente la propuesta. La generación del informe no persiste.
- El CLI de mantenimiento solo escribe con `--execute`, después de generar todas las propuestas y dentro de una transacción.
- La disponibilidad y cuota del proveedor son dependencias externas.

---

## 6. Avatares externos y otros terceros

`users.php` y el editor de perfil cargan `avatar_url` directamente con `<img src="...">`.

- La CSP permite explícitamente imágenes `http:` y `https:`.
- No hay proxy, descarga en servidor ni lista blanca de dominios.
- El navegador contacta al tercero y puede revelarle IP, momento de la consulta y URL del avatar. `referrerpolicy="no-referrer"` evita enviar la URL de SysTec como referente, pero no anula esa comunicación.
- Una URL HTTP también puede permitir seguimiento o contenido no cifrado.
- Solo los administradores ven el listado de usuarios, pero la URL se guarda en la base y se entrega a cada navegador que renderiza el perfil.

Recomendación: usar avatares alojados en SysTec o en un proxy institucional, limitar dominios y exigir HTTPS. No incluir identificadores personales en la ruta del avatar.

`documentation.php` también carga Mermaid desde jsDelivr con SRI. Es una salida de red deliberada; sin CDN, la guía sigue mostrando el contenido textual pero no todos los diagramas.

---

## 7. Secretos y configuración

| Ítem | Situación al 2026-11-26 |
|---|---|
| `.env` local | **Existe** en el entorno de trabajo; está ignorado por `.gitignore` y no debe versionarse |
| Carga de `.env` | `include/config.php` solo admite nombres allowlisted; el entorno tiene prioridad |
| Variables de aplicación | `SYSTEC_STORAGE`, `SYSTEC_DB`, `SYSTEC_DEBUG` |
| PostgreSQL legacy | `SYSTEC_LEGACY_PG_HOST`, `PORT`, `DATABASE`, `USER`, `PASSWORD`, `SYSTEC_LEGACY_CACHE_TTL` |
| NVIDIA | `API_KEY_NVIDIA_BUILD` |
| Depuración | `display_errors=Off` salvo `SYSTEC_DEBUG=1` |
| Log de PHP | `storage/logs/php-error.log` con rotación por tamaño |

No se deben copiar valores de `.env` a documentación, issues, capturas o logs. Las credenciales de producción deben estar en un gestor de secretos o en variables del servicio, con permisos mínimos.

---

## 8. Router, Apache y exposición de archivos

`router.php` y `.htaccess` **no son equivalentes**.

| Recurso | `router.php` | `.htaccess` |
|---|---|---|
| Prefijos `storage`, `bck`, `docs`, `grafo`, `spec`, `bin`, `include`, `template`, `dataFile`, `tests` | Denegados | Denegados |
| `migrations/` | Denegado | No incluido explícitamente |
| `conn.php` | Denegado explícitamente | No denegado explícitamente |
| `router.php` | Denegado explícitamente | No denegado explícitamente |
| Extensiones `db`, `sqlite`, `sqlite3`, `csv`, `xls`, `xlsx`, `log`, `md`, `env`, `sql` | Denegadas | Denegadas |
| Extensiones `json`, `lock` | No denegadas por la regla de extensión | Denegadas |
| `.agents`, `.skills` | No incluidos | Denegados |

Implicaciones:

- En el servidor embebido, `/conn.php` y `/router.php` devuelven `403`.
- En Apache, si se aplica el `.htaccess` actual, esos dos archivos no tienen una denegación explícita. Deben añadirse reglas en el servidor o mover el código fuera del webroot.
- `migrations/` depende del servidor: el router lo bloquea, el `.htaccess` actual no.
- En Nginx hay que replicar todas las denegaciones; no se heredan de `.htaccess`.
- `AllowOverride` debe permitir las reglas Apache o deben moverse al virtual host.
- La práctica recomendada es que `storage/`, `.env`, backups y código interno no sean servidos en absoluto.

---

## 9. Rate limiting y auditoría

### 9.1 Login

El throttle se guarda en `storage/cache/throttles/` con claves hasheadas:

- **5 intentos fallidos por usuario** en una ventana móvil de **15 minutos**.
- **15 intentos fallidos por IP** en la misma ventana.
- Al alcanzar el umbral, el login responde `429` y muestra los minutos restantes.
- Un login correcto limpia el throttle del usuario; la limpieza administrativa también está disponible.

No hay rate limiting para importaciones, sincronizaciones, NVIDIA, Informes de Rendición ni otras acciones POST. Los límites de 5 MB/5000 filas no sustituyen un control de frecuencia.

### 9.2 Auditoría y logs

- **No existe una auditoría general** (`audit_events` ni equivalente) con actor, entidad, before/after y fecha para todos los cambios.
- `ticket_histories` registra creación, cambio de estado, seguimiento y borrado lógico de tickets. No cubre mantenimientos, bienes, usuarios, perfiles, importaciones ni informes.
- Hay mensajes operativos mediante `error_log()` para administración de usuarios, asignaciones, importaciones y cambios de estado de bienes.
- `systec_log()` ofrece formato estructurado y rotación, pero no se usa de forma uniforme en todos los flujos.
- Los logs pueden contener nombres de usuario, IDs y excepciones; deben protegerse igual que datos operativos y tener retención definida.

La ausencia de auditoría impide reconstruir con garantía quién modificó inventario, mantenimientos, perfiles o informes.

---

## 10. Importaciones, backups y retención

- `imports.php` y `updates.php` exigen `admin`/`operador`, POST, CSRF y confirmación.
- Límite de archivo: 5 MB; límite de filas/bienes: 5000.
- Las importaciones se ejecutan en transacción SQLite; los errores por fila se reportan. No hay rate limiting.
- PostgreSQL legacy se consulta en una transacción de solo lectura y se verifica `transaction_read_only`.
- El lote de mantenimientos acepta archivo en servidor o subida; no valida un fingerprint criptográfico del archivo.
- Los backups actuales pueden contener hashes, perfiles, tickets, historiales, informes y CSV. Deben cifrarse y almacenarse fuera del host.
- `bin/backup.sh` permite cifrado opcional; la ejecución recomendada es con `SYSTEC_BACKUP_KEY_FILE` y `--require-encryption`.
- El script no instala cron, no envía alertas, no restaura y no prueba el descifrado. Ver `BACKUP.md`.

---

## 11. Checklist de endurecimiento

### Implementado

- [x] Autenticación obligatoria en páginas de datos.
- [x] Roles y propiedad de tickets validados en servidor.
- [x] CSRF en POST y acciones JSON mutantes.
- [x] Escape de salida y serialización segura para JavaScript.
- [x] Consultas parametrizadas y allowlists para identificadores SQL.
- [x] Headers de seguridad y CSP con nonce.
- [x] Denegación de `storage/` y extensiones sensibles en el router embebido.
- [x] Rate limiting de login por usuario e IP.
- [x] Límites y transacción en importaciones.
- [x] Script de backup con snapshot, verificación, cifrado opcional y rotación.

### Pendiente

- [ ] HTTPS y HSTS en el servidor real.
- [ ] Revocación de sesiones al desactivar, eliminar, cambiar rol o restablecer contraseña.
- [ ] Unificar la política de contraseñas y aplicar una política más fuerte.
- [ ] Rotar la clave inicial común de las cuentas seed definida en la migración 009.
- [ ] Reconciliar `abrizuela`/`abrizzuela` y probar el seeder.
- [ ] Auditoría general e inmutable de cambios.
- [ ] Rate limiting de importaciones, sincronización y IA.
- [ ] Política de avatares externos/HTTPS o proxy local.
- [ ] Alinear `router.php` y `.htaccess`, especialmente `conn.php`, `router.php` y `migrations/`.
- [ ] Programar backup con clave, alertas de fallo y restauración/descifrado probado en aislamiento.
- [ ] Pruebas automatizadas de seguridad end-to-end en CI.

---

## 12. Respuesta a incidentes

1. **Contener:** retirar la aplicación de la red o ponerla en mantenimiento; revocar sesiones y credenciales afectadas.
2. **Preservar evidencia:** copiar base, backups y logs antes de modificar; proteger las copias como datos sensibles.
3. **Evaluar:** identificar rutas, cuentas, datos y cambios afectados; revisar logs y `ticket_histories` sin asumir que son auditoría completa.
4. **Restaurar:** usar un backup verificado en un entorno aislado y aplicar las migraciones necesarias.
5. **Corregir:** cerrar el hallazgo y validar permisos, CSRF, escape y exposición de archivos.
6. **Documentar:** registrar el incidente y crear una decisión arquitectónica si cambia el diseño.
7. **Comunicar:** informar a la Oficina de Informática y a las partes afectadas según la política institucional.

---

Ver también: `README.md`, `ARCHITECTURE.md`, `DATABASE.md`, `API.md`, `BACKUP.md`, `TODO.md` y `docs/anexos/matriz-trazabilidad.md`.
