# PROJECT.md — SysTec GMB

> **Estado:** v1.2 (F2 en ejecución) · **Actualizado:** 2026-11-09 · **Dueño:** Oficina de Informática, San Felipe, Edo. Yaracuy.
> **Fuentes:** código PHP, `storage/db.db`, migraciones y backup previo a F2, `grafo/`.
> Ver también: `README.md`, `ARCHITECTURE.md`, `DOMAIN.md`, `TODO.md`.

## Elevator Pitch

Sistema web interno que administra el inventario de bienes de una institución y lleva el registro cronológico de todos los mantenimientos realizados a cada bien. Genera historiales técnicos por bien y un reporte imprimible de bienes dañados o desincorporados, a partir de datos cargados manualmente o importados desde CSV y reportes del sistema de bienes.

## Problema

- **¿Quién lo sufre?** La Oficina de Informática y el personal administrativo que hoy mantiene el inventario y los mantenimientos en hojas de cálculo, archivos CSV y reportes manuales.
- **¿Cómo hoy?** El inventario se importa desde un CSV o el reporte descargado del sistema de bienes; el histórico de mantenimientos conserva su CSV de origen. La base SQLite también se actualiza por formularios. Los reportes se imprimen desde el navegador.
- **¿Por qué no alcanza?** La autenticación y los controles básicos ya existen (F1); F2 añadió importaciones transaccionales y limitadas, normalización, migraciones y restricciones SQLite. Siguen faltando trazabilidad, estrategia operativa de backups y una suite de pruebas automatizada.

## Usuarios objetivo

| Rol | Necesidad principal | Nivel técnico | Estado actual |
|---|---|---|---|
| Operador de inventario | Consultar bienes, actualizar estado, cargar CSV | Medio-bajo | Rol `operador` con login |
| Técnico de mantenimiento | Registrar, editar y cerrar mantenimientos | Medio | Rol `tecnico` con login |
| Dirección / auditor | Ver historiales y reportes imprimibles | Medio | Rol `consulta` (solo lectura) |
| Administrador del sistema | Desplegar, respaldar, corregir datos y gestionar usuarios | Alto | Rol `admin`; alta de usuarios por CLI |

> **Riesgo de producto:** los roles ya se aplican en servidor (F1), pero no hay UI de gestión de usuarios, auditoría ni rate limiting. Ver `SECURITY.md`.

## Propuesta de valor

Un único lugar para responder: **¿qué bienes tenemos, qué mantenimientos se les hicieron, cuándo toca el próximo y cuáles están dañados?** El valor está en la trazabilidad histórica por bien y en la generación de reportes imprimibles, no en la sofisticación tecnológica.

## Alcance actual (dentro)

- Inventario de bienes con código compuesto, descripción, modelo, serial, color y estado.
- Dashboard con búsqueda, conteo de mantenimientos y cambio rápido de estado.
- Registro manual de mantenimientos con tipo, técnico, fecha, razón, descripción, notas y próximo mantenimiento.
- Edición de mantenimientos y transición de estado `iniciado → revisión → finalizado`.
- Detección visual de mantenimientos vencidos y filtros por estado, tipo y técnico.
- Historial cronológico por bien, con membrete institucional y firma de "Realizado por".
- Reporte imprimible de bienes dañados/desincorporados.
- Impresión por lote de dos historiales.
- Importación de inventario mediante CSV, reporte `.xls` descargado o lectura de solo lectura desde PostgreSQL del sistema de bienes; mantenimientos desde `storage/maintenances.csv`.
- Importaciones limitadas a 5000 filas/5 MB, con transacción y reporte por fila.
- Migraciones SQL versionadas con runner CLI, backup verificado obligatorio, triggers de catálogos/transiciones e índices básicos.
- Backup previo a la normalización F2 en `storage/backups/2026-11-09/`, con integridad y restauración aislada verificadas.

## Fuera de alcance (explícito)

