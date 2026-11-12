# API.md — SysTec GMB

> **Estado:** v1.4 (registro reutilizable de tickets) · **Actualizado:** 2026-11-11.
> **No existe una API pública.** El sistema expone una superficie HTTP de páginas y formularios que devuelven HTML y redirecciones. Este documento describe esa superficie real, no un contrato REST ideal.

---

## 1. Convenciones generales

| Ítem | Situación actual |
|---|---|
| Estilo | Scripts PHP por ruta; `router.php` para el servidor embebido |
| Métodos | `GET` solo lectura; `POST` para toda mutación |
| Formato de respuesta | HTML completo, redirección `Location` o texto plano de error |
| Formato de error | Texto plano con código HTTP explícito |
| Autenticación | Sesión PHP (`systec_session`), `password_hash`/`password_verify` |
| Autorización | `require_login()` y `require_role()`; roles `admin`, `operador`, `tecnico`, `consulta`, `personal` |
| CSRF | Token por sesión en todos los POST (`csrf_field()` / `verify_csrf()`) |
| Idempotencia | Las lecturas sí; las mutaciones no (cada POST crea o cambia) |
| Validación | `maintenances/validations/register.php` y `update.php`; validación adicional en cada acción |
| Codificación | UTF-8; zona horaria `America/Caracas` |
| Base de datos | SQLite `storage/db.db` (fuera del webroot) |

### 1.1 Patrón de respuesta

- **Éxito en página:** HTML completo con layout.
- **Avisos:** las páginas pueden definir `$notifications` como lista de `toast_notification($tipo, $mensaje)`; el layout muestra toast temporales, no una respuesta HTTP aparte.
- **Éxito en acción:** `header('Location: ...')` + `exit()` (PRG).
- **Error de validación:** `422` con texto plano.
- **Error de autenticación:** `302` a `login.php`.
- **Error de autorización:** `403` con HTML breve.
- **CSRF inválido o método incorrecto:** `419` o `405`.
- **Error de base de datos:** `500` con mensaje genérico; detalle en `storage/logs/php-error.log`.

### 1.2 Códigos de estado HTTP

| Código | Uso |
|---|---|
| `200` | Página renderizada o resultado de importación |
| `302` | Redirección tras login, logout o mutación |
| `400` | Parámetros incompletos o inválidos |
| `403` | Rol sin permiso o acceso directo denegado |
| `404` | Recurso inexistente o ticket fuera de alcance |
| `405` | Método distinto de POST en una acción de mutación |
| `419` | Token CSRF ausente o inválido |
| `422` | Validación de negocio (fecha, estado, transición, bien) |
| `503` | Fuente o datos necesarios no disponibles para una lectura JSON |
| `500` | Error interno controlado |

---

## 2. Mapa de rutas

