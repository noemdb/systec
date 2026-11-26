# API.md — SysTec GMB

> **Estado:** superficie HTTP real del repositorio · **Actualizado:** 2026-11-26.
> **No existe una API pública ni un directorio `/api`.** SysTec expone páginas PHP que devuelven HTML, endpoints JSON internos para la interfaz y documentos Markdown de los Informes de Rendición. Este documento describe ese contrato tal como está implementado, no una API REST ideal.

---

## 1. Convenciones generales

| Ítem | Situación actual |
|---|---|
| Entrada | Scripts PHP por ruta; `router.php` solo para el servidor embebido |
| Autenticación | Sesión PHP `systec_session`; `password_hash`/`password_verify` |
| Autorización | `require_login()`, `require_role()` y comprobaciones de propietario |
| Roles | `admin`, `operador`, `tecnico`, `consulta`, `personal` |
| Métodos | `GET` para lecturas; `POST` para mutaciones |
| CSRF | Token por sesión en los POST; los JSON de lectura no lo requieren |
| Formatos | HTML completo, redirección, texto plano de error, JSON interno y `text/markdown` |
| Base de datos | SQLite en `storage/db.db` por defecto; PostgreSQL legacy solo se consulta en modo lectura |
| Zona horaria | `America/Caracas` |
| Errores HTML | Algunas páginas muestran errores en el propio HTML con `200`; no hay un esquema único de errores |
| Errores JSON | Objeto `{ "error": "..." }` y código HTTP explícito |
| Cache de JSON | `Content-Type: application/json; charset=utf-8` y `Cache-Control: private, no-store` |

### 1.1 Patrones de respuesta

- **Página:** HTML completo renderizado mediante `include/layout.php`.
- **Mutación correcta:** mensaje en `$_SESSION` y redirección `302`; el alta personal usa `303`.
- **Validación en acción HTML:** texto plano con `400`, `404`, `405`, `419`, `422` o `500`, según el caso.
- **JSON interno:** respuestas `200` con datos o errores con estado HTTP y cuerpo JSON.
- **Markdown:** el Informe de Rendición guardado se puede descargar como adjunto `.md`.
- **Notificaciones:** toasts de sesión; no son un endpoint aparte.

### 1.2 Códigos HTTP relevantes

| Código | Uso actual |
|---|---|
| `200` | Página, lectura JSON, generación de informe o descarga Markdown |
| `302` | Redirección habitual después de login, logout o mutación |
| `303` | Alta personal: vuelve a `/personal/index.php`, también cuando conserva un error del formulario |
| `400` | Parámetro incompleto o inválido en una acción HTML |
| `401` | Sesión ausente en endpoints JSON; las páginas HTML suelen redirigir a `login.php` |
| `403` | Rol sin permiso, ticket fuera de alcance o acceso directo denegado |
| `404` | Recurso inexistente, ticket eliminado o informe no encontrado en JSON |
| `405` | Método distinto del admitido; incluye `Allow` en los endpoints JSON |
| `409` | Conflicto de estado: seguimiento personal sobre ticket finalizado o informe aprobado/inmutable |
| `419` | Token CSRF ausente, inválido o expirado |
| `422` | Validación de negocio, campos, fechas, estados o transiciones |
| `429` | Login bloqueado temporalmente por intentos fallidos |
| `500` | Error interno controlado; detalle generalmente en `storage/logs/php-error.log` |
| `502` | Fallo del proveedor NVIDIA en acciones de mejora/procesamiento que no usan fallback |
| `503` | No se pueden cargar inventario/direcciones para el selector JSON |

---

## 2. Mapa de rutas

