# CONVENTIONS.md — SysTec GMB

> **Estado:** v1.1 (F1 implementada + reglas para cambios) · **Actualizado:** 2026-11-09.
> Consistencia para un equipo pequeño y agentes IA. Este documento se subordina a `DOMAIN.md` (idioma), `DATABASE.md` (persistencia), `API.md` (rutas) y `SECURITY.md` (controles).

---

## 1. Estructura de carpetas

La estructura actual es funcional, no MVC:

```
systec/
├── *.php                     # Rutas de primer nivel
├── conn.php                  # Capa de datos y catálogos
├── login.php / logout.php    # Autenticación
├── router.php / .htaccess    # Denegaciones del servidor
├── bin/create-user.php       # Usuarios por CLI
├── include/                  # config, security, auth, layout y helpers
├── dashboard/                # Módulo inventario
├── maintenances/             # Módulo mantenimientos
├── properties/               # Importación de inventario
├── historials/               # Vistas de historial
├── storage/                  # db.db, backups, CSV y logs (fuera del webroot)
├── vendor/                   # Dependencias vendorizadas
├── docs/                     # Documentación
└── grafo/                    # Grafo de lógica de negocio
```

**Regla para cambios:** mantén el patrón de carpetas por módulo (`dashboard/table`, `maintenances/form`, etc.). No introduzcas un framework ni un router sin un ADR que lo justifique. `router.php` existe solo para el servidor embebido; en producción se usa la configuración del servidor.

**Estructura objetivo recomendada (sin reescribir):**

```
systec/
├── public/                   # Docroot opcional para producción
│   ├── index.php
│   ├── dashboard.php
│   ├── maintenances.php
│   ├── historials.php
│   ├── report.php
│   └── assets/
├── src/
│   ├── Database.php          # Conexión + consultas parametrizadas
│   ├── Repositories/         # Properties, Maintenances
│   ├── Services/             # Reglas de negocio
│   ├── Validators/           # Alta, edición, transición
│   └── Support/              # escape, fechas, códigos
├── views/                    # Plantillas y parciales
├── storage/                  # db.db, backups, logs (ya implementado)
├── migrations/               # NNN_descripcion.sql
├── tests/                    # Unitarias e integración
└── docs/ · grafo/
```

F1 ya movió los datos a `storage/` y denegó el acceso directo. La migración a `public/` + `src/` sigue siendo una propuesta (ADR-014), no una obligación inmediata.

---

## 2. Nombres

| Elemento | Convención | Ejemplo |
|---|---|---|
| Archivos PHP de ruta | minúsculas, sin guiones | `dashboard.php`, `maintenances.php` |
| Carpetas | minúsculas, plural por módulo | `maintenances/`, `historials/` |
| Clases | PascalCase | `DB` |
| Métodos | camelCase, verbo | `getCodeId`, `indexWithMaintenances` |
| Funciones helper | snake_case en español | `badge_clase_bien`, `texto_tipo` |
| Variables | camelCase o snake_case según contexto | `$property_id`, `$maintenances` |
| Tablas | snake_case, plural | `properties`, `maintenances` |
| Columnas | snake_case | `next_maintenance_date`, `failure_reason` |
| Estados | minúsculas para mantenimiento; mayúsculas para bien | `finalizado`, `BUENO` |
| Documentos | MAYÚSCULAS en la raíz de `docs/`; kebab-case en anexos | `DOMAIN.md`, `matriz-trazabilidad.md` |

**Idioma:**

- Código y comentarios: español técnico (el sistema ya está en español).
- UI, mensajes y reportes: español de Venezuela.
- Términos de dominio: según `DOMAIN.md` (`bien`, `mantenimiento`, `técnico`, `vencido`).
- Sinónimos prohibidos: `artículo`, `activo`, `propiedad`, `item`, `servicio`, `reparación` (salvo correctivo), `bitácora`.

---

## 3. Estilo de código

### 3.1 PHP

- Mantener compatibilidad con PHP 7.4 (no usar `match`, `str_contains`, named arguments, enums ni tipos union).
- Usar `declare(strict_types=1)` solo en archivos nuevos que no dependan de coerciones legacy.
- Una página = una ruta; `router.php` solo enruta/deniega en el servidor embebido.
- Incluir siempre con `__DIR__`; no depender de `$_SERVER['DOCUMENT_ROOT']`.
- No cerrar la conexión dentro de métodos de `DB`; usar `db()` y dejar que el request termine.
- No usar `die()` para errores de negocio en código nuevo; responder con código HTTP y mensaje controlado.
- No concatenar SQL con valores; usar `prepare()`/`bindValue()` y listas blancas.
- Escapar toda salida con `e()` (que envuelve `htmlspecialchars`).
- Toda página de aplicación empieza con `require_login()` o `require_role([...])`.
- Toda mutación exige `POST` + `verify_csrf()`; los GET no cambian datos.
- Los formularios incluyen `csrf_field()`.
- Las importaciones se ejecutan por POST con confirmación y reporte por fila.

### 3.2 Fechas

- Almacenar en ISO 8601 (`Y-m-d H:i:s` o `Y-m-d`).
- Interpretar en `America/Caracas`.
- No hardcodear fechas en vistas.
- Para diferencias, calcular duración total, no solo `DateInterval->h`.

### 3.3 Dinero y números

- No hay manejo de dinero. Si se agrega, usar enteros o `decimal` y evitar `float`.
- IDs: validar con `filter_var($id, FILTER_VALIDATE_INT)`.

### 3.4 HTML y UI