| # | Método | Ruta | Archivo | Auth / rol | Efecto | Respuesta |
|---|---|---|---|---|---|---|
| 1 | GET | `/login.php` | `login.php` | Público | Formulario de login | HTML |
| 2 | POST | `/login.php` | `login.php` | Público + CSRF | Autentica y redirige | `302` |
| 3 | POST | `/logout.php` | `logout.php` | Sesión + CSRF | Cierra sesión | `302` |
| 4 | GET | `/index.php` o `/` | `index.php` | Sesión | Portada | HTML |
| 4a | GET | `/personal/index.php` | `personal/index.php` | `personal` | Bandeja paginada; acepta `q`, `status`, `category`, `date_from`, `date_to`, `adscription` y `page` | HTML |
| 4b | GET | `/personal/register.php` | `personal/register.php` | `personal` | Formulario de alta de ticket del ámbito personal | HTML |
| 4c | POST | `/personal/actions/register.php` | `personal/actions/register.php` | `personal` + CSRF | Crea ticket propio o vuelve a la bandeja con el modal y los valores conservados | `302` / `303` |
| 4d | GET | `/personal/detail.php?id={id}` | `personal/detail.php` | `personal` + propietario | Detalle y historial del ticket personal | HTML |
| 4e | POST | `/personal/actions/history.php` | `personal/actions/history.php` | `personal` + CSRF | Agrega seguimiento a un ticket propio | `302` |
| 4f | GET | `/personal/actions/details.php?ticket_id={id}` | `personal/actions/details.php` | `personal` + propietario | Devuelve detalle e historial del ticket | JSON |
| 4g | GET | `/personal/actions/properties.php` | `personal/actions/properties.php` | `personal` | Devuelve bienes y direcciones para el selector | JSON |
| 5 | GET | `/dashboard.php` | `dashboard.php` | Sesión | Lista de bienes; acepta `search` | HTML |
| 6 | POST | `/dashboard/actions/status.php` | `dashboard/actions/status.php` | `admin`, `operador` + CSRF | Avanza el estado de un bien | `302` |
| 7 | GET | `/maintenances.php` | `maintenances.php` | Sesión | Lista, alta o edición según parámetros | HTML |
| 8 | POST | `/maintenances/register.php` | `maintenances/register.php` | `admin`, `operador`, `tecnico` + CSRF | Crea mantenimiento | `302` |
| 9 | POST | `/maintenances/update.php` | `maintenances/update.php` | `admin`, `operador`, `tecnico` + CSRF | Actualiza mantenimiento | `302` |
| 10 | POST | `/maintenances/actions/status.php` | `maintenances/actions/status.php` | `admin`, `operador`, `tecnico` + CSRF | Cambia estado y calcula horas | `302` |
| 10a | POST | `/tickets/actions/delete.php` | `tickets/actions/delete.php` | `admin`, `operador` + CSRF | Oculta ticket y registra eliminación en su historial | `302` |
| 10b | GET | `/tickets.php` | `tickets.php` | `admin`, `operador`, `tecnico` | Bandeja de soporte y modal de alta | HTML |
| 10c | GET | `/tickets/register.php` | `tickets/register.php` | `personal`, `admin`, `operador`, `tecnico` | Formulario de alta independiente, conservado junto al modal | HTML |
| 10d | POST | `/tickets/actions/register.php` | `tickets/actions/register.php` | `admin`, `operador`, `tecnico` + CSRF | Crea ticket y su evento inicial | `302` |
| 10e | GET | `/tickets/actions/properties.php` | `tickets/actions/properties.php` | `admin`, `operador`, `tecnico` | Devuelve bienes y direcciones para el selector | JSON |
| 10f | GET | `/tickets/detail.php?id={id}` | `tickets/detail.php` | `admin`, `operador`, `tecnico` | Detalle, historial y formularios autorizados | HTML |
| 10g | POST | `/tickets/actions/history.php` | `tickets/actions/history.php` | `admin`, `operador`, `tecnico` + CSRF | Agrega seguimiento a ticket activo | `302` |
| 10h | POST | `/tickets/actions/status.php` | `tickets/actions/status.php` | `admin`, `operador`, `tecnico` + CSRF | Cambia estado y agrega evento | `302` |
| 11 | GET | `/historials.php` | `historials.php` | Sesión | Historial de un bien | HTML |
| 12 | GET | `/report.php` | `report.php` | Sesión | Reporte de bienes dañados | HTML imprimible |
| 13 | GET | `/batch.php` | `batch.php` | Sesión | Dos historiales en una página | HTML imprimible |
| 14 | GET | `/imports.php` | `imports.php` | `admin`, `operador` | Pantalla de confirmación de importación | HTML |
| 15 | POST | `/imports.php` | `imports.php` | `admin`, `operador` + CSRF | Importa `storage/maintenances.csv` | HTML con reporte |
| 16 | GET | `/updates.php` | `updates.php` | `admin`, `operador` | Pantalla de confirmación de inventario | HTML |
| 17 | POST | `/updates.php` | `updates.php` | `admin`, `operador` + CSRF | Importa archivo o consulta PostgreSQL legacy en solo lectura | HTML con resumen detallado |
| 18 | GET | `/vendor/...` | `vendor/` | Público | Assets estáticos | CSS/JS |
| 19 | GET | `/storage/...`, `/docs/...`, `/grafo/...`, `/spec/...`, `/bin/...`, `/include/...` | — | Denegado | `403` por `router.php`/`.htaccess` | Texto plano |

