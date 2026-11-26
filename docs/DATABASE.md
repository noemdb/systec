# DATABASE.md — SysTec GMB

> **Estado:** v2.0 (esquema 001–011, usuarios/perfiles e informes de rendición) · **Actualizado:** 2026-11-26.
> **Motor:** SQLite 3, archivo `storage/db.db`.
> **Snapshot:** conteos y distribuciones verificados en modo de solo lectura el 2026-11-26.
> **Regla de oro:** `ensureSchema()` crea el esquema base de una base vacía; **no aplica migraciones a bases antiguas**. La evolución se controla con `bin/migrate.php` y `schema_migrations`.

---

## 1. Motor y convenciones

| Ítem | Situación actual | Consecuencia |
|---|---|---|
| Motor | SQLite 3 mediante `SQLite3`; PDO SQLite en importaciones/pruebas | Archivo único; escritura con `busyTimeout(5000)` y transacciones `BEGIN IMMEDIATE` |
| Archivo | `storage/db.db`; configurable con `SYSTEC_DB` | `storage/` está dentro del árbol del proyecto, no necesariamente fuera del `DocumentRoot` |
| Protección HTTP | `router.php` bloquea `/storage`; `.htaccess` la redirige con 403 y deniega extensiones sensibles | La denegación depende de servir la aplicación con una de esas dos protecciones |
| Creación de esquema | `conn.php::ensureSchema()` cuando no existe la tabla `users` | Si `users` ya existe, la función retorna sin agregar columnas o tablas |
| Migraciones | `migrations/001…011`, registradas en `schema_migrations` | Las 11 migraciones están aplicadas en la base actual |
| Tipos | `INTEGER`, `TEXT`, `STRING`, `DATETIME`, `VARCHAR(255)` | SQLite usa afinidad dinámica; no aplica longitudes ni tipos estrictos |
| Fechas | Se pretende `YYYY-MM-DD HH:MM:SS`; ausencias como `NULL` | Hay registros recientes con separador ISO `T`; SQLite/PHP los interpretan |
| Identificadores | `INTEGER PRIMARY KEY AUTOINCREMENT` | Sin UUID ni claves naturales |
| Nombres | `snake_case`, plural, salvo `profile` | Convención de facto |
| Borrado | Tickets con baja lógica; usuarios e informes con borrado físico | El historial del ticket se conserva; el perfil se elimina en cascada |
| Auditoría | No hay bitácora relacional genérica | Algunas acciones administrativas emiten entradas en el log de PHP |
| Claves foráneas | Solo `profile.user_id → users.id` | `DB::__construct()` activa `PRAGMA foreign_keys = ON` por conexión; las demás relaciones son lógicas |
| Catálogos | Triggers de 001 para estado/tipo de bienes y mantenimientos | Estados de ticket y transiciones se controlan en aplicación |
| Índices | Inventario, mantenimientos, tickets, historial, informes y email | Ver catálogo de migraciones |
| Backups | Copias manuales y script `bin/backup.sh` | No hay cron de producción confirmado |

`schema_migrations` tiene `version TEXT PRIMARY KEY` y `applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`. El runner ordena los archivos por nombre natural, valida `NNN_nombre.sql`, ejecuta cada migración en una transacción y registra la versión en la misma transacción.

---

## 2. Diagrama entidad-relación

Las líneas marcadas como **FK** son restricciones físicas. El resto son relaciones implementadas y validadas por la aplicación.

```mermaid
erDiagram
    USERS ||--o| PROFILE : "user_id FK UNIQUE ON DELETE CASCADE"
    PROPERTIES ||--o{ MAINTENANCES : "property_id lógica"
    TICKETS ||--o{ MAINTENANCES : "ticket_id lógica opcional"
    PROPERTIES ||--o{ TICKETS : "property_id lógica"
    TICKET_STATUS ||--o{ TICKETS : "status_id lógica"
    USERS ||--o{ TICKETS : "created_by lógica"
    USERS ||--o{ TICKETS : "assigned_to lógica"
    TICKETS ||--o{ TICKET_HISTORIES : "ticket_id lógica"
    USERS ||--o{ TICKET_HISTORIES : "actor_user_id lógica"
    TICKET_STATUS ||--o{ TICKET_HISTORIES : "from_status_id lógica"
    TICKET_STATUS ||--o{ TICKET_HISTORIES : "to_status_id lógica"
    USERS ||--o{ RENDITION_REPORTS : "created_by lógica"
    USERS ||--o{ RENDITION_REPORTS : "approved_by lógica"

    PROPERTIES {
        integer id PK
        string grupo
        string subgrupo
        string seccion
        string ident
        string adscription
        string description
        string model
        string color
        string serial
        string status
        text responsible_name
        text responsible_status
        text assigned_name
        text assigned_status
    }

    MAINTENANCES {
        integer id PK
        integer property_id
        integer ticket_id
        string code
        string type
        string description
        datetime date
        integer time_taken
        string technician
        datetime next_maintenance_date
        text failure_reason
        text notes
        string status
    }

    USERS {
        integer id PK
        string username UK
        string email
        string password_hash
        string role
        integer active
        datetime created_at
    }

    PROFILE {
        integer id PK
        integer user_id FK_UK
        text first_name
        text last_name
        string avatar_url
    }

    TICKET_STATUS {
        integer id PK
        string code UK
        string name
        integer sort_order
        integer is_terminal
        integer active
    }

    TICKETS {
        integer id PK
        integer property_id
        integer status_id
        string title
        text description
        text observation
        integer created_by
        integer assigned_to
        datetime created_at
        datetime updated_at
        datetime closed_at
        datetime deleted_at
    }

    TICKET_HISTORIES {
        integer id PK
        integer ticket_id
        integer actor_user_id
        string action_type
        text description
        text observation
        integer from_status_id
        integer to_status_id
        datetime created_at
    }

    RENDITION_REPORTS {
        integer id PK
        string report_code UK
        string title
        string date_from
        string date_to
        string scope_hash UK
        text filters_json
        text parameters_json
        text stats_json
        text maintenance_ids_json
        text ticket_ids_json
        text content_markdown
        integer ai_generated
        string status
        integer created_by
        integer approved_by
        datetime created_at
        datetime updated_at
        datetime approved_at
    }
```

