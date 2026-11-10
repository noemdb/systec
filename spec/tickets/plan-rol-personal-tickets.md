# Plan de ingeniería: rol `personal` y gestión de tickets

> **Estado:** propuesta para revisión; no implementada.
> **Fecha:** 2026-11-09.
> **Alcance:** incorporar el rol `personal` con una experiencia separada para registrar y seguir tickets de soporte relacionados con bienes.
> **Decisión requerida antes de construir:** aprobar las reglas de visibilidad y quién puede ejecutar/registrar acciones de soporte (ver §4).

## 1. Resumen ejecutivo

Agregar `personal` como rol de primer nivel, no como una variante visual del dashboard actual. Al iniciar sesión, este rol debe llegar a una bandeja de tickets y no poder navegar ni operar las pantallas de inventario y mantenimiento. Los roles existentes conservan su flujo.

El módulo se implementará como una unidad PHP bajo `tickets/`, siguiendo el patrón actual de páginas y acciones, SQLite y migraciones numeradas. `tickets` representa el estado vigente; `ticket_histories` es el registro append-only de solicitudes y acciones; `ticket_status` es el catálogo controlado de estados. Las mutaciones requieren POST, CSRF, autorización de rol, validación de transición y transacción.

## 2. Contexto confirmado

- Aplicación monolítica PHP 7.4 sin framework; las rutas son scripts PHP y el idioma de UI es español de Venezuela.
- SQLite vive en `storage/db.db`. Los cambios de esquema van en `migrations/NNN_descripcion.sql` y se aplican con `php bin/migrate.php --backup=...` tras verificar integridad y conteos del backup.
- `users.role` es texto sin catálogo SQL. `include/auth.php` ofrece `require_login()`, `require_role()` y CSRF; la sesión guarda `id`, `username` y `role`.
- `bin/create-user.php` acepta los roles publicados por `DB::getListRoles()`. `DB::$schema` también mantiene una lista permitida de tablas/columnas.
- El login y `auth_intended_url()` actualmente aterrizan en `dashboard.php`; el módulo actual usa layout compartido. El ticket necesita un punto de entrada y navegación propios.
- Convenciones vigentes: GET de lectura, POST para mutaciones, SQL parametrizado, `e()` para salida, relaciones SQLite habilitadas pero sin claves foráneas declaradas en las tablas actuales.

## 3. Objetivos y no objetivos

### Objetivos

- Crear usuarios con rol `personal` desde el mecanismo administrativo existente.
- Dar a `personal` una interfaz operativa propia para crear tickets, consultar su estado y ver la actividad de soporte permitida por la política aprobada.
- Relacionar cada ticket con un bien existente y conservar una historia atribuible, cronológica y no editable desde la interfaz.
- Impedir accesos cruzados entre el área de tickets y la administración de inventario mediante controles en servidor, no solo ocultando enlaces.
- Mantener compatibilidad con los roles y flujos existentes y poder desplegar el cambio con backup y migración reversible operativamente.

### Fuera de alcance de esta entrega

- Notificaciones por correo, SLA/calendario laboral, adjuntos, API pública, aplicación móvil o integración con proveedores.
- Reasignación automática, colas, prioridades configurables o métricas de soporte hasta que exista una política aprobada.
- Reemplazar el módulo de mantenimientos existente o migrar su historial al modelo de tickets.

## 4. Decisiones de producto bloqueantes

Resolver y registrar estas decisiones antes de congelar el contrato de autorización y las consultas de la bandeja:

| ID | Pregunta | Recomendación inicial | Consecuencia |
|---|---|---|---|
| D-01 | ¿`personal` solicita soporte o también es el equipo que ejecuta soporte? | Tratar `personal` como solicitante. Mantener las acciones de soporte para `tecnico` y/o `admin`, con actores identificados. | Define permisos de creación, comentarios, transición y atribución. No asumir que personal es técnico. |
| D-02 | ¿Qué tickets puede ver `personal`? | Solo los creados por su usuario; administradores/soporte ven el alcance operativo aprobado. | Si la visibilidad cambia, hace falta ámbito organizativo/departamental que hoy no está modelado. |
| D-03 | ¿Quién puede consultar todos los tickets y reasignarlos? | `admin` como operador global; confirmar si `tecnico` también ve/asume todos. | Define autorización por fila y futuras asignaciones. |
| D-04 | ¿El bien es obligatorio al registrar? | Sí, dado que el ticket se define ligado a `bienId`; exigir selección de un bien existente. | Tickets sin bien requerirían una relación opcional y un flujo distinto. |
| D-05 | ¿`con retraso` se asigna manualmente o se deriva de vencimiento/SLA? | Mantenerlo manual en v1; no inventar SLA. | Si es automático, se necesitan fecha objetivo, política horaria y proceso que actualice estado. |
| D-06 | ¿Cuál es la transición permitida entre estados y quién puede ejecutarla? | Aprobar matriz antes de implementar; ver propuesta base en §6. | La misma matriz debe existir en la aplicación, pruebas y restricciones de SQLite. |
| D-07 | ¿Se pueden editar/cancelar tickets? | No borrar; correcciones y cancelaciones se registran como eventos. | Evita pérdida de trazabilidad. Confirmar si se requiere estado `cancelado`. |