| Método | Ruta | Archivo | Acceso | Respuesta / efecto |
|---|---|---|---|---|
| GET / POST | `/login.php` | `login.php` | Público + CSRF en POST | HTML / autentica y redirige |
| POST | `/logout.php` | `logout.php` | Sesión + CSRF | Cierra sesión |
| GET | `/index.php` o `/` | `index.php` | Sesión | Portada |
| GET | `/documentation.php` | `documentation.php` | Sesión, incluido `personal` | Manual HTML; admite `print=1` |
| GET | `/dashboard.php` | `dashboard.php` | Sesión no personal | Inventario y filtros |
| POST | `/dashboard/actions/status.php` | `dashboard/actions/status.php` | `admin`, `operador` + CSRF | Cambia estado explícito del bien |
| GET | `/maintenances.php` | `maintenances.php` | Sesión no personal | Listado, alta, edición e informes |
| POST | `/maintenances/register.php` | `maintenances/register.php` | `admin`, `operador`, `tecnico` + CSRF | Crea mantenimiento |
| POST | `/maintenances/update.php` | `maintenances/update.php` | `admin`, `operador`, `tecnico` + CSRF | Edita mantenimiento |
| POST | `/maintenances/actions/status.php` | `maintenances/actions/status.php` | `admin`, `operador`, `tecnico` + CSRF | Transición individual |
| POST | `/maintenances/actions/bulk-status.php` | `maintenances/actions/bulk-status.php` | `admin`, `operador`, `tecnico` + CSRF | Transición de lote, hasta 500 IDs |
| GET | `/maintenances/actions/property-tickets.php` | `maintenances/actions/property-tickets.php` | `admin`, `operador`, `tecnico` | JSON de tickets activos del bien |
| GET | `/maintenances/print.php` | `maintenances/print.php` | Sesión | HTML imprimible de historiales filtrados |
| POST | `/maintenances/actions/improve-narrative.php` | `maintenances/actions/improve-narrative.php` | `admin`, `operador`, `tecnico` + CSRF | JSON con textos mejorados por NVIDIA |
| POST | `/maintenances/actions/generate-rendition-report.php` | `maintenances/actions/generate-rendition-report.php` | `admin`, `operador`, `tecnico` + CSRF | JSON con Markdown generado; no persiste |
| POST | `/maintenances/actions/save-rendition-report.php` | `maintenances/actions/save-rendition-report.php` | `admin`, `operador`, `tecnico` + CSRF | Guarda borrador o aprueba |
| GET | `/maintenances/actions/rendition-reports.php` | `maintenances/actions/rendition-reports.php` | Roles no personales, incluido `consulta` | JSON con informe o últimos 50 |
| POST | `/maintenances/actions/delete-rendition-report.php` | `maintenances/actions/delete-rendition-report.php` | `admin` + CSRF | Elimina informe permanentemente |
| GET | `/maintenances/rendition-print.php` | `maintenances/rendition-print.php` | Sesión no personal | HTML imprimible; `download=md` descarga Markdown |
| GET | `/personal/index.php` | `personal/index.php` | `personal` | Bandeja propia paginada a 25 |
| GET | `/personal/register.php` | `personal/register.php` | `personal` | Formulario independiente de alta |
| POST | `/personal/actions/register.php` | `personal/actions/register.php` | `personal` + CSRF | Crea ticket propio y vuelve a la bandeja |
| GET | `/personal/detail.php?id={id}` | `personal/detail.php` | `personal` + propietario | Detalle propio |
| GET | `/personal/actions/details.php?ticket_id={id}` | `personal/actions/details.php` | `personal` + propietario | JSON de detalle e historial |
| GET | `/personal/actions/properties.php` | `personal/actions/properties.php` | `personal` | JSON del selector; `mode=adscriptions` |
| POST | `/personal/actions/history.php` | `personal/actions/history.php` | `personal` + propietario + CSRF | Seguimiento propio; rechaza finalizados |
| POST | `/personal/actions/password.php` | `personal/actions/password.php` | Sesión + CSRF | Cambia contraseña propia |
| POST | `/personal/actions/process-narrative.php` | `personal/actions/process-narrative.php` | `personal` + CSRF | JSON; usa fallback local si NVIDIA falla |
| POST | `/personal/actions/improve-narrative.php` | `personal/actions/improve-narrative.php` | `personal` + CSRF | JSON o `502` |
| POST | `/personal/actions/improve-history-note.php` | `personal/actions/improve-history-note.php` | `personal` + propietario + CSRF | JSON; `409` si está finalizado |
| GET | `/tickets.php` | `tickets.php` | `admin`, `operador`, `tecnico`, `consulta` | Bandeja global; `personal` se redirige |
| GET | `/tickets/register.php` | `tickets/register.php` | Roles operativos; `personal` termina redirigido por el layout | Formulario independiente de soporte |
| POST | `/tickets/actions/register.php` | `tickets/actions/register.php` | `admin`, `operador`, `tecnico` + CSRF | Crea ticket; `personal` se redirige a su formulario |
| GET | `/tickets/actions/properties.php` | `tickets/actions/properties.php` | `admin`, `operador`, `tecnico` | JSON del selector |
| GET | `/tickets/detail.php?id={id}` | `tickets/detail.php` | `admin`, `operador`, `tecnico` | Detalle completo y acciones |
| POST | `/tickets/actions/history.php` | `tickets/actions/history.php` | `admin`, `operador`, `tecnico` + CSRF | Seguimiento |
| POST | `/tickets/actions/status.php` | `tickets/actions/status.php` | `admin`, `operador`, `tecnico` + CSRF | Cambio de estado |
| POST | `/tickets/actions/delete.php` | `tickets/actions/delete.php` | `admin`, `operador` + CSRF | Borrado lógico |
| POST | `/tickets/actions/improve-narrative.php` | `tickets/actions/improve-narrative.php` | Roles operativos + CSRF | JSON o `502` |
| POST | `/tickets/actions/process-narrative.php` | `tickets/actions/process-narrative.php` | Roles operativos + CSRF | JSON o `502` |
| POST | `/tickets/actions/improve-history-note.php` | `tickets/actions/improve-history-note.php` | Roles operativos + CSRF | JSON con fallback local |
| GET | `/historials.php?property_id={id}` | `historials.php` | Sesión | Historial del bien; admite `embed=1` |
| GET | `/report.php` | `report.php` | Sesión no personal | Reporte HTML de bienes dañados |
| GET | `/batch.php` | `batch.php` | Sesión | Historiales legacy en dos columnas |
| GET | `/users.php` | `users.php` | `admin` | Administración de cuentas y perfiles |
| POST | `/users/actions/save.php` | `users/actions/save.php` | `admin` + CSRF | `save`, `toggle_active`, `unlock` o `delete` |
| GET / POST | `/imports.php` | `imports.php` | `admin`, `operador` + CSRF en POST | Importa mantenimientos desde servidor o subida |
| GET / POST | `/updates.php` | `updates.php` | `admin`, `operador` + CSRF en POST | Importa inventario CSV/XLS o PostgreSQL |
| GET / POST | `/sync-assignments.php` | `sync-assignments.php` | `admin`, `operador` + CSRF en POST | Sincroniza responsables/asignados desde legacy |
| GET | `/vendor/...`, `/assets/...` | Assets | Público | CSS/JS/imágenes estáticas |

