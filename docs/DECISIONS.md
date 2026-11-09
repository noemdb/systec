# DECISIONS.md — SysTec GMB

> **Estado:** v1.1 (F2) · **Actualizado:** 2026-11-09.
> Registro de decisiones de arquitectura (ADR). No se edita ni se borra una entrada: un cambio de rumbo crea una nueva entrada que referencia la anterior.
>
> **Naturaleza:** la mayoría son **reconstruidas** a partir del código (decisiones de facto, no necesariamente deliberadas). Las propuestas se marcan como tales y no deben implementarse sin aprobación del dueño.
>
> Workflow: `Reconstruida` → `Aceptada` (si el dueño la ratifica) → `Reemplazada por ADR-NNN`. `Propuesta` → `Aceptada` / `Rechazada`.

---

## ADR-001 — Monolito PHP + SQLite sin framework
**Fecha:** 2026-11-09 (reconstruida)
**Estado:** Reconstruida / de facto

### Contexto
La institución necesita un sistema interno de inventario y mantenimientos que funcione en un servidor PHP básico, sin administración de base de datos ni dependencias externas.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| PHP procedural + SQLite (elegida de facto) | Cero configuración, archivo único, hosting ubicuo | Sin separación de capas, concurrencia limitada, seguridad manual |
| Laravel + MySQL/PostgreSQL | Estructura, ORM, migraciones, auth | Requiere Composer, servidor de BD, más infraestructura |
| Node/Next.js + PostgreSQL | Moderno, tipado | Fuera del perfil y la infraestructura disponibles |
| Access/Excel | Cero desarrollo | Sin concurrencia, sin web, sin control |

### Decisión
Monolito PHP con SQLite, servido por PHP built-in server en desarrollo y por Apache/PHP en producción.

### Consecuencias
- **Gana:** despliegue simple, cero dependencias, costo mínimo.
- **Sacrifica:** separación de capas, pruebas, seguridad por defecto y evolución controlada.
- **Deuda:** H-01, H-02, H-03, H-11, H-12, H-13.

---

## ADR-002 — Renderizado en servidor con layout por output buffering
**Fecha:** 2026-11-09 (reconstruida)
**Estado:** Reconstruida

### Contexto
Se necesita un layout común (head, header, footer, scripts) sin motor de plantillas ni framework.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| `ob_start()` + `$main` + `include layout.php` | Simple, sin dependencias, flexible | Acopla página y layout; difícil de testear |
| Motor de plantillas (Twig/Blade) | Separación, herencia | Dependencia y curva de aprendizaje |
| Funciones de render | Reutilizable | Más refactor del existente |

### Decisión
Cada página captura su HTML en `$main` y luego incluye `include/layout.php`. El layout acepta `$title`, `$no_footer`, `$header_file` y `$main_class`.

### Consecuencias
- **Gana:** consistencia visual y cero dependencias.
- **Sacrifica:** testabilidad y separación de responsabilidades.
- **Regla:** mantener el patrón hasta que un ADR lo reemplace.

---

## ADR-003 — Bootstrap 5.3 + DataTables 1.13 vendorizados
**Fecha:** 2026-11-09 (reconstruida)
**Estado:** Reconstruida

### Contexto
Se necesitan componentes UI y tablas con búsqueda, orden y paginación en cliente, sin build step.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Bootstrap + DataTables vendorizados | Sin build, funciona offline, conocido | 17 MB de vendor, versiones manuales |
| Tailwind + componente propio | Moderno, liviano | Requiere build y más código |
| CDN | Menos peso en repo | Depende de internet, riesgo de disponibilidad |

### Decisión
Vendorizar Bootstrap 5.3.2, DataTables 1.13.7 y jQuery 3.7.0 en `vendor/`.

### Consecuencias
- **Gana:** funciona sin red y sin build.
- **Sacrifica:** actualizaciones manuales y peso del repositorio.
- **Deuda:** `include/es-ES.json` no se usa (H-14).

---

## ADR-004 — Código de bien compuesto `grupo-subgrupo-seccion-ident`
**Fecha:** 2026-11-09 (reconstruida)
**Estado:** Reconstruida

### Contexto
La institución identifica cada bien con un código jerárquico legible, no con un UUID.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Código compuesto con ceros | Legible, compatible con CSV legacy, ordenable | Padding inconsistente, no único por BD |
| UUID | Único, estable | Ilegible para el operador |
| Código secuencial simple | Simple | Pierde jerarquía grupo/subgrupo/sección |