**Gate:** no iniciar implementación de autorización ni transiciones hasta cerrar D-01, D-02 y D-06. Las demás pueden cerrarse en refinamiento siempre que no amplíen el alcance.

## 5. Diseño propuesto

### 5.1 Experiencia y navegación

- Definir `/tickets.php` como entrada de bandeja y `tickets/` como carpeta del módulo, en línea con `maintenances.php` y `maintenances/`.
- Después del login, elegir destino según el rol: `personal` → `/tickets.php`; roles actuales → destino pretendido validado o `/dashboard.php`. Si existe URL pretendida, validar que el usuario tenga permiso para esa ruta antes de redirigir.
- Las páginas de personal usan navegación y acciones propias centradas en tickets. No renderizar el menú de inventario en esa experiencia. El layout puede reutilizar componentes neutrales existentes solo si no mezcla navegación ni permisos.
- Implementar vistas iniciales: bandeja con filtros por estado, alta de ticket, detalle cronológico y vista operativa de soporte según permisos aprobados. En pantallas pequeñas, priorizar búsqueda, estado y última actividad.
- Mostrar en cada ticket el identificador, bien (código y descripción), resumen, estado, fechas y última actividad. El detalle muestra la historia completa permitida.

### 5.2 Autorización

Centralizar comprobaciones reutilizables en `include/auth.php` o helpers próximos; cada página y acción valida permisos en servidor. La matriz final depende de §4. Como base:

| Capacidad | `personal` | `tecnico` | `admin` | otros roles |
|---|---:|---:|---:|---:|
| Entrar a la interfaz de tickets | Sí | Según decisión | Sí | No, salvo decisión explícita |
| Crear ticket como solicitante | Sí | Según decisión | Según necesidad | No |
| Leer tickets propios | Sí | Según alcance operativo | Sí | No |
| Registrar acción de soporte/cambiar estado | No | Según decisión | Sí | No |
| Acceder a dashboard de inventario | No | Flujo actual | Flujo actual | Flujo actual |

Las reglas de visibilidad deben aplicarse en las consultas (por ejemplo, `created_by = :user_id`), no cargando registros globales para filtrarlos en PHP. Toda petición directa no autorizada debe responder 403; ocultar enlaces no constituye autorización.

### 5.3 Esquema lógico

Usar nombres `snake_case` consistentes con el repositorio. Normalizar el typo `descripciotn` del requerimiento a `description`.

```sql
ticket_status (
    id INTEGER PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    is_terminal INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1
)

tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    status_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    observation TEXT,
    created_by INTEGER NOT NULL,
    assigned_to INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at TEXT
)

ticket_histories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INTEGER NOT NULL,
    actor_user_id INTEGER NOT NULL,
    action_type TEXT NOT NULL,
    description TEXT NOT NULL,
    observation TEXT,
    from_status_id INTEGER,
    to_status_id INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
```

Notas de diseño:

- `property_id` reemplaza `bienId`; el FK lógico apunta a `properties.id`. `created_by` conserva el solicitante; `assigned_to` es opcional y solo se usa si se aprueba asignación.
- `title` separa el resumen de `description`; `observation` es una nota complementaria, no un sustituto de la historia.
- Los estados iniciales del catálogo deben ser estables y únicos: `iniciado`, `en_progreso`, `con_retraso`, `en_espera`, `finalizado`. Usar `code` para lógica y `name` para etiqueta UI.
- Una historia se inserta en cada creación, cambio de estado y acción de soporte. Registrar actor, instante y estados anterior/nuevo cuando aplique. La historia no se actualiza ni elimina desde la aplicación.
- Declarar claves foráneas y mantener `PRAGMA foreign_keys = ON`; añadir restricciones `NOT NULL`, `CHECK` e índices de acuerdo con los campos y consultas definitivos. Si una migración no puede introducir FKs con seguridad sobre datos existentes, documentar la comprobación equivalente y su deuda explícita.
- Índices mínimos a validar con consultas reales: `tickets(status_id, updated_at)`, `tickets(created_by, updated_at)`, `tickets(property_id, created_at)` y `ticket_histories(ticket_id, created_at, id)`.
- No guardar PII innecesaria ni duplicar datos mutables del bien en el ticket. Mostrar datos actuales del bien mediante lectura relacionada.