Las rutas de datos y código interno no son API. `router.php` y `.htaccess` aplican denegaciones diferentes; el detalle está en `SECURITY.md`.

---

## 3. Autenticación y navegación

### `GET /login.php`

- Devuelve el formulario con `csrf_token`, `username` y `password`.
- Si ya existe sesión, redirige a `personal/index.php` para `personal` y a `dashboard.php` para los demás roles.

### `POST /login.php`

- Verifica CSRF y credenciales con `password_verify`; solo acepta cuentas `active = 1`.
- Regenera el ID de sesión y guarda `id`, `username`, `role` y un nuevo token CSRF.
- Sin destino pretendido, `personal` va a `/personal/index.php` y los demás a `/dashboard.php`.
- Si `require_login()` guardó una URL local en `$_SESSION['intended']`, se respeta esa URL después del login; las rutas posteriores vuelven a validar rol y propietario.
- Credenciales incorrectas: HTML `200` con mensaje. Bloqueo: `429`.

### `POST /logout.php`

- Requiere `csrf_token`; destruye la sesión y la cookie y redirige a `login.php`.

---

## 4. Documentación funcional

### `GET /documentation.php`

- **Acceso:** cualquier sesión, incluido `personal`.
- **Parámetro:** `print=1` abre la versión imprimible.
- **Respuesta:** HTML con índice, búsqueda del lado cliente, detalles en offcanvas y guía según el rol.
- **Diagramas:** se generan con Mermaid; la página carga `mermaid@10.9.3` desde jsDelivr. Si el CDN no está disponible, el contenido textual sigue disponible.
- No lee ni publica los archivos de `docs/` como endpoint y no ofrece JSON.

---

## 5. Bienes y dashboard

### `GET /dashboard.php`

Filtros reconocidos por la interfaz:

| Parámetro | Valores / significado |
|---|---|
| `search` | Texto para la tabla |
| `category` | `informatica`, `redes`, `otros` |
| `status` | `BUENO`, `REGULAR`, `MALO`, `MUY MALO`, `__attention__` |
| `adscription` | Clave jerárquica, `local:<sha256>` o `__unassigned__` |
| `responsible`, `assigned` | Claves SHA-256 de la asignación; disponibles para roles operativos |

La página carga el inventario y resuelve adscripciones/asignaciones desde la caché local. Las acciones por fila incluyen mantenimiento, historial y, para `admin`/`operador`, cambio de estado.

### `POST /dashboard/actions/status.php`

| Campo | Obligatorio | Regla |
|---|---|---|
| `csrf_token` | Sí | Token de sesión |
| `property_id` | Sí | Entero de bien existente |
| `status` | Sí | `bueno`, `regular`, `malo` o `muy malo`; se normaliza a mayúsculas |
| `return_category` | No | Categoría de retorno válida |
| `return_status` | No | Estado de retorno válido o `__attention__` |
| `return_adscription` | No | Clave de adscripción de retorno |
| `return_responsible`, `return_assigned` | No | Claves SHA-256 de retorno |
| `return_search` | No | Búsqueda de retorno |