### Decisión
`getCodeId()` compone `grupo-subgrupo-seccion-ident` con `str_pad` a 4 dígitos. La importación rellena `subgrupo` a 4 y `seccion` a 3.

### Consecuencias
- **Gana:** compatibilidad con datos y reportes existentes.
- **Sacrifica:** consistencia (3 vs 4 dígitos) y unicidad garantizada.
- **Deuda:** unificar padding y agregar `UNIQUE(grupo, subgrupo, seccion, ident)`.

---

## ADR-005 — CSV como canal de importación masiva
**Fecha:** 2026-11-09 (reconstruida)
**Estado:** Reconstruida

### Contexto
El inventario y el histórico viven en CSV legacy; se necesita cargarlos sin reescribir todo a mano.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Importación directa al abrir la página | Rápida de implementar | Efectos accidentales, sin confirmación, sin reporte |
| Importación con staging y confirmación | Trazable, segura | Más desarrollo |
| Carga manual | Control | Inviable para cientos de filas |

### Decisión
Importar directamente desde `updates.php` (properties) e `imports.php` (maintenances) al abrir la página.

### Consecuencias
- **Gana:** velocidad de implementación.
- **Sacrifica:** seguridad, idempotencia y trazabilidad.
- **Deuda:** H-15. Propuesta: mover a POST con confirmación, transacción y reporte de filas.

---

## ADR-006 — Mantenimiento con estados `iniciado`, `revision`, `finalizado`
**Fecha:** 2026-11-09 (reconstruida)
**Estado:** Reconstruida

### Contexto
Se necesita reflejar el avance de un mantenimiento desde su registro hasta su cierre.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Tres estados simples | Fácil de entender y mostrar | Sin guardia, sin historial de transiciones |
| Máquina de estados formal | Consistente, auditable | Más código y modelo |
| Solo abierto/cerrado | Mínimo | Pierde la etapa de revisión |

### Decisión
Tres estados con transiciones sugeridas por la UI, sin validación en servidor.

### Consecuencias
- **Gana:** simplicidad operativa.
- **Sacrifica:** integridad; cualquier estado puede persistirse.
- **Deuda:** H-06. Propuesta: guardia en servidor + `CHECK` + auditoría de transiciones.

---

## ADR-007 — Cambio de estado del bien por botón cíclico
**Fecha:** 2026-11-09 (reconstruida)
**Estado:** Reconstruida / cuestionada

### Contexto
El operador necesita actualizar rápidamente la condición física del bien desde el dashboard.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Botón cíclico | Un clic, sin formulario | Ciclo no lineal, sin confirmación, sin motivo |
| Selector explícito | Claro y controlado | Un paso más |
| Derivar de mantenimientos | Consistente | Requiere reglas y puede sorprender |

### Decisión
Botón **Estado** que aplica `BUENO → MALO`, `MALO → REGULAR`, `REGULAR → BUENO`, y cualquier otro → `BUENO`.

### Consecuencias
- **Gana:** rapidez.
- **Sacrifica:** claridad y trazabilidad; `MUY MALO`/`MUY BUENO` se pierden.
- **Propuesta:** reemplazar por selector con motivo y auditoría (ADR-013).

---

## ADR-008 — Reportes imprimibles desde datos vivos
**Fecha:** 2026-11-09 (reconstruida)
**Estado:** Reconstruida

### Contexto
Se necesitan historiales y reportes de dañados para imprimir o guardar como PDF desde el navegador.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| HTML imprimible desde datos vivos | Simple, sin dependencias | No reproducible, cambia si cambian los datos |
| Snapshot + PDF en servidor | Reproducible, firmable | Requiere almacenamiento y generación |
| Exportar a Excel | Editable | No es un reporte formal |

### Decisión
Generar HTML en el momento y usar el diálogo de impresión del navegador.

### Consecuencias
- **Gana:** cero infraestructura.
- **Sacrifica:** reproducibilidad y evidencia histórica.
- **Propuesta:** snapshot de reporte + hash si se requiere auditoría (ADR-013).

---