> `router.php` también deniega `.db`, `.csv`, `.log`, `.md` y `.env` en cualquier ruta.

---

## 3. Detalle por módulo

### 3.1 Autenticación

#### `GET /login.php`

- **Parámetros:** ninguno.
- **Respuesta:** formulario con token CSRF.
- **Nota:** si ya hay sesión, redirige a `dashboard.php`.

#### `POST /login.php`

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `csrf_token` | string | Sí | Token de sesión |
| `username` | string | Sí | Usuario |
| `password` | string | Sí | Contraseña |

- **Efecto:** verifica con `password_verify`; regenera el ID de sesión; guarda `user` y `csrf`.
- **Respuesta:** `302` a la URL pretendida o `dashboard.php`; en error, `200` con mensaje.
- **Errores:** `419` si el CSRF falla.

#### `POST /logout.php`

- **Campos:** `csrf_token`.
- **Efecto:** destruye la sesión y la cookie.
- **Respuesta:** `302` a `login.php`.

### 3.2 Portada

#### `GET /index.php`

- **Parámetros:** ninguno.
- **Auth:** sesión obligatoria.
- **Respuesta:** HTML con descripción y enlace al dashboard.

### 3.3 Dashboard de bienes

#### `GET /dashboard.php`

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `search` | string | No | Texto inicial para DataTables; se serializa con `js()` |
| `category` | string | No | Categoría de bien: `informatica`, `redes` u `otros` |
| `status` | string | No | Estado del bien o `__attention__` |
| `adscription` | string | No | Código jerárquico `cod_dependencia/cod_direccion/cod_departamento`, `local:<sha256>` o `__unassigned__`; permite filtrar por unidad operativa |

- **Efecto:** lee `properties`, resuelve adscripciones con `legacy_postgres_inventory_snapshot()` en la transacción PostgreSQL de solo lectura, cuenta mantenimientos por bien y muestra el total de tickets a `admin`, `operador` y `tecnico`.
- **Etiqueta de adscripción:** usa el nombre del departamento de `BIEN006`; si falta, el nombre de dirección (`BIEN005`) y luego el de dependencia (`BIEN001`). Los bienes sin asignación reciben el filtro `__unassigned__`.
- **Disponibilidad:** si PostgreSQL no responde, usa `properties.adscription` local cuando exista; de lo contrario, muestra “Sin adscripción”.
- **Alcance de la consulta:** busca en SIA todos los identificadores locales, incluso bienes desincorporados que permanecen en SysTec; la comprobación/importación legacy sigue limitada a `desincorporado = 'N'`.
- **Permisos PostgreSQL:** el usuario de solo lectura requiere acceso a `BIEN015`, `BIEN001`, `BIEN005` y `BIEN006` para resolver la adscripción; la importación también usa `BIEN004`.
- **Respuesta:** HTML con tabla, badges, conteo y acciones.
- **Acciones por fila:**
  - **Mantenimiento:** enlace a `maintenances.php?modeCreate=true&property_id={id}`.
  - **Historial:** enlace a `historials.php?property_id={id}`.
  - **Estado:** formulario POST a `dashboard/actions/status.php` (solo `admin`/`operador`).

#### `POST /dashboard/actions/status.php`

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `csrf_token` | string | Sí | Token de sesión |
| `property_id` | int | Sí | ID del bien |
| `ident` | string | No | Identificador para el `search` de la redirección |

- **Lógica de transición:** `BUENO→MALO`, `MALO→REGULAR`, `REGULAR→BUENO`, otro→`BUENO`.
- **Efecto:** `UPDATE properties SET status = ? WHERE id = ?`.
- **Respuesta:** `302` a `dashboard.php?search={ident}`.
- **Errores:** `400` bien inválido, `404` bien inexistente, `419` CSRF, `403` rol no permitido.