- **No hay ciclo automático ni campo `ident`:** el destino debe enviarse explícitamente en `status`.
- Si el bien ya tiene ese estado, no escribe y muestra un aviso informativo: la operación es idempotente respecto al estado.
- Si cambia, actualiza `properties.status`, registra un mensaje en el log y redirige al dashboard conservando los `return_*` permitidos.
- Errores: `400` bien/estado inválido, `404` bien inexistente, `419` CSRF, `403` rol, `500` fallo de actualización.

---

## 6. Mantenimientos

### `GET /maintenances.php`

- Carga todos los mantenimientos y los pagina en el navegador en páginas de 10.
- Modos: `modeCreate=true`, `modeEdit=true`, `id`, `property_id`; `search` inicializa la búsqueda.
- Filtros de la URL del listado: `category`, `adscription`, `responsible`, `assigned`, `search`, `estado`, `tipo`, `tecnico`, `desde`, `hasta`, `proximo=30` y `vencido=1`.
- `responsible` y `assigned` solo se ofrecen a `admin`, `operador` y `tecnico`.
- Tipos: `preventivo`, `correctivo`, `predictivo`, `evaluativo`.
- Estados: `iniciado`, `revision`, `finalizado`.
- La exportación CSV usa los registros filtrados, no solo la página visible.

### `POST /maintenances/register.php`

| Campo | Obligatorio | Validación |
|---|---|---|
| `csrf_token` | Sí | Token de sesión |
| `register` | Sí | Marca de envío |
| `property_id` | Sí | Entero y bien existente |
| `ticket_id` | No | Entero positivo; el ticket debe existir, no estar eliminado y pertenecer al mismo bien |
| `type` | Sí | Catálogo de cuatro tipos |
| `technician` | Sí | Catálogo vigente de técnicos |
| `date` | Sí | Fecha/hora interpretable por `strtotime()` |
| `failure_reason` | Sí | No vacío |
| `description` | Sí | No vacío |
| `notes` | No | Texto |
| `next_maintenance_date` | No | Fecha válida si se envía |
| `return_to=dashboard` | No | Vuelve al dashboard en lugar de mantenimientos |
| `return_*` | No | Filtros de dashboard cuando `return_to=dashboard` |

- Inserta con `status = 'iniciado'` y calcula `code` a partir del bien.
- No crea un evento de historial de mantenimiento.
- Respuesta: `302` a mantenimientos o al dashboard. Los errores desde el dashboard se conservan en sesión y regresan con los valores del formulario.

### `POST /maintenances/update.php`

- Admite los mismos campos, más `id`, `update` y `status`.
- `status` debe estar en catálogo y respetar `maintenance_transition_allowed()`.
- `ticket_id` vacío desvincula; si el campo no se envía, conserva el vínculo existente.
- Recalcula `code`, actualiza el registro y redirige a `maintenances.php?id={id}`.
- Errores: `404` mantenimiento inexistente, `422` validación/transición, `500` fallo de escritura.

### `POST /maintenances/actions/status.php`

- Campos: `csrf_token`, `id`, `status` y `return_query` opcional.
- Transiciones: `iniciado→revision`, `iniciado→finalizado`, `revision→finalizado`, `finalizado→revision`; el mismo estado está permitido por el helper.
- Al finalizar calcula `time_taken` en horas desde `date`. Repetir la finalización puede recalcular ese valor, por lo que no es estrictamente idempotente.
- `return_query` solo conserva: `category`, `adscription`, `responsible`, `assigned`, `search`, `estado`, `tipo`, `tecnico`, `desde`, `hasta`, `proximo`, `vencido`.

### `POST /maintenances/actions/bulk-status.php`

- Campos: `csrf_token`, `ids[]`, `status` (`revision` o `finalizado`) y `return_query`.
- Toma como máximo los primeros 500 IDs recibidos, descarta inválidos y elimina duplicados.
- Recorre cada registro y aplica la misma guardia de transición que la acción individual; calcula horas al finalizar.
- **No es atómico:** cada actualización se confirma por separado. Los registros omitidos o fallidos se cuentan como `skipped`; no se revierten los ya actualizados.
- Redirige a mantenimientos con un aviso; la página también acepta `bulk_updated` y `bulk_skipped` para mostrarlo.

### `GET /maintenances/actions/property-tickets.php`

- Parámetro: `property_id` entero positivo.
- Devuelve `{ "tickets": [...] }` con tickets activos del bien, estado, datos del bien y `property_category`.
- Errores JSON: `401`, `403`, `405`, `422`, `500`.

### `GET /maintenances/print.php`

Prepara una o varias páginas HTML para imprimir, agrupando los historiales por bien. Parámetros:

