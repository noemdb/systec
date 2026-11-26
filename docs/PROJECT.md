# PROJECT.md — SysTec GMB

> **Estado:** v1.6 (inventario, mantenimientos, tickets, usuarios/perfiles e informes de rendición) · **Actualizado:** 2026-11-26 · **Dueño:** Oficina de Informática, San Felipe, Edo. Yaracuy.
> **Fuentes:** código PHP, `storage/db.db`, migraciones `001`–`011`, pruebas y `grafo/`.

## Elevator Pitch

Sistema web interno que administra el inventario de bienes de una institución, registra mantenimientos y tickets de soporte, conserva la trazabilidad por bien y genera historiales, reportes de dañados e informes de rendición. También administra cuentas, roles y perfiles de usuario, y permite que el personal institucional gestione sus propias solicitudes.

## Problema

- **¿Quién lo sufre?** La Oficina de Informática, el personal administrativo y los técnicos que mantienen inventarios y mantenimientos en hojas de cálculo, CSV y reportes manuales.
- **¿Cómo se resuelve hoy?** El inventario se importa desde CSV, un reporte `.xls` tabular o PostgreSQL legacy; los mantenimientos y tickets se registran desde formularios web; los reportes se imprimen desde el navegador.
- **¿Qué falta?** Auditoría general persistente, revocación centralizada de sesiones, HTTPS/HSTS en producción, pruebas E2E y operación de backups aprobada por TI.

## Usuarios objetivo

| Rol | Necesidad principal | Estado actual |
|---|---|---|
| Operador de inventario | Consultar bienes, cambiar condición, importar y gestionar tickets | Rol `operador` |
| Técnico de mantenimiento | Registrar/editar mantenimientos y gestionar tickets | Rol `tecnico` |
| Dirección / auditor | Consultar inventario, historiales, reportes y tickets | Rol `consulta`; principalmente solo lectura |
| Personal institucional | Registrar incidencias y consultar sus seguimientos | Rol `personal`, portal `/personal/` |
| Administrador del sistema | Operar el sistema, respaldar y gestionar cuentas/perfiles | Rol `admin`; interfaz web y CLI |

> **Límite de autorización:** el rol se guarda en la sesión al iniciar sesión. Cambiar el rol o desactivar una cuenta impide nuevos logins, pero no invalida por sí solo una sesión ya abierta.

## Alcance actual

- Inventario de bienes con código compuesto, descripción, modelo, serial, color, estado, adscripción y asignaciones.
- Dashboard con búsqueda, filtros, indicadores, exportación y selector explícito de condición del bien.
- Registro y edición de mantenimientos en drawer, con tipo, técnico, fecha, motivo, descripción, notas, horas, próximo mantenimiento y ticket opcional del mismo bien.
- Transiciones individuales y masivas: `iniciado → revision/finalizado`, `revision → finalizado`, `finalizado → revision`.
- Historial por bien, impresión filtrada, lote de dos historiales y reporte de bienes dañados con CSV/impresión.
- Tickets globales con wizard en drawer, filtros, seguimiento, historial y baja lógica para `admin`/`operador`.
- Portal Personal para crear tickets propios, consultar detalle y añadir seguimientos a tickets no finalizados.
- Informes de Rendición persistentes por período y alcance, con edición Markdown, estados `borrador`/`aprobado`, impresión y descarga `.md`.
- Gestión web de usuarios en `/users.php`: búsqueda, filtros, indicadores, CSV, alta/edición en drawer, rol, estado, contraseña y perfil (`first_name`, `last_name`, `avatar_url`).
- Desbloqueo de cuentas, protección del administrador en sesión y eliminación permanente con doble confirmación.
- Importación de inventario CSV/XLS/PostgreSQL y mantenimientos CSV, con confirmación, transacción, límites y reporte por fila.
- Sincronización separada de responsables y usuarios asignados a bienes, con caché local.
- Migraciones SQL versionadas `001`–`011`, runner con backup verificado y seeder idempotente de perfiles.
- Backup programable con snapshot SQLite, verificación, cifrado opcional/exigible y rotación; la instalación de cron sigue pendiente de TI.
- Asistencia opcional con NVIDIA Build para narrativas de tickets, mantenimientos, seguimientos e informes de rendición.

## Fuera de alcance

- Auditoría general `audit_events` y registro persistente de motivos de cambio de estado.
- Recuperación de contraseña por email; el email actualmente es informativo y no se edita en el drawer.
- Invalidación inmediata de todas las sesiones al cambiar rol/estado o eliminar una cuenta.
- API pública, multi-institución, adjuntos, notificaciones push/correo, app móvil o SPA.
- Migraciones `down`; la reversión confirmada se realiza restaurando un backup.
- Aplicación automática de todas las migraciones al iniciar la web. Existe una salvaguarda runtime que crea la tabla de informes de rendición si falta, pero el flujo normal es CLI.

## Requisitos y restricciones

- **Runtime:** PHP 8.2 o superior, extensiones `sqlite3`, `mbstring` y `session`; Composer fija plataforma 8.2.
- **Persistencia:** `storage/db.db`; backups, CSV, caché y logs en `storage/`.
- **Conectividad:** el núcleo funciona sin internet. Los avatares remotos y NVIDIA Build son opcionales y requieren salida de red.
- **Despliegue:** servidor embebido PHP para desarrollo o Apache/PHP; HTTPS/HSTS real sigue pendiente.
- **Datos:** snapshot 2026-11-26 con 627 bienes, 324 mantenimientos, 8 tickets (4 activos y 4 con baja lógica), 19 eventos de historial, 29 usuarios, 29 perfiles y 1 informe de rendición. Los totales pueden variar con el uso.

## Estado del sistema

| Área | Estado | Evidencia |
|---|---|---|
| Inventario y mantenimientos | Operativo | `dashboard.php`, `maintenances.php`, repositorios y triggers |
| Tickets y Portal Personal | Operativo | `tickets.php`, `personal/`, `TicketRepository` |
| Usuarios y perfiles | Operativo | `users.php`, `include/user_manage_drawer.php`, `bin/seed-profiles.php` |
| Informes de rendición | Operativo | `rendition_reports`, wizard, repositorio e impresión |
| Migraciones | Operativo | `001`–`011` registradas en `schema_migrations` |
| Seguridad | Parcial | Auth, roles, CSRF, rate limiting y CSP; faltan HTTPS/HSTS, auditoría y revocación centralizada |
| Pruebas | Unitarias/integración local | PHPUnit y `make ci`; faltan E2E y medición de cobertura |
| Backups | Parcial | Script verificable implementado; cron, destino separado y primera restauración operativa pendientes |
| Documentación | v1.6 | `docs/`, `grafo/` y guía publicada en `documentation.php` |

## Referencias

- `docs/DATABASE.md` y `grafo/modelo-datos.md`: esquema y migraciones.
- `docs/DOMAIN.md` y `grafo/logica-negocio.md`: reglas y flujos.
- `docs/API.md`: rutas y parámetros.
- `docs/SECURITY.md` y `docs/BACKUP.md`: controles y operación.
- `docs/templatesOthersApp/`: referencia externa, no normativa para SysTec.