### 3.4 Mantenimientos

#### `GET /maintenances.php`

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `modeCreate` | `true` | No | Muestra el formulario de alta |
| `modeEdit` | `true` | No | Muestra el formulario de edición |
| `id` | int | No | ID del mantenimiento a editar o resaltar |
| `property_id` | int | No | Bien preseleccionado al crear |
| `search` | string | No | Búsqueda inicial de DataTables |

- **Modos:** lista; lista + alta; lista + edición.
- **Respuesta:** HTML con tabla, filtros, checkbox “Solo vencidos” y modal de detalle.
- **Datos:** todos los mantenimientos ordenados por `id DESC`; el detalle viaja como JSON escapado en `data-detail`.

#### `POST /maintenances/register.php`

| Campo | Tipo | Obligatorio | Validación |
|---|---|---|---|
| `csrf_token` | string | Sí | Token de sesión |
| `register` | any | Sí | Marca de envío |
| `property_id` | int | Sí | Entero y bien existente |
| `type` | string | Sí | Debe existir en `getListMaintenanceType()` |
| `technician` | string | Sí | Debe existir en `getListMaintenanceTechnician()` |
| `date` | datetime-local | Sí | `strtotime()` válido |
| `failure_reason` | string | Sí | No vacío |
| `description` | string | Sí | No vacío |
| `notes` | string | No | — |
| `next_maintenance_date` | date | No | `strtotime()` válido si se envía |

- **Efecto:** calcula `code` con `getCodeId(property_id)`, inserta con `status = 'iniciado'`, redirige a `maintenances.php`.
- **Errores:** `422` si el bien o las fechas no son válidos; `500` controlado.

#### `POST /maintenances/update.php`

| Campo | Tipo | Obligatorio | Validación |
|---|---|---|---|
| `csrf_token` | string | Sí | Token de sesión |
| `update` | any | Sí | Marca de envío |
| `id` | int | Sí | Mantenimiento existente |
| `property_id` | int | Sí | Bien existente |
| `type`, `technician` | string | Sí | Catálogos |
| `date` | datetime-local | Sí | Fecha válida |
| `failure_reason`, `description` | string | Sí | No vacíos |
| `notes` | string | No | — |
| `next_maintenance_date` | date | No | Fecha válida si se envía |
| `status` | string | Sí | Catálogo + transición permitida |

- **Efecto:** recalcula `code`, actualiza todos los campos y redirige a `maintenances.php?id={id}`.
- **Errores:** `404` mantenimiento inexistente, `422` estado/transición/fecha inválidos.

#### `POST /maintenances/actions/status.php`

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `csrf_token` | string | Sí | Token de sesión |
| `id` | int | Sí | ID del mantenimiento |
| `status` | string | Sí | `revision` o `finalizado` según transición |

- **Transiciones permitidas:** `iniciado→revision`, `iniciado→finalizado`, `revision→finalizado`, `finalizado→revision`.
- **Efecto:** actualiza `status`; si el nuevo estado es `finalizado`, calcula `time_taken` como diferencia total en horas.
- **Respuesta:** `302` a `maintenances.php?id={id}`.
- **Errores:** `422` transición no permitida o estado inválido.

### 3.5 Historial

#### `GET /historials.php`

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `property_id` | int | Sí (para datos) | Bien a historiar |

- **Efecto:** lee el bien y sus mantenimientos ordenados por `date ASC`.
- **Respuesta:** HTML con membrete, tabla, observaciones y firma.
- **Casos límite:** si el bien no existe o el ID no es entero, muestra “No se encontró el bien solicitado”.
- **Uso:** imprimible desde el navegador; no genera PDF en servidor.

### 3.6 Tickets

#### `GET /personal/index.php`