`category`, `search`, `status`, `type`, `technician`, `adscription`, `responsible`, `assigned`, `desde`, `hasta`, `proximo=30`, `vencido=1`.

A diferencia de la URL del listado, esta ruta usa `status`, `type` y `technician`. No genera PDF en el servidor.

### `POST /maintenances/actions/improve-narrative.php`

- Campos: `csrf_token`, `id` opcional y los textos `description`, `failure_reason`, `notes`.
- Debe haber al menos un texto no vacío; si se envía `id`, el mantenimiento debe existir.
- Devuelve los tres textos propuestos; no guarda nada.
- `502` indica fallo de NVIDIA o de salida del proveedor. No usa fallback local en esta acción.

---

## 7. Informes de Rendición

Los informes se guardan en `rendition_reports` con ciclo `borrador → aprobado`. Un informe aprobado es inmutable. La unicidad se calcula por período y filtros normalizados (`scope_hash`).

### `POST /maintenances/actions/generate-rendition-report.php`

| Campo | Obligatorio | Uso |
|---|---|---|
| `csrf_token` | Sí | Token de sesión |
| `date_from`, `date_to` | Sí | `YYYY-MM-DD`, con `date_from <= date_to` |
| `use_active_filters` | No | Por defecto `1` |
| `category`, `adscription`, `adscription_label` | No | Filtros de alcance |
| `status`, `type`, `technician` | No | Filtros de mantenimiento |
| `title`, `issuing_unit`, `recipient_name`, `prepared_by` | No | Encabezado; hay valores por defecto |
| `include_tickets` | No | Por defecto `1` |
| `custom_notes` | No | Notas del informe |
| `force_regenerate` | No | `1` regenera aunque exista un borrador |

- Recopila mantenimientos del período y tickets asociados; si no hay mantenimientos devuelve `422`.
- Si existe un informe aprobado para el alcance, devuelve `200` con `already_approved=true` y su contenido.
- Si existe un borrador y no se fuerza regeneración, devuelve `200` con `loaded_existing_draft=true`.
- La generación **no persiste** el informe. Devuelve `content_markdown`, `report_code`, `scope_hash`, estadísticas, IDs y `fallback`.
- `fallback=true` indica que se usó la síntesis local por falta de clave/conexión o respuesta inválida de NVIDIA.

### `POST /maintenances/actions/save-rendition-report.php`

- Admite los campos anteriores y además `report_id`, `content_markdown`, `ai_generated`, `status` y `confirm_approval`.
- `status=aprobado` exige `confirm_approval=1`; cualquier otro valor guarda como `borrador`.
- Vuelve a calcular período, alcance, estadísticas e IDs desde la base antes de guardar.
- Máximo de Markdown: 150000 caracteres.
- Respuesta `200`: `{ "message": "...", "report": {...}, "total_reports": N }`.
- `409`: ya existe un informe aprobado para el alcance, el informe ya fue aprobado o el borrador indicado no existe.
- `422`: rango, contenido, datos o confirmación inválidos.

> **Inconsistencia actual:** en `save-rendition-report.php`, `status` se usa tanto para el ciclo de vida del informe (`borrador`/`aprobado`) como para el filtro de mantenimiento. Al guardar, ese valor se normaliza como filtro y queda vacío porque no es un estado de mantenimiento. El alcance guardado puede diferir del generado si había filtro por estado; debe corregirse antes de tratar este endpoint como contrato estable.

### `GET /maintenances/actions/rendition-reports.php`

- Sin parámetros devuelve los 50 informes más recientes: `{ "reports": [...], "total": N }`. `total` es la cantidad devuelta, no necesariamente el total global.
- Con `id` devuelve `{ "report": {...} }`.
- Acceso para `admin`, `operador`, `tecnico` y `consulta`.
- Errores: `401`, `403`, `405`, `422`, `404`, `500`.

### `POST /maintenances/actions/delete-rendition-report.php`

- Campos: `csrf_token` y `report_id`.
- Solo `admin`.
- Elimina físicamente el informe; no hay borrado lógico ni auditoría general asociada.
- Respuesta `200` con `deleted`, `deleted_id`, `report_code` y `total_reports`; `404` si no existe.

### `GET /maintenances/rendition-print.php`

- Parámetro `id`; sin informe válido muestra una página HTML “Informe de Rendición no encontrado” con `200`.
- Con `download=md` devuelve `text/markdown; charset=utf-8` como adjunto.
- Sin `download`, renderiza el Markdown a HTML seguro para vista e impresión. No genera PDF.
- Acceso para cualquier sesión no personal.

---

## 8. Tickets de soporte

### `GET /tickets.php`