### 5.4 Transiciones iniciales a validar

Propuesta para conversación de producto, no regla aprobada:

| Estado actual | Siguiente estado propuesto |
|---|---|
| `iniciado` | `en_progreso`, `en_espera`, `con_retraso` |
| `en_progreso` | `en_espera`, `con_retraso`, `finalizado` |
| `con_retraso` | `en_progreso`, `en_espera`, `finalizado` |
| `en_espera` | `en_progreso`, `con_retraso`, `finalizado` |
| `finalizado` | Ninguno; reapertura como nueva acción explícita si se aprueba |

La creación genera `iniciado`. Cada cambio de estado debe insertar la fila de historia y actualizar `tickets` en una única transacción; si falla cualquiera, no persiste ninguno. La matriz aprobada se valida con un helper puro y triggers SQLite para impedir escrituras inválidas fuera de la UI.

### 5.5 Contrato HTTP inicial

Mantener páginas HTML y PRG; no crear una API JSON para esta entrega.

| Método / ruta | Uso | Permiso |
|---|---|---|
| `GET /tickets.php` | Bandeja | Personal: ámbito permitido; soporte/admin: alcance aprobado |
| `GET /tickets/register.php` | Formulario de alta | Personal según D-01 |
| `POST /tickets/actions/register.php` | Crear ticket + primer evento | Rol autorizado + CSRF |
| `GET /tickets/detail.php?id={id}` | Detalle e historia | Autorización por ticket |
| `POST /tickets/actions/history.php` | Agregar acción/observación | Rol autorizado + CSRF |
| `POST /tickets/actions/status.php` | Cambiar estado + evento | Rol autorizado + CSRF + transición permitida |

Validar identificadores enteros, existencia del bien/ticket, longitud y contenido de texto; responder con códigos HTTP coherentes con `docs/API.md`. Escapar todo texto mostrado con `e()`.

## 6. Migración y compatibilidad

1. Añadir una migración siguiente a `002` que cree catálogo, tablas, índices, restricciones, triggers y los cinco estados iniciales. No modificar migraciones ya aplicadas.
2. Revisar el flujo de `bin/migrate.php`: preparar backup verificable antes de cualquier migración; probar la migración en una copia de DB y comprobar integridad, conteos legacy y filas de catálogo.
3. Ampliar el catálogo `DB::getListRoles()` con `personal` y el allowlist de tablas/columnas de `DB` con las nuevas tablas/campos; no hacer depender las nuevas consultas de helpers que no admitan JOIN.
4. Actualizar creación de usuario, documentación de roles, superficie HTTP, base de datos, seguridad, dominio y trazabilidad. No cambiar roles existentes ni recalificar cuentas.
5. Si hay que retirar el módulo, deshabilitar primero sus rutas/rol; restaurar backup solo con aprobación y ventana operativa. SQLite no ofrece rollback automático de datos confiable para esta migración.

## 7. Plan por fases y gates

### F0 — Cierre de reglas de negocio

- Resolver D-01…D-07 y escribir la matriz aprobada de permisos/estados.
- Definir campos obligatorios, límite de longitud, uso de observación, cancelación, asignación y política de visibilidad.
- **Gate:** responsable de negocio valida ejemplos de creación, seguimiento, acceso ajeno y cierre.

### F1 — Persistencia y permisos

- Implementar migración de catálogo/tablas/índices/triggers; extender allowlists y catálogo de roles.
- Ajustar alta de usuarios y landing por rol; añadir guardias explícitas para la separación de áreas.
- **Gate:** migración repetible, integridad SQLite `ok`, roles existentes pasan smoke tests y `personal` no puede abrir rutas legacy protegidas.

### F2 — Flujos funcionales