- **Auth:** rol `personal`.
- **Filtros:** `q`, `status`, `category` (`informatica`, `redes`, `otros`), `date_from`, `date_to` y `adscription`.
- **Paginación:** 25 resultados por página mediante `page`; las tarjetas de estado aplican el filtro correspondiente.
- **Adscripción:** se resuelve desde el snapshot de SIA cacheado; si la fuente y el cache no están disponibles, el filtro falla cerrado y muestra un aviso.
- **Alta:** abre el wizard compartido en un modal; el selector carga los bienes al abrirse y ofrece categoría, dirección y búsqueda.

#### `GET /personal/register.php`

- **Auth:** rol `personal`.
- **Uso:** formulario de alta independiente que se conserva como ruta directa; la bandeja usa el wizard modal.

#### `POST /personal/actions/register.php`

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| `csrf_token` | string | Sí | Token de sesión |
| `property_id` | int | Sí | Bien existente |
| `title` | string | Sí | Máximo 255 caracteres |
| `description` | string | Sí | Máximo 5000 caracteres |
| `observation` | string | No | Máximo 5000 caracteres |

- **Auth:** rol `personal`; el creador del ticket es la sesión y no se acepta desde el formulario.
- **Efecto:** crea el ticket y el evento inicial dentro de una transacción; si falla, conserva los valores y vuelve a `/personal/index.php` con el modal abierto.
- **Respuesta:** `302` al detalle del ticket o `303` a la bandeja al volver con error.
- **Límites:** el título admite 255 caracteres; descripción y observación, 5000 cada una.

#### `GET /personal/actions/details.php?ticket_id={id}`

- **Auth:** rol `personal`; solo devuelve tickets activos creados por la sesión.
- **Efecto:** consulta bajo demanda el ticket, bien asociado y su historial.
- **Respuesta:** JSON privado/no almacenable; `404` si el ticket no pertenece al usuario o ya no está disponible.

#### `GET /personal/actions/properties.php`

- **Auth:** rol `personal`.
- **Efecto:** devuelve los bienes locales, dirección de dependencia y unidades de adscripción; la página carga las unidades en segundo plano y el selector solicita los bienes al abrirse.
- **Cache:** las adscripciones de SIA se guardan por cinco minutos; si la fuente falla se informa si se está usando un cache vencido. Sin datos disponibles, devuelve `503`.
- **Respuesta JSON:** `properties`, `directions`, `adscriptions`, `stale` y `cached_at`; usa `Cache-Control: private, no-store`.

#### `GET /tickets.php`

- **Auth:** `admin`, `operador` o `tecnico`.
- **Efecto:** lista tickets activos y ofrece alta en el wizard compartido. `admin` y `operador` pueden además solicitar la baja lógica.

#### `GET /tickets/register.php`

- **Auth:** `personal`, `admin`, `operador` o `tecnico`.
- **Uso:** formulario independiente de alta que se conserva como alternativa al wizard modal.

#### `POST /tickets/actions/register.php`

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| `csrf_token` | string | Sí | Token de sesión |
| `property_id` | int | Sí | Bien existente |
| `title` | string | Sí | No vacío |
| `description` | string | Sí | No vacía |
| `observation` | string | No | — |

- **Auth:** `admin`, `operador` o `tecnico`; si la sesión es `personal`, redirige al flujo del área personal.
- **Efecto:** crea el ticket en estado inicial `iniciado`, registra evento `created` y redirige al detalle.
- **Errores:** `403` rol no permitido; `419` CSRF; `422` campos incompletos o bien inexistente; `500` error de base de datos.

#### `GET /tickets/actions/properties.php`

- **Auth:** `admin`, `operador` o `tecnico`.
- **Efecto:** comparte el contrato de carga de bienes/direcciones con el endpoint personal mediante `include/property_picker_api.php`.
- **Respuesta JSON:** `properties`, `directions`, `adscriptions`, `stale` y `cached_at`; los datos no se cachean en el navegador.
- **Errores:** `503` si no se pueden obtener los bienes o direcciones.

#### `GET /tickets/detail.php?id={id}`