- Acceso: `admin`, `operador`, `tecnico` y `consulta`.
- `personal` se redirige a su bandeja; si recibe `id`, a su detalle personal.
- Carga **todos** los tickets activos con JOIN a bien, creador, estado, historial y mantenimientos; no pagina en SQL.
- El paginador cliente muestra 10 filas por página.
- Filtros: `search`, `status` (`abiertos` o código de estado), `assigned` y `adscription`. Los filtros y la búsqueda se reflejan en la URL.
- La exportación CSV contiene todos los tickets filtrados, no solo la página visible.
- `admin` y `operador` pueden solicitar borrado lógico; los tres roles operativos pueden crear, seguir y cambiar estado.

> **Inconsistencia de permisos:** `consulta` sí puede abrir `tickets.php`, buscar/filtrar y ver el detalle resumido del offcanvas, cuyos datos incluyen historial y mantenimientos. Sin embargo, `/tickets/detail.php` y todas las acciones de ticket rechazan a `consulta` con `403`. El listado no equivale al detalle completo autorizado.

### `GET /tickets/register.php` y `POST /tickets/actions/register.php`

- El formulario independiente de soporte es efectivo para roles operativos. Aunque `require_role()` incluye `personal`, `include/layout.php` lo redirige a su bandeja antes de mostrar el formulario; el alta personal usa `/personal/register.php`.
- Campos del POST: `csrf_token`, `property_id`, `title`, `description`, `observation`.
- Roles operativos: crea ticket `iniciado`, registra el evento `created` y redirige al detalle. Las dos inserciones no tienen una transacción explícita en esta ruta.
- `personal`: se redirige a `/personal/register.php`; para crear debe usar `/personal/actions/register.php`.
- No se acepta `created_by` ni `assigned_to` desde el formulario.

### `GET /tickets/actions/properties.php` y `/personal/actions/properties.php`

Comparten `include/property_picker_api.php`. Respuesta normal:

```json
{
  "properties": [],
  "directions": {},
  "assigned_users": {},
  "adscriptions": {},
  "stale": false,
  "cached_at": "2026-11-26T..."
}
```

- `properties` incluye `id`, `code`, `description`, `status`, `category`, `direction_key`, `direction_label`, `assigned_key` y `assigned_label`.
- `assigned_users` es el catálogo de usuarios asignados al bien procedente de SQLite/caché legacy.
- `mode=adscriptions` devuelve solo `adscriptions` y metadatos; las otras colecciones quedan vacías.
- `503` si no se puede cargar inventario/direcciones.

### `GET /tickets/detail.php?id={id}`

- Solo roles operativos; tickets activos.
- Muestra datos del bien, ticket, historial, seguimiento y cambio de estado.
- `404` para ID inválido, ticket inexistente o eliminado; `403` para `consulta`.

### `POST /tickets/actions/history.php`

- Campos: `csrf_token`, `ticket_id`, `description`; opcionales `return_to=tickets` y `return_query`.
- Inserta `comment` y actualiza `updated_at` en una transacción.
- La ruta de soporte exige ticket activo, pero no comprueba si su estado es `finalizado`.
- Redirige al detalle o a `tickets_return_url()`. Este helper solo conserva `status` y `search`; no conserva `assigned` ni `adscription`.

### `POST /tickets/actions/status.php`

- Campos: `csrf_token`, `ticket_id`, `status_id` y `return_query` opcional.
- Actualiza estado, `updated_at`, `closed_at` e inserta `status_change` en una transacción.
- Si el estado ya es el solicitado, no escribe ni genera evento: es idempotente respecto al estado.
- `finalizado` fija `closed_at`; cualquier otro estado lo deja en `NULL`.
- Redirige con `tickets_return_url()`.

### `POST /tickets/actions/delete.php`

- Campos: `csrf_token`, `ticket_id` y `return_query`.
- Solo `admin`/`operador`.
- Marca `deleted_at` y añade un evento `deleted` en una transacción; conserva el historial previo.
- Un segundo intento sobre el ticket eliminado devuelve `404`.

---

## 9. Portal personal

### `GET /personal/index.php`

- Filtros: `q`, `status`, `category`, `date_from`, `date_to`, `adscription`.
- Orden: `sort=id|property|status|updated_at` y `dir=asc|desc`.
- Paginación **en servidor, 25 tickets por página**, mediante `page`; la página se limita al último número válido.
- Solo devuelve tickets activos creados por la sesión.
- La exportación CSV descarga **los tickets de la página actual**, no todos los resultados filtrados.
- Si el filtro de adscripción no se puede verificar, falla cerrado y no muestra resultados parciales.

### `POST /personal/actions/register.php`