- Implementar alta y bandeja del solicitante, detalle cronológico y acciones de soporte según matriz.
- Guardar creación, historia, cambio de estado y actualización del ticket atómicamente.
- Añadir layout/navegación propios para personal y vistas responsive de lista/detalle.
- **Gate:** recorrido end-to-end con usuarios de cada rol y registros no autorizados bloqueados por el servidor.

### F3 — Endurecimiento y despliegue gradual

- Completar pruebas de autorización, CSRF, XSS, transiciones, concurrencia básica y migración con DB temporal.
- Probar en copia/entorno de aceptación; crear primer usuario personal por CLI y validar inicio/cierre de sesión.
- Desplegar con backup comprobado, habilitar primero a un grupo piloto y revisar logs/feedback antes de ampliarlo.
- **Gate:** checklist de salida de §9 aprobado; operador conoce restauración y procedimiento ante fallo.

## 8. Estrategia de pruebas

- **Unidad:** normalización/validación del ticket, catálogo de estados y matriz de transición; rechazar estado desconocido y transición no permitida.
- **Persistencia:** alta requiere bien válido; estado inicial correcto; historia vinculada al actor; rollback de ticket si falla inserción de historia; FK/triggers rechazan referencias o transiciones inválidas.
- **Autorización:** `personal` puede crear/leer su ámbito aprobado, no accede a otro ticket fuera de ámbito ni a dashboard/acciones legacy; `tecnico` y `admin` respetan matriz; `consulta` y `operador` conservan comportamiento actual.
- **HTTP/seguridad:** POST solamente para mutaciones, token CSRF ausente/inválido rechazado, payload HTML se escapa, SQL usa parámetros, ID inexistente responde 404 sin revelar datos.
- **Regresión:** login y destino por rol; navegación y páginas existentes para los cuatro roles; usuario inactivo/rol inválido según controles vigentes.
- **Migración:** copia de base, backup con integridad y conteos, aplicación de migración, segunda ejecución sin cambios, rollback transaccional ante fallo.
- **UI:** escritorio y móvil; sin menú cruzado, textos largos manejados, estados identificables por texto y no solo color, foco/labels accesibles.

## 9. Definition of Done

- Matriz de roles, visibilidad y transiciones aprobada y reflejada en código y documentación.
- Migración versionada probada contra copia; backup verificado y plan de recuperación documentado.
- Un usuario `personal` llega a su experiencia de tickets; no puede alcanzar la interfaz de inventario por navegación ni URL directa.
- Cada ticket está ligado a un bien válido, tiene creador y fechas; las acciones y cambios de estado quedan atribuidos en historia.
- Mutaciones protegidas por autorización, POST, CSRF, validación, consultas parametrizadas y transacción; la historia no admite edición/borrado de aplicación.
- Pruebas automatizadas y smoke tests de regresión pasan; `docs/API.md`, `DATABASE.md`, `SECURITY.md`, `DOMAIN.md`, `TODO.md` y grafo se actualizan donde corresponda.
- Procedimiento de creación de usuarios, despliegue y soporte piloto probado.

## 10. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| El rol nuevo hereda por accidente permisos amplios del dashboard | Denegar por defecto; matriz explícita y tests de acceso directo por ruta/acción. |
| Personal ve tickets ajenos | Filtrado en consulta por identidad/ámbito y pruebas de IDOR con dos usuarios. |
| Se pierde trazabilidad entre cambio vigente e historia | Actualización e inserción de historia en una sola transacción; triggers y prueba de rollback. |
| `con retraso` se interpreta como SLA aunque no hay calendario | Mantenerlo manual hasta aprobación de plazo, horario y automatización. |
| Estados duplicados o transición inválida desde SQL | Catálogo seed estable, claves únicas, FK y triggers; lógica duplicada cubierta por pruebas. |
| Migración afecta DB operativa o copia incompleta | Runner con backup, prueba sobre copia y verificación previa/posterior. |
| SQLite limita escrituras simultáneas | Transacciones cortas, orden de escritura consistente y manejo controlado de `SQLITE_BUSY`; medir antes de evaluar otro motor. |

## 11. Referencias de implementación

- Autenticación/autorización: `include/auth.php`, `login.php`, `bin/create-user.php`.
- Persistencia/listas blancas: `conn.php`; migraciones: `migrations/` y `bin/migrate.php`.
- Contratos y convenciones: `docs/API.md`, `docs/DATABASE.md`, `docs/SECURITY.md`, `docs/CONVENTIONS.md`, `docs/DOMAIN.md`.
- Pruebas actuales: `tests/Unit/`, `tests/bootstrap.php`, `phpunit.xml.dist`.