- **Auth:** `admin`, `operador` o `tecnico`; solo muestra tickets activos. El rol `personal` se redirige al detalle personal.
- **Efecto:** presenta los datos del ticket y su historial; el soporte puede registrar seguimiento y cambiar el estado.

#### `POST /tickets/actions/history.php`

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| `csrf_token` | string | Sí | Token de sesión |
| `ticket_id` | int | Sí | Ticket activo |
| `description` | string | Sí | Nota de seguimiento no vacía |

- **Auth:** `admin`, `operador` o `tecnico`.
- **Efecto:** agrega evento `comment` y actualiza `updated_at`.
- **Respuesta:** `302` al detalle del ticket.

#### `POST /tickets/actions/status.php`

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| `csrf_token` | string | Sí | Token de sesión |
| `ticket_id` | int | Sí | Ticket activo |
| `status_id` | int | Sí | Estado existente |

- **Auth:** `admin`, `operador` o `tecnico`.
- **Efecto:** actualiza el estado, registra `status_change` y actualiza `closed_at` cuando el estado es `finalizado`.
- **Respuesta:** `302` a `/tickets.php`.
- **Errores:** `403` rol no permitido; `419` CSRF; `422` parámetros/estado inválidos; `404` ticket inexistente o dado de baja.

#### `POST /tickets/actions/delete.php`

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `csrf_token` | string | Sí | Token de sesión |
| `ticket_id` | int | Sí | ID del ticket |

- **Auth:** `admin` u `operador`.
- **Efecto:** eliminación lógica transaccional, registra al actor en `ticket_histories` y conserva el historial previo.
- **Respuesta:** `302` a `/tickets.php` con confirmación.
- **Errores:** `403` rol no permitido; `404` ticket inexistente/eliminado; `419` CSRF; `422` ID inválido; `500` error de base de datos.

### 3.7 Reportes

#### `GET /report.php`

- **Parámetros:** ninguno.
- **Auth:** sesión.
- **Efecto:** ejecuta `indexWithMaintenances('properties.*', 'date ASC')`.
- **Consulta base:** bienes `MALO`/`MUY MALO` con al menos un mantenimiento cuya nota contiene `desincorporación`.
- **Respuesta:** HTML imprimible con un bloque por bien.
- **Nota F2:** la consulta usa `SELECT DISTINCT` para no repetir bienes con varias notas coincidentes.

#### `GET /batch.php`

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `property_id` | int | No | Primer bien |
| `property_id2` | int | No | Segundo bien |

- **Efecto:** renderiza dos historiales lado a lado.
- **Casos límite:** si falta un parámetro o el bien no existe, muestra “Bien no encontrado”.

### 3.8 Importación

#### `GET /imports.php` / `POST /imports.php`

- **Auth:** `admin` u `operador`.
- **GET:** pantalla de advertencia con botón de confirmación y CSRF.
- **POST:** lee `storage/maintenances.csv` y procesa cada fila mediante `include/csv_import.php`.
- **Reporte:** insertados, omitidos (bien no encontrado) y errores (fecha inválida o fallo de inserción).
- **Efecto por fila válida:** inserta `maintenances` con `status = 'finalizado'` y `time_taken = NULL`.
- **Formato esperado:** `id_mantenimiento,tipo,descripcion,fname,usuario,coment_user,fecha,obs,estado`.
- **Delimitador:** `,`.
- **Límites:** 5 MB y 5000 filas no vacías; las filas inválidas se reportan y omiten. El archivo se procesa en transacción; un fallo no capturado revierte la operación.
- **Errores:** `419` CSRF; `403` rol no permitido.

#### `GET /updates.php` / `POST /updates.php`