- Campos: `csrf_token`, `property_id`, `title`, `description`, `observation`.
- Límites: título 255; descripción y observación 5000 caracteres.
- Crea ticket y evento inicial en una transacción.
- Tanto el éxito como un error de formulario redirigen con `303` a `/personal/index.php`; en error conserva los valores y abre el wizard.

### Detalle y seguimiento personal

- `GET /personal/detail.php?id={id}` y `GET /personal/actions/details.php?ticket_id={id}` validan propietario y ticket activo.
- `POST /personal/actions/history.php` registra seguimiento y vuelve a la bandeja.
- Un ticket finalizado devuelve `409` al intentar añadir seguimiento.
- Las acciones NVIDIA personales exigen rol, CSRF y, para notas, propiedad del ticket.

---

## 10. Usuarios y perfiles

### `GET /users.php`

- Solo `admin`.
- Consulta `users` con `LEFT JOIN profile` para mostrar cuentas aunque no tengan perfil.
- Campos de perfil: `first_name`, `last_name`, `avatar_url`.
- Calcula conteos de tickets creados y registros de historial por usuario, además de total/activos/técnicos/inactivos y bloqueos temporales.
- La tabla se pagina en cliente a 10 y permite exportar el directorio filtrado a CSV.

### `POST /users/actions/save.php`

Todas las variantes requieren `admin`, POST y CSRF. La respuesta habitual es `302` a `users.php` con feedback en sesión, incluso cuando la validación de dominio falla.

| `action` | Campos | Efecto |
|---|---|---|
| `save` o ausente | `user_id` opcional, `username`, `role`, `active`, `password`, `first_name`, `last_name`, `avatar_url` | Crea o actualiza cuenta y perfil |
| `toggle_active` | `user_id` | Alterna `active`; no permite desactivar la cuenta propia |
| `unlock` | `user_id` | Borra el throttle de intentos fallidos del usuario |
| `delete` | `user_id`, `confirm_username`, `confirm_risk=1` | Eliminación permanente con doble confirmación |

Validaciones y transacciones:

- Usuario nuevo: 3–64 caracteres `[a-zA-Z0-9._-]`; duplicados comprobados sin distinguir mayúsculas/minúsculas. En actualización el nombre de usuario no se cambia.
- Rol dentro de `admin`, `operador`, `tecnico`, `consulta`, `personal`.
- Contraseña inicial: el backend web acepta 4 caracteres, aunque la UI exige 6. Actualización: 6 si se envía una contraseña no vacía.
- Nombre y apellidos deben enviarse juntos, con máximo 120 cada uno. Si ambos están vacíos no se crea/actualiza el perfil; no se borra uno existente.
- Avatar: URL `http`/`https` válida, máximo 2048; exige nombre y apellidos. Vacío deja `avatar_url=NULL` al actualizar un perfil.
- Alta y edición de cuenta/perfil usan `BEGIN IMMEDIATE` y `COMMIT`; un fallo revierte ambas escrituras.
- No se puede desactivar, degradar ni eliminar la cuenta propia. Tampoco se puede eliminar el último administrador activo.
- Los técnicos activos alimentan el catálogo de mantenimientos; un técnico inactivo se retira del catálogo.
- `unlock` no modifica la base; `delete` hace un `DELETE` único con cascada de perfil, no una transacción explícita de múltiples pasos.

---

## 11. Importación y sincronización

### `GET /imports.php` / `POST /imports.php`

- Acceso: `admin` u `operador`; POST requiere CSRF y `confirm_import=1`.
- Orígenes: `import_source=server` usa `storage/maintenances.csv`; `import_source=upload` acepta `maintenances_csv` con extensión `.csv`.
- Límites: 5 MB y 5000 filas no vacías.
- Formato por posiciones, delimitado por comas:
  `código, tipo, descripción, técnico, reservado, razón, fecha, notas, estado`.
- El código se vincula por sus últimos cuatro dígitos con `properties.ident`.
- Las filas válidas se insertan como `finalizado`, con `time_taken=NULL`; no se vincula ticket ni se importa fecha siguiente. El campo `estado` de origen no se utiliza.
- El procesamiento va en una transacción. Las filas inválidas se reportan y omiten; un fallo no capturado revierte todo el lote.
- El reporte HTML muestra insertados, omitidos y errores.

### `GET /updates.php` / `POST /updates.php`