- Auditoría de cambios (quién, cuándo, valores anteriores/nuevos).
- API pública, JSON, integraciones en tiempo real.
- Multi-institución o multi-tenant.
- Gestión documental de adjuntos, fotos o PDFs firmados.
- Notificaciones, correo o calendario.
- Gestión de compras, proveedores, presupuesto o contabilidad.
- Aplicación automática de migraciones al iniciar la web y suite persistente de pruebas (la migración es explícita por CLI; las pruebas quedan para F3).
- Aplicación móvil o SPA.

## Métricas de éxito

| ID | Métrica | Estado actual |
|---|---|---|
| M1 | Inventario consultable y buscable | Operativo |
| M2 | Historial completo por bien | Operativo |
| M3 | Reporte de dañados imprimible | Operativo; consulta deduplicada en F2 |
| M4 | Importación CSV sin pérdida silenciosa | Transacción y límites verificados en F2; no hay suite persistente |
| M5 | Datos consistentes (estados, tipos, técnicos) | Estados/tipos y grafía técnica legacy normalizados en F2; técnicos siguen como texto libre |
| M6 | Acceso controlado y auditable | Parcial: auth y roles (F1); falta auditoría |
| M7 | Recuperación ante pérdida de `storage/db.db` | Parcial: propuesta de política registrada; aprobación TI, destino, cifrado y cron pendientes; ver `BACKUP.md` |
| M8 | Documentación que permita traspaso | **Cumplido con esta entrega** |

## Contexto y restricciones

- **Geografía:** San Felipe, Estado Yaracuy, Venezuela. Oficina de Informática.
- **Infraestructura:** PHP 7.4 con extensión `sqlite3`, servidor embebido de PHP (`php -S localhost:5050`) o hosting con Apache/PHP. No hay Composer, Docker ni framework.
- **Persistencia:** archivo SQLite `storage/db.db`, con backups y CSV en `storage/` (denegados por `router.php`/`.htaccess`).
- **Conectividad:** no se asume internet; la aplicación es completamente local.
- **Operación:** el cambio de estado y los reportes se ejecutan desde el navegador; la impresión usa el diálogo del navegador.
- **Datos:** 617 bienes, 547 mantenimientos y 4 usuarios al 2026-11-09; ver `DATABASE.md` para conteos y snapshot detallado.
- **Equipo:** mantenimiento pequeño, sin pipeline de CI ni revisión formal.

## Estado del sistema (snapshot)

| Área | Estado | Evidencia |
|---|---|---|
| Inventario | ✅ Operativo | 617 filas en `properties` |
| Mantenimientos | ✅ Operativo | 547 filas; estados normalizados y triggers activos |
| Historial | ✅ Operativo | `historials.php` |
| Reporte de dañados | ✅ Operativo | `conn.php::indexWithMaintenances`, deduplicado en F2 |
| Importación | ✅ Operativa | `include/csv_import.php`, transacción y límite |
| Seguridad | 🧪 F1 | Controles de aplicación implementados; HTTPS/HSTS en servidor real, rate limiting y auditoría pendientes |
| Pruebas | ⚠️ Manuales | `php -l` + pruebas de humo; sin suite automatizada |
| Migraciones | ✅ F2 | `bin/migrate.php`, `migrations/`, `schema_migrations` |
| Documentación | ✅ v1.2 (F2) | Este directorio |

## Referencias

- `grafo/logica-negocio.md` — flujos y reglas reconstruidas.
- `grafo/modelo-datos.md` — ERD y calidad de datos.
- `docs/BACKUP.md` — política registrada y pasos pendientes para habilitar backups periódicos.
- `docs/anexos/inventario-tecnico.md` — análisis archivo por archivo.
- `docs/anexos/matriz-trazabilidad.md` — hallazgo → código → documento → tarea.
- `docs/templatesOthersApp/` — referencia externa de estructura documental, no normativa de este proyecto.