## ADR-009 — Sin autenticación ni autorización
**Fecha:** 2026-11-09 (reconstruida) · **Actualizada:** 2026-11-09 (F1)
**Estado:** Reemplazada por ADR-015 (login + roles)

### Contexto
El sistema se pensó para una red interna y un equipo pequeño; se priorizó la funcionalidad.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Sin auth (actual) | Cero fricción | Cualquiera puede leer y mutar; no apto para exponer |
| Login simple + roles | Control básico | Requiere usuarios, sesiones y UI |
| SSO institucional | Integración | Depende de infraestructura |

### Decisión
~~No implementar autenticación en la versión actual.~~ Reemplazada: F1 implementó login, sesión, roles y CSRF (ADR-015).

### Consecuencias
- **Gana:** simplicidad (histórico).
- **Sacrifica:** confidencialidad, integridad y responsabilidad (histórico).
- **Resuelto:** `include/auth.php`, `login.php`, `logout.php`, `bin/create-user.php`.

---

## ADR-010 — Sin migraciones ni versionado de esquema
**Fecha:** 2026-11-09 (reconstruida)
**Estado:** Deuda / Propuesta

### Contexto
El esquema es pequeño y estable; se creó con `CREATE TABLE IF NOT EXISTS`.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Esquema en código (actual) | Cero herramientas | Sin evolución controlada, sin rollback |
| Migraciones SQL versionadas | Trazable, reproducible | Disciplina y herramienta mínima |
| ORM con migraciones | Automatiza | Dependencia y complejidad |

### Decisión
Mantener el esquema en `conn.php` sin migraciones.

### Consecuencias
- **Gana:** simplicidad inicial.
- **Sacrifica:** evolución segura.
- **Propuesta:** ADR-014, `migrations/` + `schema_migrations`.

---

## ADR-011 — `time_taken` en horas
**Fecha:** 2026-11-09 (reconstruida) · **Actualizada:** 2026-11-09 (F1)
**Estado:** Aceptada / corregida en F1

### Contexto
Se quería registrar cuánto tiempo tomó un mantenimiento.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Horas enteras | Simple | Pierde precisión; el cálculo actual es incorrecto |
| Minutos | Preciso | Cambia UI y datos |
| Días/horas | Legible | Más complejo |

### Decisión
Guardar `time_taken` como entero de horas totales, calculado con `abs(timestamp diff) / 3600` y redondeo.

### Consecuencias
- **Gana:** campo simple y duración correcta para más de 24 h.
- **Sacrifica:** precisión sub-hora (se redondea).
- **Resuelto:** H-05; pendiente decidir si se migra a minutos (B-04).

---

## ADR-012 — Conservar datos legacy sin normalizar
**Fecha:** 2026-11-09 (reconstruida)
**Estado:** Reconstruida / deuda

### Contexto
Los CSV y la base contienen valores heterogéneos (`FINALIZADO`, `evaluativo`, `MUY BUENO`, `ndomiguez`) que se querían conservar tal cual.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Conservar y normalizar en presentación | No altera evidencia | La presentación queda incompleta; reglas frágiles |
| Normalizar en migración | Datos consistentes | Requiere decisión y backup |
| Rechazar datos no canónicos | Calidad | Pérdida de histórico |

### Decisión
Conservar los valores legacy y mapear solo algunos en `include/helpers.php`.

### Consecuencias
- **Gana:** no se pierde información.
- **Sacrifica:** consistencia y reportes (H-06, H-07, D-01…D-04).
- **Propuesta:** normalizar con migración y mantener una tabla de equivalencias si se necesita trazabilidad.

---

## ADR-013 — Endurecimiento progresivo vs reescritura
**Fecha:** 2026-11-09
**Estado:** Propuesta

### Contexto
El sistema tiene deudas críticas de seguridad y calidad. Se puede reescribir o endurecer por fases.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Endurecer por fases (recomendada) | Bajo riesgo, valor temprano, conserva datos | Convive con deuda un tiempo |
| Reescritura completa | Arquitectura limpia | Alto costo, riesgo de pérdida de reglas implícitas |
| No hacer nada | Cero costo | Riesgo de incidente y pérdida de datos |

### Decisión propuesta
Fase P0: auth, SQL parametrizado, escape, mover `db.db` fuera del webroot. Fase P1: estados, importación, migraciones, pruebas. Fase P2: auditoría, snapshots, API.