- Acceso: `admin` u `operador`; POST requiere CSRF y `confirm_import=1`.
- Orígenes: `import_source=file` con `properties_csv` (`.csv` o `.xls`) o `import_source=legacy_postgres`.
- Límite: 5000 bienes; archivos CSV/XLS limitados a 5 MB.
- CSV: 11 columnas separadas por `;`: `grupo;subgrupo;seccion;ident;description;marca;modelo;serial;material;color;status`.
- XLS: reporte del sistema de bienes con `CODIGO`, `DENOMINACION DEL BIEN`, `MARCA`, `MODELO`, `SERIAL`, `MATERIAL`, `COLOR`, `ESTADO`.
- `MUY BUENO` se normaliza a `BUENO`.
- Si el bien existe, la importación solo actualiza `status`; no reescribe descripción, modelo, serial, material, color ni datos organizativos.
- **PostgreSQL:** consulta `public.bien015`, `public.bien004`, `public.sia000` y tablas de adscripción/asignación en una transacción `REPEATABLE READ READ ONLY`; exige empresa `38`, selecciona `desincorporado = 'N'` y limita a 5000. En los bienes existentes **preserva el estado de SysTec**; el estado legacy solo se aplica a bienes nuevos.
- Configuración: `SYSTEC_LEGACY_PG_USER` y `SYSTEC_LEGACY_PG_PASSWORD` obligatorias; opcionales `SYSTEC_LEGACY_PG_HOST` (`localhost`), `SYSTEC_LEGACY_PG_PORT` (`5432`) y `SYSTEC_LEGACY_PG_DATABASE` (`DATOS`). Se leen del entorno o del `.env` local.
- La importación PostgreSQL actualiza adscripciones/asignaciones locales y la caché después de insertar/actualizar el inventario; un fallo de caché se registra sin deshacer el inventario ya importado.

### `GET /sync-assignments.php` / `POST /sync-assignments.php`

- Acceso: `admin` u `operador`; POST requiere CSRF y `confirm_sync=1`.
- GET muestra cobertura de responsables/asignados y estado/antigüedad de la caché.
- POST consulta PostgreSQL legacy en solo lectura para los identificadores locales y actualiza:
  `responsible_name`, `responsible_status`, `assigned_name`, `assigned_status`.
- La actualización SQLite usa una transacción; después se escribe la caché local de adscripciones/asignaciones.
- No sincroniza cédulas, cuentas SysTec, roles ni contraseñas.
- Éxito: `303` con cantidades devueltas por legacy y registros cambiados. Fallo: HTML con error y datos locales conservados.

---

## 12. Historiales y reportes

### `GET /historials.php`

- Parámetro `property_id`.
- Incluye mantenimientos ordenados por fecha y tickets activos del bien con estado, creador, asignado e historial.
- `embed=1` devuelve solo el fragmento HTML membrete + historial, para embebido en un modal; no incluye el layout.
- Si el bien no existe, muestra “No se encontró el bien solicitado”.
- La impresión la realiza el navegador; no hay PDF de servidor.

### `GET /report.php`

- Reporte HTML de bienes en `MALO`/`MUY MALO` con al menos una nota de mantenimiento que contiene `desincorporación`.
- Incluye búsqueda/filtrado cliente, exportación CSV e impresión selectiva.
- Usa `DISTINCT` para no duplicar bienes por varias notas coincidentes.

### `GET /batch.php`

- Parámetros `property_id` y `property_id2`.
- Ruta legacy de impresión de dos historiales en columnas. El detalle actual de mantenimientos se precarga directamente; no equivale al historial enriquecido con tickets de `historials.php`.

---

## 13. Idempotencia y efectos secundarios

| Acción | Comportamiento |
|---|---|
| Lecturas HTML/JSON | Idempotentes; no mutan datos |
| Login/logout | No idempotentes; cambian la sesión |
| Estado de bien | Idempotente si se envía el mismo `status`; no avanza ciclos automáticamente |
| Alta de mantenimiento/ticket | No idempotente; cada POST crea un registro |
| Edición de mantenimiento | Última escritura gana; sin historial de mantenimiento |
| Estado individual de mantenimiento | Mismo estado permitido, pero finalizar recalcula `time_taken` |
| Estado de ticket | Mismo `status_id` no escribe ni genera evento |
| Lote de mantenimientos | No atómico; la repetición puede recalcular horas o volver a aplicar transiciones permitidas |
| Seguimiento de ticket | No idempotente; cada POST añade un evento |
| Importación de mantenimientos | No idempotente; puede duplicar filas |
| Importación de inventario | Inserta nuevos; en existentes solo cambia estado, salvo PostgreSQL que lo preserva |
| Informe de Rendición | Generar no persiste; guardar actualiza/crea por alcance; aprobado es inmutable |
| `toggle_active` | No idempotente: alterna el estado en cada POST |
| `unlock` / `delete` | Efecto deseado único; repetidos pueden terminar en feedback de no encontrado/bloqueo ya limpio |

---

Ver también: `README.md`, `ARCHITECTURE.md`, `DOMAIN.md`, `DATABASE.md`, `SECURITY.md`, `BACKUP.md` y `CONVENTIONS.md`.