`rendition_reports` guarda los IDs de mantenimientos y tickets en JSON, no como claves foráneas. Su `scope_hash` identifica de forma única el período y los filtros normalizados; no incluye el título ni los parámetros editoriales.

---

## 3. DDL efectivo y límites de `ensureSchema()`

El esquema de una base vacía se define en `conn.php`. Las columnas y restricciones añadidas después proceden de las migraciones; el DDL siguiente resume el estado efectivo verificado en `sqlite_master`.

### 3.1 Inventario, mantenimientos y usuarios

```sql
CREATE TABLE properties (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    grupo             STRING,
    subgrupo          STRING,
    seccion           STRING,
    ident             STRING,
    adscription       STRING,
    description       STRING,
    model             STRING,
    color             STRING,
    serial            STRING,
    status            STRING,
    responsible_name  TEXT,
    responsible_status TEXT,
    assigned_name     TEXT,
    assigned_status   TEXT
);

CREATE TABLE maintenances (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id           INTEGER,
    ticket_id             INTEGER,
    code                  STRING,
    type                  STRING,
    description           STRING,
    date                  DATETIME,
    time_taken            INTEGER,
    technician            VARCHAR(255),
    next_maintenance_date DATETIME,
    failure_reason        TEXT,
    notes                 TEXT,
    status                STRING
);

CREATE TABLE users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL DEFAULT 'consulta',
    active        INTEGER NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    email         TEXT
);

CREATE UNIQUE INDEX idx_users_email_unique
    ON users (email)
    WHERE email IS NOT NULL AND trim(email) <> '';

CREATE TABLE profile (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    first_name TEXT NOT NULL,
    last_name  TEXT NOT NULL,
    avatar_url TEXT
);
```

El índice de email es **parcial**: admite varios `NULL` y vacíos, y no normaliza mayúsculas/minúsculas. La aplicación comprueba duplicados de `username` sin distinguir mayúsculas al crear usuarios, aunque el `UNIQUE` SQLite de `username` sea binario.

### 3.2 Tickets e historial

```sql
CREATE TABLE ticket_status (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    code        TEXT NOT NULL UNIQUE,
    name        TEXT NOT NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    is_terminal INTEGER NOT NULL DEFAULT 0,
    active      INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE tickets (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    status_id   INTEGER NOT NULL,
    title       TEXT NOT NULL,
    description TEXT NOT NULL,
    observation TEXT,
    created_by  INTEGER NOT NULL,
    assigned_to INTEGER,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at   DATETIME,
    deleted_at  DATETIME
);

CREATE TABLE ticket_histories (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id      INTEGER NOT NULL,
    actor_user_id  INTEGER NOT NULL,
    action_type    TEXT NOT NULL,
    description    TEXT NOT NULL,
    observation    TEXT,
    from_status_id INTEGER,
    to_status_id   INTEGER,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

### 3.3 Informes de rendición

```sql
CREATE TABLE rendition_reports (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    report_code           TEXT NOT NULL UNIQUE,
    title                 TEXT NOT NULL,
    date_from             TEXT NOT NULL,
    date_to               TEXT NOT NULL,
    scope_hash            TEXT NOT NULL UNIQUE,
    filters_json          TEXT NOT NULL DEFAULT '{}',
    parameters_json       TEXT NOT NULL DEFAULT '{}',
    stats_json            TEXT NOT NULL DEFAULT '{}',
    maintenance_ids_json  TEXT NOT NULL DEFAULT '[]',
    ticket_ids_json       TEXT NOT NULL DEFAULT '[]',
    content_markdown      TEXT NOT NULL,
    ai_generated          INTEGER NOT NULL DEFAULT 0,
    status                TEXT NOT NULL DEFAULT 'borrador'
                              CHECK (status IN ('borrador', 'aprobado')),
    created_by            INTEGER NOT NULL,
    approved_by           INTEGER,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at           DATETIME
);