### Consecuencias
- **Gana:** reduce riesgo sin detener la operación.
- **Sacrifica:** velocidad de modernización.
- **Criterio de aceptación:** checklist P0 de `SECURITY.md` completo antes de exponer el sistema.

---

## ADR-014 — Separar capa de datos y adoptar migraciones
**Fecha:** 2026-11-09
**Estado:** Propuesta

### Contexto
`conn.php` mezcla conexión, SQL, catálogos y reglas; el esquema no tiene versión. Esto impide pruebas y evolución segura.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Refactor incremental a repositorios + PDO | Testeable, parametrizado, bajo riesgo | Requiere tocar todas las páginas |
| Mantener `SQLite3` y solo parametrizar | Menor cambio | Persiste acoplamiento |
| ORM completo | Productividad | Dependencia y reescritura |

### Decisión propuesta
1. Crear `src/Database.php` con PDO/SQLite y consultas parametrizadas.
2. Extraer repositorios `PropertiesRepository` y `MaintenancesRepository`.
3. Mover reglas a servicios (`CodeService`, `MaintenanceService`, `ReportService`).
4. Introducir `migrations/` y `schema_migrations`.
5. Mantener la UI y las rutas actuales durante la transición.

### Consecuencias
- **Gana:** seguridad, pruebas y evolución.
- **Sacrifica:** tiempo de refactor y cambios en todas las páginas.
- **Dependencia:** ADR-013 aceptado y backup verificado.

---

## ADR-015 — Contención F1: sesión, roles, CSRF y datos en `storage/`
**Fecha:** 2026-11-09
**Estado:** Aceptada / implementada

### Contexto
El sistema no tenía autenticación, CSRF, escape sistemático ni SQL parametrizado, y la base/CSV vivían bajo el webroot. Se necesitaba contener el riesgo sin reescribir la aplicación ni introducir un framework.

### Alternativas consideradas
| Opción | Pros | Contras |
|---|---|---|
| Endurecer in situ (elegida) | Bajo riesgo, conserva rutas y datos | Persiste el monolito |
| Reestructurar a `public/` + `src/` | Separación limpia | Cambia todas las rutas y el despliegue |
| Framework con auth/ORM | Ecosistema | Dependencias, costo y reescritura |

### Decisión
1. Autenticación con sesión PHP, `password_hash`/`password_verify` y roles `admin`, `operador`, `tecnico`, `consulta`.
2. CSRF por sesión y mutaciones solo por POST.
3. SQL parametrizado con listas blancas de tabla/columna.
4. Escape centralizado con `e()`/`js()`.
5. Datos en `storage/` (configurable con `SYSTEC_STORAGE`/`SYSTEC_DB`) y denegación por `router.php`/`.htaccess`.
6. Cabeceras de seguridad, errores ocultos y log en `storage/logs/`.

### Consecuencias
- **Gana:** el sistema deja de ser anónimo y de exponer la base; las rutas actuales se conservan.
- **Sacrifica:** `storage/` sigue dentro del árbol del proyecto (aunque denegado); la separación `public/`/`src/` queda para ADR-014.
- **Pendiente:** HTTPS/HSTS, rate limiting, auditoría y pruebas automatizadas.
- **Evidencia:** `php -l`, pruebas de humo y mutaciones documentadas en `CHANGELOG.md`.

---

## ADR-016 — Normalización aprobada de datos legacy
**Fecha:** 2026-11-09
**Estado:** Aceptada e implementada en F2

### Contexto
Los estados, tipos, técnicos y fechas tenían variantes legacy. La normalización podía alterar la interpretación de datos, por lo que requería aprobación del dueño y un backup restaurable.

### Decisión
1. Los estados de bienes válidos son `BUENO`, `REGULAR`, `MALO`, `MUY MALO`; los dos registros `MUY BUENO` se mapearon a `BUENO`.
2. `evaluativo` es un tipo válido; `ndomiguez` se corrige a `ndominguez`.
3. El estado canónico de mantenimiento es minúscula; fechas válidas se normalizan a formato ISO.
4. `time_taken` se mide en horas totales. Se conservan valores históricos y nulos porque no existen fechas de inicio/fin para reconstruirlos.
5. La importación de inventario actualiza solo el estado de bienes existentes.