- **Auth:** `admin` u `operador`.
- **GET:** pantalla de advertencia con botón de confirmación y CSRF.
- **POST:** ofrece tres fuentes: CSV, reporte `.xls` descargado del sistema de bienes o PostgreSQL legacy. La conexión PostgreSQL se realiza con PDO dentro de una transacción `REPEATABLE READ READ ONLY`; la base legacy no recibe escrituras.
- **PostgreSQL:** consulta `public.bien015` y `public.bien004`, exige una sola empresa con código `38`, y selecciona `desincorporado = 'N'`. No excluye `bien_en_salida = 'S'`, en concordancia con el reporte oficial. El límite es 5000 bienes.
- **Configuración PostgreSQL:** variables de entorno `SYSTEC_LEGACY_PG_USER` y `SYSTEC_LEGACY_PG_PASSWORD` obligatorias; `SYSTEC_LEGACY_PG_HOST` (por defecto `localhost`), `SYSTEC_LEGACY_PG_PORT` (5432) y `SYSTEC_LEGACY_PG_DATABASE` (`DATOS`). Se leen del entorno o del `.env` local, ignorado por Git y denegado por Apache/router. Se requiere `pdo_pgsql`; configurar el usuario del servicio web con privilegios mínimos de lectura.
- **Mapeo de código para empresa 38:** grupo = posición 1 de `cod_clasificacion`; subgrupo = posiciones 3–6; sección = posiciones 8–10; identificador = `num_bien`. Se valida la reconstrucción contra `cod_bien_mue` y se cancela si hay identificadores repetidos.
- **Mapeo:** denominación, características, material y color se conservan en descripción cuando no aparecen allí; marca/modelo se componen para el campo modelo; `serial1` va a serial, `color` a color y `edo_bien` al estado. `MUY BUENO` se normaliza como `BUENO`. No se importan valor de incorporación, `serial2` ni datos organizativos porque no tienen campos equivalentes en el modelo SysTec.
- **Reporte final:** filas consideradas, bienes nuevos, estados actualizados, sin cambios y errores, además del resultado por bien.
- **CSV esperado:** filas de 11 columnas delimitadas por `;`: `grupo;subgrupo;seccion;ident;description;marca;modelo;serial;material;color;status`.
- **Reporte XLS esperado:** columnas `CODIGO`, `DENOMINACION DEL BIEN`, `MARCA`, `MODELO`, `SERIAL`, `MATERIAL`, `COLOR` y `ESTADO`; `CODIGO` debe tener cuatro segmentos numéricos separados por guiones. Los campos `MATERIAL` y `COLOR` se conservan en la descripción si aún no aparecen en ella.
- **Normalización de estado:** `MUY BUENO` se importa como `BUENO`, de acuerdo con el catálogo vigente.
- **Campos no almacenados:** `VALOR INCORP.` no tiene un campo equivalente en el modelo actual y no se importa.
- **Regla:** si el bien existe, solo actualiza `status`; si no, lo inserta.
- **Límites de archivos:** 5 MB y 5000 bienes; los errores de datos por fila se reportan y omiten. Un XLS sin encabezados requeridos o con un código no válido se rechaza antes de iniciar la transacción. Un fallo no capturado durante el procesamiento revierte la operación.
- **Errores:** `419` CSRF; `403` rol no permitido.

### 3.9 Archivos estáticos y datos

| Ruta | Contenido | Acceso |
|---|---|---|
| `/vendor/bootstrap/...` | CSS/JS de Bootstrap | Público |
| `/vendor/datatables/...` | DataTables + jQuery 3.7.0 | Público |
| `/storage/db.db` | Base completa | `403` |
| `/storage/backups/...` | Backups | `403` |
| `/storage/*.csv` | Datos legacy | `403` |
| `/storage/logs/...` | Logs | `403` |
| `/docs/...`, `/grafo/...`, `/spec/...` | Documentación | `403` |
| `/bin/...`, `/include/...`, `/conn.php` | Código interno | `403` |

---

## 4. Catálogo de errores actual