CREATE INDEX idx_rendition_reports_dates
    ON rendition_reports (date_from DESC, date_to DESC, id DESC);
CREATE INDEX idx_rendition_reports_status
    ON rendition_reports (status, updated_at DESC);
```

**Comportamiento importante:**

- `ensureSchema()` comprueba únicamente si existe `users`. Si existe, no agrega `email`, `profile`, `deleted_at`, `ticket_id`, asignaciones ni informes.
- `RenditionReportRepository::ensureTableExists()` puede ejecutar el `CREATE TABLE IF NOT EXISTS` y los dos índices de informes en tiempo de ejecución como salvaguarda. No registra la migración 008 ni migra el resto del esquema.
- Una base recién creada por `ensureSchema()` todavía necesita el runner para obtener triggers, índices y las columnas incorporadas por 004–011.

---

## 4. Diccionario de datos

### 4.1 `properties` — bien

| Columna | Tipo SQLite | Nulo | Regla de negocio |
|---|---|---:|---|
| `id` | INTEGER | No | PK autoincremental |
| `grupo`, `subgrupo`, `seccion` | STRING | Sí | Componentes del código; se normalizan con relleno al importar |
| `ident` | STRING | Sí | Clave de búsqueda de importación; indexado, no `UNIQUE` |
| `adscription` | STRING | Sí | Respaldo local de la unidad operativa; la vista prioriza la caché institucional cuando existe |
| `description` | STRING | Sí | Texto libre del bien |
| `model`, `color`, `serial` | STRING | Sí | Texto libre; pueden estar vacíos |
| `status` | STRING | Sí en DDL | Trigger exige `BUENO`, `REGULAR`, `MALO` o `MUY MALO` en INSERT/UPDATE |
| `responsible_name` | TEXT | Sí | Nombre del responsable sincronizado desde legacy |
| `responsible_status` | TEXT | Sí | Estado del responsable; texto libre |
| `assigned_name` | TEXT | Sí | Nombre del usuario asignado al bien |
| `assigned_status` | TEXT | Sí | Estado de la asignación; texto libre |

**Índice:** `idx_properties_ident(ident)`.

### 4.2 `maintenances` — mantenimiento

| Columna | Tipo SQLite | Nulo | Regla de negocio |
|---|---|---:|---|
| `id` | INTEGER | No | PK autoincremental |
| `property_id` | INTEGER | Sí en DDL | La aplicación exige un bien existente; no hay FK |
| `ticket_id` | INTEGER | Sí | Relación opcional con un ticket activo del mismo bien; índice simple |
| `code` | STRING | Sí | Snapshot del código del bien |
| `type` | STRING | Sí en DDL | Trigger exige `preventivo`, `correctivo`, `predictivo` o `evaluativo` |
| `description` | STRING | Sí | Trabajo o incidencia |
| `date` | DATETIME | Sí | Fecha/hora del mantenimiento |
| `time_taken` | INTEGER | Sí | Horas declaradas; los nulos se tratan como cero en agregados |
| `technician` | VARCHAR(255) | Sí | Texto libre; el selector combina históricos y usuarios activos con rol `tecnico` |
| `next_maintenance_date` | DATETIME | Sí | Base del vencimiento |
| `failure_reason`, `notes` | TEXT | Sí | Causa y observaciones |
| `status` | STRING | Sí en DDL | Trigger exige `iniciado`, `revision` o `finalizado` |

El trigger de transición permite `iniciado → revision/finalizado`, `revision → finalizado` y `finalizado → revision`. No permite regresar a `iniciado`.

**Índices:** `idx_maintenances_property`, `idx_maintenances_status_next(status, next_maintenance_date)` y `idx_maintenances_ticket_id`.

### 4.3 `users` — cuenta de acceso

| Columna | Tipo SQLite | Nulo | Regla de negocio |
|---|---|---:|---|
| `id` | INTEGER | No | PK autoincremental |
| `username` | TEXT | No | `UNIQUE`; la UI/CLI exigen 3–64 caracteres `[a-zA-Z0-9._-]` |
| `email` | TEXT | Sí | Añadido por 009; índice único parcial para valores no vacíos |
| `password_hash` | TEXT | No | `password_hash(PASSWORD_DEFAULT)` |
| `role` | TEXT | No | `admin`, `operador`, `tecnico`, `consulta`, `personal`; default `consulta` |
| `active` | INTEGER | No | `1` habilita login; `0` lo impide |
| `created_at` | DATETIME | No | Fecha/hora de alta |

La gestión normal se hace en `users.php` con `include/user_manage_drawer.php` y `users/actions/save.php`. El formulario actual muestra el email, pero **no lo edita**; `auth_admin_save_user()` tampoco lo actualiza. `bin/create-user.php` sigue disponible para CLI.

### 4.4 `profile` — perfil uno a uno

| Columna | Tipo SQLite | Nulo | Regla de negocio |
|---|---|---:|---|
| `id` | INTEGER | No | PK autoincremental |
| `user_id` | INTEGER | No | `UNIQUE` y FK a `users.id` con `ON DELETE CASCADE` |
| `first_name` | TEXT | No | Nombre; se guarda junto con apellidos o no se crea el perfil |
| `last_name` | TEXT | No | Apellidos |
| `avatar_url` | TEXT | Sí | URL `http`/`https`; la UI valida protocolo y longitud |

No todas las cuentas están obligadas a tener perfil. El alta/edición administrativa guarda usuario y perfil en una sola transacción cuando se indican ambos nombres. Al eliminar un usuario, SQLite elimina su perfil en cascada si la conexión tiene las FK activas.

### 4.5 `ticket_status` — catálogo de estados

| Columna | Tipo SQLite | Nulo | Regla de negocio |
|---|---|---:|---|
| `id` | INTEGER | No | PK |
| `code` | TEXT | No | `UNIQUE`; código estable |
| `name` | TEXT | No | Etiqueta visible |
| `sort_order` | INTEGER | No | Orden de presentación |
| `is_terminal` | INTEGER | No | `finalizado` es terminal |
| `active` | INTEGER | No | Disponibilidad del estado |

Estados iniciales: `iniciado`, `en_progreso`, `con_retraso`, `en_espera` y `finalizado`.

### 4.6 `tickets` — incidencia

| Columna | Tipo SQLite | Nulo | Regla de negocio |
|---|---|---:|---|
| `id` | INTEGER | No | PK |
| `property_id` | INTEGER | No | Bien relacionado por lógica |
| `status_id` | INTEGER | No | Estado actual por lógica |
| `title`, `description` | TEXT | No | Requeridos al registrar |
| `observation` | TEXT | Sí | Opcional |
| `created_by` | INTEGER | No | Creador por lógica |
| `assigned_to` | INTEGER | Sí | Asignación a usuario; no es lo mismo que `properties.assigned_name` |
| `created_at`, `updated_at` | DATETIME | No | Trazabilidad funcional del ticket |
| `closed_at` | DATETIME | Sí | Se establece al pasar a `finalizado` |
| `deleted_at` | DATETIME | Sí | Baja lógica; `NULL` significa no eliminado |

**Índices:** `idx_tickets_status_updated`, `idx_tickets_created_by_updated`, `idx_tickets_property_created`, `idx_tickets_deleted_updated` y `idx_tickets_personal_active_updated`.

### 4.7 `ticket_histories` — eventos

| Columna | Tipo SQLite | Nulo | Regla de negocio |
|---|---|---:|---|
| `id` | INTEGER | No | PK |
| `ticket_id` | INTEGER | No | Ticket relacionado por lógica |
| `actor_user_id` | INTEGER | No | Usuario que generó el evento |
| `action_type` | TEXT | No | `created`, `comment`, `status_change`, `deleted` |
| `description` | TEXT | No | Detalle del evento |
| `observation` | TEXT | Sí | Opcional |
| `from_status_id`, `to_status_id` | INTEGER | Sí | Estado anterior/nuevo cuando aplica |
| `created_at` | DATETIME | No | Orden cronológico |

**Índice:** `idx_ticket_histories_ticket_created(ticket_id, created_at ASC, id ASC)`.

### 4.8 `rendition_reports` — informe de rendición

| Columna | Tipo SQLite | Nulo | Regla de negocio |
|---|---|---:|---|
| `id` | INTEGER | No | PK |
| `report_code` | TEXT | No | `UNIQUE`; correlativo `IR-YYYY-NNNN` |
| `title` | TEXT | No | Título editorial |
| `date_from`, `date_to` | TEXT | No | Fechas ISO `YYYY-MM-DD` |
| `scope_hash` | TEXT | No | `UNIQUE`; SHA-256 de período + filtros canónicos |
| `filters_json` | TEXT | No | JSON de categoría, adscripción, estado, tipo y técnico |
| `parameters_json` | TEXT | No | JSON de título, unidad, destinatario, preparado por, tickets y notas |
| `stats_json` | TEXT | No | JSON con totales y distribuciones del alcance |
| `maintenance_ids_json`, `ticket_ids_json` | TEXT | No | Arrays JSON de IDs incluidos |
| `content_markdown` | TEXT | No | Documento Markdown; incluye tablas HTML generadas por el repositorio |
| `ai_generated` | INTEGER | No | `1` si se usó narrativa externa con fallback |
| `status` | TEXT | No | `CHECK`: `borrador` o `aprobado` |
| `created_by` | INTEGER | No | Autor por lógica |
| `approved_by`, `approved_at` | INTEGER / DATETIME | Sí | Se completan al aprobar |
| `created_at`, `updated_at` | DATETIME | No | Auditoría mínima del informe |

El repositorio limita `content_markdown` a 150 000 caracteres. Un borrador puede actualizarse para el mismo `scope_hash`; una vez `aprobado`, `saveReport()` rechaza sobrescribirlo. La inmutabilidad es de aplicación, no un trigger SQL. El endpoint administrativo de eliminación puede borrar físicamente cualquier informe, incluido uno aprobado.

### 4.9 `schema_migrations`

| Columna | Tipo | Nulo | Regla |
|---|---|---:|---|
| `version` | TEXT | No | PK; nombre completo del archivo SQL |
| `applied_at` | DATETIME | No | Fecha/hora de registro |

---

## 5. Relaciones e integridad

| Relación | Implementación | Estado del snapshot |
|---|---|---|
| `profile.user_id → users.id` | FK + `UNIQUE` + cascada | 29 perfiles para 29 usuarios; sin huérfanos |
| `maintenances.property_id → properties.id` | Validación en aplicación | 0 huérfanos |
| `maintenances.ticket_id → tickets.id` | Validación de ticket activo y mismo bien | 2 enlaces; 0 inválidos o eliminados |
| `tickets.property_id → properties.id` | Validación en aplicación | 0 huérfanos |
| `tickets.status_id → ticket_status.id` | Catálogo en aplicación | 0 enlaces inválidos |
| `tickets.created_by / assigned_to → users.id` | JOIN y permisos | Sin FK física |
| `ticket_histories.ticket_id → tickets.id` | Creación desde acciones | 0 huérfanos |
| `ticket_histories.actor_user_id → users.id` | JOIN para mostrar actor | Sin FK física |
| `properties.ident` | Búsqueda de importación | 627 distintos; 0 duplicados |
| `maintenances.code` | Snapshot recalificado al registrar/editar | Puede quedar obsoleto en datos históricos |

Consultas de verificación recomendadas:

```sql
PRAGMA integrity_check;
PRAGMA foreign_key_check;