### Consecuencias
- **Gana:** datos consistentes y validación de catálogos en SQLite.
- **Sacrifica:** se pierde la grafía previa de las variantes corregidas; el backup previo conserva el estado anterior.
- **Evidencia:** `migrations/001_normalize_legacy_data.sql` y `storage/backups/2026-11-09/db-before-f2-normalization.db`.

---

## ADR-017 — Migraciones SQLite versionadas
**Fecha:** 2026-11-09
**Estado:** Aceptada e implementada en F2

### Contexto
El esquema y los datos necesitaban cambios reversibles y repetibles; antes no existía versionado ni registro de migraciones.

### Decisión
1. Versionar scripts SQL en `migrations/` y registrar nombres en `schema_migrations`.
2. Ejecutarlos mediante `php bin/migrate.php --backup=RUTA`; el runner verifica integridad y conteos del backup.
3. Aplicar cada migración en una transacción. Ante error, revertir esa migración; para revertir una ya confirmada, restaurar el backup verificado.

### Consecuencias
- **Gana:** ejecución idempotente y trazabilidad del esquema.
- **Sacrifica:** no hay scripts `down`; la reversión usa restore.
- **Evidencia:** `bin/migrate.php`, `migrations/001_normalize_legacy_data.sql`, `migrations/002_add_indexes.sql`.

---

## ADR-018 — Política operativa inicial de backups
**Fecha:** 2026-11-09  
**Estado:** Parcial; propuesta registrada, aprobación y despliegue pendientes de TI

### Contexto
F2 verificó un backup y una restauración aislada, pero no había frecuencia, retención ni objetivos de recuperación acordados. Programar copias antes de definir destino y cifrado podría dejar datos sin protección o expuestos.

### Decisión
1. Solicitar backup diario a las 02:00 `America/Caracas`, incluyendo `storage/db.db` y los CSV fuente.
2. Retener 30 copias diarias, 12 semanales y 12 mensuales. Se propone promover la última diaria de cada semana y mes; TI debe confirmar la definición de semana.
3. Exigir cifrado en reposo y prueba trimestral de restauración aislada.
4. Fijar objetivos de RPO de 24 horas y RTO de 1 día hábil.
5. Solicitar ejecución mediante `cron` en Linux.
6. No activar la tarea hasta que TI asigne un destino separado del host, apruebe herramienta/custodios de cifrado y confirme la ruta y cuenta de producción. `code/bck/systec` queda como ruta lógica propuesta, aún no confirmada como destino absoluto o externo.

### Consecuencias
- **Gana:** hay parámetros operativos iniciales, criterios de recuperación y alcance documentados.
- **Pendiente:** aprobación de TI, destino y separación física, herramienta/custodia de claves, definición de semana, usuario/host de producción, monitoreo y primera ejecución/restauración.
- **Evidencia:** `docs/BACKUP.md` y respuestas recibidas para F2-16.

---

## Índice

| ADR | Tema | Estado |
|---|---|---|
| 001 | Monolito PHP + SQLite | Reconstruida |
| 002 | Layout por output buffering | Reconstruida |
| 003 | Bootstrap + DataTables vendorizados | Reconstruida |
| 004 | Código de bien compuesto | Reconstruida |
| 005 | Importación CSV directa | Reconstruida |
| 006 | Estados de mantenimiento | Reconstruida |
| 007 | Ciclo de estado del bien | Reconstruida / cuestionada |
| 008 | Reportes desde datos vivos | Reconstruida |
| 009 | Sin autenticación | Reemplazada por ADR-015 |
| 010 | Sin migraciones | Deuda / Propuesta |
| 011 | `time_taken` en horas | Aceptada / corregida F1 |
| 012 | Datos legacy sin normalizar | Reconstruida / deuda |
| 013 | Endurecimiento vs reescritura | Aceptada (F1 ejecutada) |
| 014 | Repositorios + migraciones | Propuesta |
| 015 | Contención F1: auth, CSRF y storage | Implementada |
| 016 | Normalización de datos legacy | Aceptada / implementada |
| 017 | Migraciones SQLite versionadas | Aceptada / implementada |
| 018 | Política operativa inicial de backups | Parcial; requiere despliegue TI |

---

Ver también: `README.md`, `PROJECT.md`, `ARCHITECTURE.md`, `DATABASE.md`, `BACKUP.md`, `SECURITY.md`, `TODO.md`, `CHANGELOG.md`.