| Origen | Mensaje / comportamiento | Código |
|---|---|---|
| Validación de alta/edición | `Error: El código es obligatorio...` | `200` (texto de `die()`) |
| Validación de alta/edición | `Error: El tipo es obligatorio...` | `200` |
| Validación de alta/edición | `Error: El técnico es obligatorio...` | `200` |
| Validación de alta/edición | `Error: la fecha es obligatoria...` | `200` |
| Validación de alta/edición | `Error: La Razón del mantenimiento es obligatoria...` | `200` |
| Validación de alta/edición | `Error: La descripción es obligatorio...` | `200` |
| Bien inexistente | `El bien seleccionado no existe.` | `422` |
| Fecha inválida | `La fecha de inicio no es válida.` | `422` |
| Estado inválido | `El estado del mantenimiento no es válido.` | `422` |
| Transición inválida | `La transición de estado no está permitida.` | `422` |
| Mantenimiento inexistente | `Mantenimiento no encontrado.` | `404` |
| Bien inválido | `Bien inválido` | `400` |
| CSRF inválido | `Token CSRF inválido o expirado...` | `419` |
| Método incorrecto | `Método no permitido` | `405` |
| Rol sin permiso | HTML “Acceso denegado” | `403` |
| Acceso directo denegado | `Acceso no permitido` | `403` |
| Error interno | Mensaje genérico; detalle en log | `500` |

**Pendiente:** catálogo estable de errores de dominio cuando se agregue una capa de servicio (F3).

---

## 5. Validaciones implementadas

| Regla | Dónde | Cubre |
|---|---|---|
| `property_id` entero y existente | Acciones + `validations/*` | Alta/edición/estado |
| `type` en catálogo | `validations/*` | Alta/edición |
| `technician` en catálogo | `validations/*` | Alta/edición |
| `date` con `strtotime()` válido | Acciones | Alta/edición |
| `next_maintenance_date` válida si se envía | Acciones | Alta/edición |
| `failure_reason` y `description` no vacíos | `validations/*` | Alta/edición |
| `id` entero y existente | Acciones | Edición/estado |
| `status` en catálogo | Acciones | Edición/estado |
| Transición permitida | `maintenance_transition_allowed()` | Edición/estado |
| Longitudes máximas | No implementado | — |
| Rate limiting | No implementado | — |

---

## 6. Idempotencia y efectos secundarios

| Acción | Idempotente | Nota |
|---|---|---|
| Ver dashboard/historial/reportes | Sí | Solo lectura |
| Login/logout | No | Cambia la sesión |
| Cambiar estado de bien | No | Cada POST avanza el ciclo |
| Cambiar estado de mantenimiento | No | Cada POST cambia estado y puede recalcular horas |
| Registrar mantenimiento | No | Cada POST crea una fila |
| Editar mantenimiento | Sí en el resultado | Última escritura gana; sin auditoría |
| Importar mantenimientos | No | Cada POST inserta filas de nuevo |
| Actualizar inventario | Parcial | Inserta nuevos; en existentes solo cambia `status` |

---

## 7. Propuesta de API futura (no implementada)

Si el sistema se moderniza, el contrato mínimo recomendado sería:

| Método | Ruta | Propósito |
|---|---|---|
| GET | `/api/properties` | Listar/buscar bienes |
| GET | `/api/properties/{id}` | Detalle de bien |
| PATCH | `/api/properties/{id}/status` | Cambiar estado con validación y auditoría |
| GET | `/api/properties/{id}/maintenances` | Historial |
| POST | `/api/maintenances` | Crear mantenimiento |
| PATCH | `/api/maintenances/{id}` | Editar |
| POST | `/api/maintenances/{id}/transitions` | Transición válida |
| GET | `/api/reports/damaged` | Reporte de dañados |
| POST | `/api/imports/properties` | Importación con reporte de errores |
| POST | `/api/imports/maintenances` | Importación con reporte de errores |

Requisitos: autenticación, autorización, CSRF o token, validación de esquema, errores estables, idempotencia y auditoría. Ver `SECURITY.md` y `TODO.md`.

---

Ver también: `README.md`, `ARCHITECTURE.md`, `DOMAIN.md`, `DATABASE.md`, `SECURITY.md`, `CONVENTIONS.md`, `TODO.md`.