SELECT m.id
FROM maintenances m
LEFT JOIN properties p ON p.id = m.property_id
WHERE p.id IS NULL;

SELECT t.id
FROM tickets t
LEFT JOIN properties p ON p.id = t.property_id
WHERE p.id IS NULL;

SELECT h.id
FROM ticket_histories h
LEFT JOIN tickets t ON t.id = h.ticket_id
WHERE t.id IS NULL;

SELECT ident, COUNT(*) AS total
FROM properties
GROUP BY ident
HAVING COUNT(*) > 1;

SELECT u.id, u.username
FROM users u
LEFT JOIN profile p ON p.user_id = u.id
WHERE p.id IS NULL;
```

---

## 6. Catálogo de migraciones

Todas las migraciones siguientes figuran como aplicadas en `schema_migrations`.

| Versión | Aplicada | Efecto |
|---|---|---|
| `001_normalize_legacy_data.sql` | 2026-11-09 | Normaliza estados/técnico/fechas; crea triggers de estado, tipo y transición de mantenimientos |
| `002_add_indexes.sql` | 2026-11-09 | Índices de `properties.ident`, `maintenances.property_id` y `(status, next_maintenance_date)` |
| `003_add_ticket_support.sql` | 2026-11-11 | Crea `ticket_status`, `tickets`, `ticket_histories`, cinco estados y sus índices |
| `004_soft_delete_tickets.sql` | 2026-11-11 | Agrega `tickets.deleted_at` y su índice |
| `005_personal_ticket_lookup.sql` | 2026-11-11 | Índice compuesto para tickets personales activos |
| `006_add_property_assignments.sql` | 2026-11-13 | Agrega responsable/asignado y sus estados a `properties` |
| `007_link_maintenances_to_tickets.sql` | 2026-11-20 | Agrega `maintenances.ticket_id` y su índice |
| `008_create_rendition_reports.sql` | 2026-11-24 | Crea `rendition_reports` e índices de fechas/estado |
| `009_seed_personal_users.sql` | 2026-11-26 | Agrega `users.email`, siembra cuentas personales canónicas y crea el índice único parcial de email |
| `010_create_profile.sql` | 2026-11-26 | Crea `profile` y siembra perfiles de usuarios canónicos existentes |
| `011_rename_personal_usernames.sql` | 2026-11-26 | Renombra usernames personales antiguos y alinea emails; conserva IDs, perfiles y credenciales |

### 6.1 Uso del runner

```bash
php bin/migrate.php --backup=/ruta/a/backup-verificado.db
```

Antes de aplicar cambios, el runner:

1. Abre el backup en solo lectura.
2. Exige `PRAGMA integrity_check = ok`.
3. Compara los conteos de `properties`, `maintenances` y `users` del backup con los de la base de destino.
4. Ejecuta cada migración pendiente y su registro en una transacción.

No compara tickets, perfiles ni informes. El backup debe corresponder a la base que se va a migrar; no es un archivo arbitrario que solo tenga `integrity_check = ok`.

### 6.2 Compatibilidad de restores antiguos

Un restore no queda listo para la aplicación solo con abrir el archivo:

1. Restaurar en una ruta aislada, nunca sobre `storage/db.db` productiva.
2. Revisar `schema_migrations` y las columnas reales. Si el backup contiene `users`, `ensureSchema()` retornará y no completará el esquema.
3. Crear una copia de seguridad verificada de la base restaurada. Para migrar esa copia, apuntar `SYSTEC_DB` a ella y pasar esa misma copia verificada como `--backup`, de modo que los conteos coincidan.
4. Aplicar las migraciones pendientes y verificar `integrity_check`, `foreign_key_check`, conteos y acceso funcional.
5. Si se restauró un estado anterior a 010/011, ejecutar `bin/seed-profiles.php` solo después de confirmar que existen todos los usernames canónicos.

Situaciones especiales:

- Un backup sin tabla `users` permite que `ensureSchema()` cree el esquema base; aun así faltan las evoluciones 001–011 hasta ejecutar el runner.
- Un backup con `users` pero sin `email`, `profile`, `ticket_id`, `deleted_at` o asignaciones puede abrirse con SQLite, pero las páginas actuales fallan hasta migrar.
- 010 siembra perfiles haciendo JOIN por username. Si después se aplica 011 a un username antiguo, el usuario renombrado puede quedarse sin perfil; el seeder de perfiles lo repara.
- 011 puede fallar por `UNIQUE` si coexisten el username antiguo y el canónico, o si sus emails ya están ocupados. Hay que reconciliar esas identidades antes de migrar; la transacción de la migración se revierte si falla.
- `RenditionReportRepository::ensureTableExists()` solo salva la tabla/índices de informes. No sustituye la secuencia de migraciones.
- No se deben reejecutar manualmente migraciones con `ALTER TABLE` ya registradas.

Copias presentes y alcance verificado:

| Backup | Última versión registrada | Conteos relevantes | Compatibilidad |
|---|---|---|---|
| `storage/backups/2025-04-09/db.db` | Ninguna; no tiene `users` | 597 bienes / 321 mantenimientos | Bootstrap + migraciones 001–011 |
| `storage/backups/2026-11-09/db-before-f2-normalization.db` | Ninguna | 617 / 547 / 4 usuarios | `ensureSchema()` no migra; ejecutar 001–011 |
| `storage/backups/2026-11-09/db-after-f2-normalization.db` | 001–002 | 617 / 547 / 4 usuarios | Ejecutar 003–011 |
| `storage/backups/2026-11-11-before-ticket-soft-delete.db` | 001–002; ya contiene tablas de tickets | 626 / 547 / 5 usuarios / 1 ticket | 003 es tolerante a tablas existentes; ejecutar 003–011 |
| `storage/backups/2026-11-11-before-personal-ticket-index.db` | 001–004 | 626 / 547 / 5 usuarios / 1 ticket | Ejecutar 005–011 |
| `storage/backups/db-before-007-2026-11-20.db` | 001–006 | 627 / 322 / 5 usuarios / 5 tickets | Ejecutar 007–011 |

### 6.3 Seeder de perfiles

`bin/seed-profiles.php` es idempotente: hace upsert de `first_name` y `last_name` por username, conserva `avatar_url` y no crea usuarios. Sin embargo, exige los 23 usernames canónicos dentro de una transacción; si falta cualquiera, revierte toda la operación y termina con error.

En el snapshot actual falta `abrizzuela`, por lo que **el seeder canónico falla hasta reconciliar esa cuenta**. No se ejecutó durante esta actualización documental.

---

## 7. Volumen y calidad de datos — snapshot 2026-11-26

### 7.1 Volumen e integridad

| Métrica | Valor |
|---|---:|
| Bienes | 627 |
| Mantenimientos | 324 |
| Bienes con al menos un mantenimiento | 169 |
| Bienes sin mantenimiento | 458 |
| Mantenimientos sin bien | 0 |
| Usuarios | 29 |
| Perfiles | 29 |
| Tickets | 8 |
| Tickets no eliminados | 4 |
| Tickets eliminados lógicamente | 4 |
| Eventos de historial | 19 |
| Estados de ticket | 5 |
| Informes de rendición | 1 |
| Migraciones registradas | 11 |
| Tamaño de `storage/db.db` | 520 192 bytes (508 KiB) |
| `PRAGMA integrity_check` | `ok` |
| `PRAGMA foreign_key_check` | Sin filas |
| Journal mode | `delete` |

### 7.2 Estado del bien

| Estado | Filas |
|---|---:|
| `BUENO` | 392 |
| `MALO` | 177 |
| `REGULAR` | 52 |
| `MUY MALO` | 6 |

No hay estados fuera del catálogo.

### 7.3 Estado y tipo de mantenimiento

| Estado | Filas |
|---|---:|
| `finalizado` | 324 |
| `revision` | 0 |
| `iniciado` | 0 |

| Tipo | Filas |
|---|---:|
| `correctivo` | 309 |
| `preventivo` | 14 |
| `evaluativo` | 1 |
| `predictivo` | 0 |

Los 324 mantenimientos tienen `technician = 'ndominguez'`. Todos están finalizados, por lo que el contador de vencidos abiertos es 0. Las fechas van de `1999-11-30 12:00:00` a `2026-11-26T09:27`; no se detectaron fechas inválidas ni futuras respecto del snapshot.

### 7.4 Asignaciones de bienes

| `responsible_status` | Filas |
|---|---:|
| `ACTIVO` | 428 |
| `RENUNCIA` | 184 |
| `INACTIVO` | 15 |

| `assigned_status` | Filas |
|---|---:|
| `ACTIVO` | 373 |
| `SIN FICHA` | 166 |
| `RENUNCIA` | 86 |
| `INACTIVO` | 2 |

`responsible_name` está presente en los 627 bienes. `assigned_name` está vacío en 166, coincidiendo con `SIN FICHA`. `adscription` también está poblado en los 627 bienes.

### 7.5 Usuarios y perfiles

| Rol | Activos | Inactivos | Total |
|---|---:|---:|---:|
| `admin` | 1 | 0 | 1 |
| `operador` | 1 | 0 | 1 |
| `tecnico` | 1 | 0 | 1 |
| `consulta` | 2 | 0 | 2 |
| `personal` | 23 | 1 | 24 |
| **Total** | **28** | **1** | **29** |

- Hay 29 perfiles, uno por usuario; todos tienen `avatar_url` vacío.
- 22 usuarios tienen email no vacío y 7 no lo tienen; no hay duplicados de email al normalizar mayúsculas y espacios.
- De las 24 cuentas `personal`, 22 usan nombres del catálogo canónico de 009/010; además existen `abrizuela` y `personal`.
- **Inconsistencia pendiente:** existe `abrizuela` (activo, con perfil) y no existe `abrizzuela`, el username canónico del seeder. Esto explica que la carga canónica de perfiles falle y debe reconciliarse antes de ejecutarla.

### 7.6 Tickets e historial

| Estado | No eliminados | Eliminados | Total |
|---|---:|---:|---:|
| `iniciado` | 0 | 4 | 4 |
| `en_progreso` | 1 | 0 | 1 |
| `con_retraso` | 1 | 0 | 1 |
| `en_espera` | 1 | 0 | 1 |
| `finalizado` | 1 | 0 | 1 |
| **Total** | **4** | **4** | **8** |

| Tipo de evento | Filas |
|---|---:|
| `created` | 8 |
| `status_change` | 4 |
| `deleted` | 4 |
| `comment` | 3 |

Los 8 tickets tienen `assigned_to` vacío. Los 4 tickets eliminados conservan sus eventos.

### 7.7 Informes de rendición

| Estado | `ai_generated` | Filas |
|---|---:|---:|
| `aprobado` | 1 | 1 |
| `borrador` | — | 0 |

El informe actual es `IR-2026-0001`, para el período `2026-11-01` a `2026-11-30`, creado y aprobado el 2026-11-24. No hay informes en borrador.

### 7.8 Nulos y campos vacíos

“Vacío” incluye `NULL` o texto vacío después de `trim()`.

| Campo | Vacíos / nulos |
|---|---:|
| `properties.model` | 298 |
| `properties.color` | 613 |
| `properties.serial` | 324 |
| `properties.adscription` | 0 |
| `properties.assigned_name` | 166 |
| `maintenances.time_taken` | 16 |
| `maintenances.next_maintenance_date` | 251 |
| `maintenances.failure_reason` | 12 |
| `maintenances.notes` | 60 |
| `maintenances.ticket_id` | 322 |
| `users.email` | 7 |
| `profile.avatar_url` | 29 |
| `tickets.observation` | 6 |
| `tickets.assigned_to` | 8 |
| `tickets.closed_at` | 4 |
| `ticket_histories.observation` | 17 |
| `ticket_histories.from_status_id` | 8 |

### 7.9 Hallazgos de calidad

| ID | Hallazgo | Acción recomendada |
|---|---|---|
| D-01 | Estados y tipos normalizados por 001; no hay valores fuera de catálogo | Mantener triggers y pruebas |
| D-02 | `abrizuela` en DB frente a `abrizzuela` canónico ausente | Reconciliar identidad/email/perfil antes del seeder; no renombrar a ciegas |
| D-03 | Cuenta `personal` fuera del catálogo canónico | Confirmar si es cuenta funcional o residuo |
| D-04 | 7 usuarios sin email | Definir si el email será obligatorio y editable |
| D-05 | 4 fechas no coinciden con el formato canónico por `T` | Normalizar al escribir o aceptar ISO 8601 de forma explícita |
| D-06 | Solo `profile` tiene FK física | Evaluar FKs para inventario/mantenimientos/tickets mediante reconstrucción |
| D-07 | 458 bienes sin mantenimiento | Validar si es esperado |
| D-08 | `code` es snapshot | Sincronizar o recalcular al consultar |
| D-09 | Inmutabilidad del informe en aplicación; borrado admin permitido | Documentar política de aprobación y retención |
| D-10 | Campos descriptivos con muchos vacíos | No inventar valores; conservar `NULL`/vacío con semántica explícita |

---

## 8. Datos auxiliares y backups

### 8.1 Contenido de `storage/`

| Ruta | Contenido | Tratamiento |
|---|---|---|
| `storage/db.db` | Base autoritativa | No versionar; proteger permisos y respaldar |
| `storage/*.csv` | Fuentes de importación | Auxiliares; se incluyen en el script de backup |
| `storage/cache/inventory-adscriptions-*.json` | Adscripciones/asignaciones institucionales en caché | Regenerables; escritura atómica con archivo `.lock` |
| `storage/cache/throttles/*.json` | Intentos fallidos de login | Hashes de usuario/IP + timestamps; ventana de 15 minutos |
| `storage/logs/php-error.log` | Errores y auditoría puntual | Rotación 5 MiB, hasta 5 archivos `.1`–`.5` |
| `storage/logs/backup.log` | Resultado del script de backup, si se ejecuta | No incluir secretos ni contenido de la base |
| `storage/result/` | Documentos generados, incluido PDF de documentación | Artefactos, no fuente autoritativa |
| `storage/backups/` | Copias manuales y destino automatizado configurable | Alto valor; acceso restringido |

La lectura local de caché usa `SYSTEC_LEGACY_CACHE_TTL` (por defecto 86 400 segundos). La consulta bajo demanda de adscripciones usa una ventana corta de 300 segundos y puede refrescar en segundo plano con FastCGI. Si la fuente institucional no está disponible, se usa la caché anterior o `properties.adscription` como respaldo.

El throttle permite 5 intentos por usuario y 15 por IP en 15 minutos. No debe confundirse con una tasa de peticiones global.

### 8.2 Script de backup

`bin/backup.sh`:

- Usa `SQLite3::backup()` para obtener un snapshot consistente en caliente.
- Verifica `PRAGMA integrity_check = ok`.
- Empaqueta `db.db`, los CSV presentes y un `manifest.json` con conteos.
- Cifra con AES-256-CBC/PBKDF2 cuando recibe `SYSTEC_BACKUP_KEY_FILE`; `--require-encryption` exige la clave.
- Publica de forma atómica y promueve copias semanales/mensuales.
- Aplica retención configurable de 30 diarias, 12 semanales y 12 mensuales.
- Por defecto escribe en `storage/backups/automated/{daily,weekly,monthly}`.

La política solicitada de respaldo diario a las 02:00 (`America/Caracas`), destino separado, cifrado y restore trimestral sigue pendiente de aprobación/operación. Ver [`BACKUP.md`](BACKUP.md). La existencia del script no implica que cron esté instalado.

---

## 9. Datos sensibles y retención

| Dato | Sensibilidad | Situación actual | Retención sugerida |
|---|---|---|---|
| Inventario y asignaciones | Media | Acceso autenticado; `storage/` denegado por router/htaccess | Mientras el bien exista |
| Historial, notas e informes | Media | Acceso autenticado; informes aprobados pretendidamente definitivos | Vida operativa + plazo administrativo |
| Email, nombres y avatar | Media/Alta (PII) | En `users`/`profile`; avatares son URLs externas | Mientras la cuenta/perfil exista |
| Hashes de contraseña | Alta | Nunca mostrar ni registrar | Mientras la cuenta exista |
| `storage/db.db` | Alta (agregado) | Dentro del árbol, sin cifrado en reposo | Según política institucional |
| Backups | Alta | Cifrado disponible en el script; método/custodia pendientes | Propuesta en `BACKUP.md` |
| Logs y throttles | Media | Pueden contener PII operativa o hashes de identificadores | Rotación y acceso restringido |

**Reglas transversales:**

- No asumir que `storage/` está fuera del `DocumentRoot`; verificar `router.php` o `.htaccess` en cada despliegue.
- No incluir contraseñas, hashes, claves ni datos personales innecesarios en logs.
- Cifrar backups antes de trasladarlos fuera del servidor.
- Documentar accesos y pruebas de restauración sin registrar secretos.

---

## 10. Pendientes de esquema y operación

| # | Pendiente | Impacto | Prioridad |
|---|---|---|---|
| 1 | Reconciliar `abrizuela`/`abrizzuela` y la cuenta `personal` | Seeder, login y continuidad de perfiles | P1 |
| 2 | Definir edición/obligatoriedad de `users.email` | Consistencia de cuentas | P2 |
| 3 | Evaluar FKs para bienes, mantenimientos, tickets e historial | Integridad relacional | P2 |
| 4 | Estandarizar fechas ISO al escribir | Consultas y reportes reproducibles | P2 |
| 5 | Bitácora relacional de auditoría | Trazabilidad administrativa | P2 |
| 6 | Política de borrado de informes aprobados | Inmutabilidad y retención documental | P2 |
| 7 | Adjuntos/fotos de mantenimientos y tickets | Evidencia operativa | P3 |
| 8 | Validar cobertura de mantenimiento de los 458 bienes sin registros | Calidad del inventario | P3 |

**Regla:** los cambios de esquema se documentan, prueban y aplican con migración y backup verificado; no se resuelven editando `storage/db.db` en caliente.

---

Ver también: `README.md`, `PROJECT.md`, `DOMAIN.md`, `ARCHITECTURE.md`, `API.md`, `SECURITY.md`, `BACKUP.md`, `TODO.md`, `grafo/modelo-datos.md`.
