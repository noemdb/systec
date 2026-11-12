# PROJECT.md — SysTec GMB

> **Estado:** v1.4 (tickets y componentes compartidos) · **Actualizado:** 2026-11-11 · **Dueño:** Oficina de Informática, San Felipe, Edo. Yaracuy.
> **Fuentes:** código PHP, `storage/db.db`, migraciones y backup previo a F2, `grafo/`.
> Ver también: `README.md`, `ARCHITECTURE.md`, `DOMAIN.md`, `TODO.md`.

## Elevator Pitch

Sistema web interno que administra el inventario de bienes de una institución y lleva el registro cronológico de todos los mantenimientos realizados a cada bien. Genera historiales técnicos por bien y un reporte imprimible de bienes dañados o desincorporados, a partir de datos cargados manualmente o importados desde CSV y reportes del sistema de bienes.

## Problema

- **¿Quién lo sufre?** La Oficina de Informática y el personal administrativo que hoy mantiene el inventario y los mantenimientos en hojas de cálculo, archivos CSV y reportes manuales.
- **¿Cómo hoy?** El inventario se importa desde un CSV o el reporte descargado del sistema de bienes; el histórico de mantenimientos conserva su CSV de origen. La base SQLite también se actualiza por formularios. Los reportes se imprimen desde el navegador.
- **¿Por qué no alcanza?** La autenticación y los controles básicos ya existen (F1); F2 añadió importaciones transaccionales y limitadas, normalización, migraciones y restricciones SQLite. Siguen faltando auditoría general, estrategia operativa de backups, mayor cobertura de pruebas y CI.

## Usuarios objetivo

| Rol | Necesidad principal | Nivel técnico | Estado actual |
|---|---|---|---|
| Operador de inventario | Consultar bienes, actualizar estado, cargar CSV | Medio-bajo | Rol `operador` con login |
| Técnico de mantenimiento | Registrar, editar y cerrar mantenimientos | Medio | Rol `tecnico` con login |
| Dirección / auditor | Ver historiales y reportes imprimibles | Medio | Rol `consulta` (solo lectura) |
| Personal institucional | Registrar incidencias y consultar su propio seguimiento | Bajo | Rol `personal`, restringido al ámbito `/personal/` |
| Administrador del sistema | Desplegar, respaldar, corregir datos y gestionar usuarios | Alto | Rol `admin`; alta de usuarios por CLI |

> **Riesgo de producto:** los roles ya se aplican en servidor (F1), pero no hay UI de gestión de usuarios, auditoría ni rate limiting. Ver `SECURITY.md`.

## Propuesta de valor

Un único lugar para responder: **¿qué bienes tenemos, qué mantenimientos se les hicieron, qué incidencias están abiertas y cuáles están dañados?** El valor está en la trazabilidad operativa por bien y en la generación de reportes imprimibles, no en la sofisticación tecnológica.

## Alcance actual (dentro)

- Inventario de bienes con código compuesto, descripción, modelo, serial, color y estado.
- Dashboard con búsqueda, conteo de mantenimientos y cambio rápido de estado.
- Registro manual de mantenimientos con tipo, técnico, fecha, razón, descripción, notas y próximo mantenimiento.
- Edición de mantenimientos y transición de estado `iniciado → revisión → finalizado`.
- Detección visual de mantenimientos vencidos y filtros por estado, tipo y técnico.
- Historial cronológico por bien, con membrete institucional y firma de "Realizado por".
- Reporte imprimible de bienes dañados/desincorporados.
- Impresión por lote de dos historiales.
- Registro y seguimiento de tickets vinculados a bienes. Soporte (`admin`, `operador`, `tecnico`) gestiona la bandeja global; el rol `personal` registra y consulta solo sus tickets.
- Alta de tickets mediante un modal reutilizable de tres pasos (selección del bien, detalles y resumen), disponible desde las áreas de tickets personal y soporte.
- Historial de eventos del ticket y baja lógica para `admin`/`operador`; los registros e historiales se conservan.
- Notificaciones toast dentro de la aplicación para mensajes de resultado; son avisos de interfaz, no notificaciones push ni correo.
- Importación de inventario mediante CSV, reporte `.xls` descargado o lectura de solo lectura desde PostgreSQL del sistema de bienes; mantenimientos desde `storage/maintenances.csv`.
- Importaciones limitadas a 5000 filas/5 MB, con transacción y reporte por fila.
- Migraciones SQL versionadas con runner CLI, backup verificado obligatorio, triggers de catálogos/transiciones e índices básicos.
- Backup previo a la normalización F2 en `storage/backups/2026-11-09/`, con integridad y restauración aislada verificadas.

## Fuera de alcance (explícito)

- Auditoría de cambios (quién, cuándo, valores anteriores/nuevos).
- API pública e integraciones en tiempo real. Existen endpoints JSON privados de uso interno para el selector de bienes y detalles de tickets.
- Multi-institución o multi-tenant.
- Gestión documental de adjuntos, fotos o PDFs firmados.
- Notificaciones push, correo o calendario; los toast son avisos temporales dentro de la interfaz.
- Gestión de compras, proveedores, presupuesto o contabilidad.
- Aplicación automática de migraciones al iniciar la web; la aplicación de migraciones sigue siendo explícita por CLI.
- Aplicación móvil o SPA.

## Métricas de éxito

| ID | Métrica | Estado actual |
|---|---|---|
| M1 | Inventario consultable y buscable | Operativo |
| M2 | Historial completo por bien | Operativo |
| M3 | Reporte de dañados imprimible | Operativo; consulta deduplicada en F2 |
| M4 | Importación CSV sin pérdida silenciosa | Transacción, límites y pruebas automatizadas parciales |
| M5 | Datos consistentes (estados, tipos, técnicos) | Estados/tipos y grafía técnica legacy normalizados en F2; técnicos siguen como texto libre |
| M6 | Acceso controlado y auditable | Parcial: auth y roles (F1); falta auditoría |
| M7 | Recuperación ante pérdida de `storage/db.db` | Parcial: propuesta de política registrada; aprobación TI, destino, cifrado y cron pendientes; ver `BACKUP.md` |
| M8 | Documentación que permita traspaso | **Cumplido con esta entrega** |

## Contexto y restricciones

- **Geografía:** San Felipe, Estado Yaracuy, Venezuela. Oficina de Informática.
- **Infraestructura:** compatibilidad con PHP 7.4 y extensión `sqlite3`, servidor embebido de PHP (`php -S localhost:5050`) o hosting con Apache/PHP. Composer se usa para dependencias de desarrollo/pruebas; no hay framework ni Docker.
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
| Pruebas | 🧪 Parciales | PHPUnit (`composer test`) y verificaciones manuales; cobertura y CI pendientes |
| Migraciones | ✅ F2 | `bin/migrate.php`, `migrations/`, `schema_migrations` |
| Tickets | ✅ Operativo | `tickets.php`, `personal/index.php`, endpoints y wizard compartido |
| Documentación | ✅ v1.4 | Este directorio; actualizado al 2026-11-11 |

## Referencias

- `grafo/logica-negocio.md` — flujos y reglas reconstruidas.
- `grafo/modelo-datos.md` — ERD y calidad de datos.
- `docs/BACKUP.md` — política registrada y pasos pendientes para habilitar backups periódicos.
- `docs/anexos/inventario-tecnico.md` — análisis archivo por archivo.
- `docs/anexos/matriz-trazabilidad.md` — hallazgo → código → documento → tarea.
- `docs/templatesOthersApp/` — referencia externa de estructura documental, no normativa de este proyecto.