- Bootstrap 5.3.2 como base.
- Badges con `sat_badge()` y helpers de `include/helpers.php`.
- Tablas con DataTables 1.13.7; inicializar con `new DataTable('#myTable', {...})`.
- Mantener el layout de `include/layout.php` y el patrón `ob_start()` / `$main` / `include layout`.
- Etiquetas y mensajes en español.
- No agregar dependencias nuevas sin vendorizarlas o documentar el CDN.

### 3.5 Comentarios

- Comentarios de fase (`Fase 2.1`, `Fase 2.2`) se conservan como historia; no agregar nuevos sin contexto.
- Comentar el "por qué", no el "qué".
- No dejar código comentado sin fecha ni motivo; si es deuda, registrar en `TODO.md`.

---

## 4. Patrones preferidos

| Patrón | Uso | Ejemplo |
|---|---|---|
| Página + layout | Renderizado | `dashboard.php` |
| Guardia de sesión/rol | Autorización | `require_login()`, `require_role()` |
| POST + CSRF | Mutaciones | `maintenances/register.php` |
| Parcial de tabla | Reutilización | `dashboard/table/index.php` |
| Parcial de formulario | Alta/edición | `maintenances/form/fields.php` |
| Helper puro de presentación | Escape/badges | `include/helpers.php` |
| Consulta parametrizada en `DB` | Acceso a datos | `index()`, `getFirstForId()` |
| Snapshot de código | Integridad histórica | `maintenances.code` |
| Redirección tras POST | PRG | `register.php` |

## 5. Patrones a evitar

| Antipatrón | Por qué | Alternativa |
|---|---|---|
| Concatenar SQL con `$_GET`/`$_POST` | Inyección | Prepared statements |
| Imprimir datos sin escape | XSS | `e()` / `htmlspecialchars` |
| Cambiar estado por GET | CSRF y acciones accidentales | POST + token |
| Cerrar la conexión dentro de `create()` | Rompe transacciones | Cerrar en el llamador |
| `die()` para validación | Mala UX y sin código | Vista de error / redirect con mensaje |
| Fechas hardcodeadas | Reportes incorrectos | `date()` / configuración |
| `rand()` para datos reales | Métrica falsa | Medición real o nulo |
| `LIKE '%texto%'` para reglas de negocio | Frágil | Campo booleano/estado |
| JOIN sin `DISTINCT` para reportes | Duplicados | `EXISTS` o `DISTINCT` |
| Importar al abrir una página | Efecto accidental | POST + confirmación |
| Duplicar lógica de vencimiento en JS y PHP | Divergencia | Una sola función en servidor |

---

## 6. Validación

- Validar en servidor siempre.
- Reglas mínimas:
  - `property_id` entero y existente.
  - `type` en `getListMaintenanceType()`.
  - `technician` en `getListMaintenanceTechnician()`.
  - `date` con formato válido y no futura (salvo mantenimiento programado).
  - `status` en el catálogo y transición permitida.
  - Longitudes máximas para textos.
- Unificar `validations/register.php` y `validations/update.php` en un validador con contexto (`create`/`update`).

---

## 7. Pruebas

No existen pruebas automatizadas. F1 ejecutó pruebas manuales documentadas. Mínimo recomendado antes de tocar reglas:

| Nivel | Herramienta | Qué cubrir |
|---|---|---|
| Sintaxis | `php -l` | Todos los archivos |
| Unitario | PHPUnit o script propio | `getCodeId`, vencimiento, diferencias de fecha |
| Integración | SQLite temporal | Importación, alta, edición, transición |
| Humo | Navegador / curl | Login, dashboard, historial, reporte, importación |
| Seguridad | curl + payloads | SQLi, XSS, CSRF, acceso directo a `storage/` |

**Regla:** ningún cambio en `conn.php`, autenticación, importadores o estados se considera terminado sin al menos una prueba manual documentada.

---

## 8. Documentación

- Todo cambio de ruta o parámetro actualiza `API.md`.
- Todo cambio de esquema actualiza `DATABASE.md` y `grafo/modelo-datos.md`.
- Toda regla nueva o cambiada actualiza `DOMAIN.md`.
- Toda decisión con alternativas reales crea un ADR en `DECISIONS.md`.
- Todo cambio de estado del backlog actualiza `TODO.md` y `CHANGELOG.md`.
- Los documentos de la raíz de `docs/` llevan cabecera `Estado / Actualizado / Dueño / Fuentes`.

---

## 9. Commits y ramas

Aunque el directorio no es un repositorio git, la convención recomendada es:

- Conventional Commits: `feat(inventario): ...`, `fix(mantenimientos): ...`, `docs: ...`, `test: ...`, `chore: ...`.
- Ramas: `main` protegida, `feat/*`, `fix/*`.
- Un cambio = un commit pequeño con su actualización de docs.
- Nunca commitear `storage/db.db`, backups ni CSV con datos reales.

---

## 10. Hallazgos de auditoría que originaron convenciones

| Fecha | Hallazgo | Convención resultante |
|---|---|---|
| 2026-11-09 | SQL concatenado en `DB` | Prohibir concatenación; prepared statements |
| 2026-11-09 | Salida sin escape en múltiples vistas | Helper `e()` obligatorio |
| 2026-11-09 | `update.php` valida con `register.php` | Un validador por contexto |
| 2026-11-09 | `time_taken` mal calculado | Duración total, no componente horario |
| 2026-11-09 | Importación al abrir página | POST + confirmación + reporte |
| 2026-11-09 | Datos legacy inconsistentes | Normalizar en migración, no en presentación |

---

Ver también: `README.md`, `DOMAIN.md`, `DATABASE.md`, `API.md`, `SECURITY.md`, `DECISIONS.md`, `TODO.md`.
