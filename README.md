# SysTec GMB

Sistema de Gestión de Mantenimientos de Bienes de la institución. Monolito PHP 8.2 + SQLite, sin framework; Composer solo para dependencias de desarrollo y documentación.

## Requisitos

- PHP 8.2 o superior con `sqlite3`, `mbstring` y `session`.
- Escritura para la cuenta que ejecuta PHP en `storage/` y sus subdirectorios (`storage/cache`, `storage/logs`). La caché de adscripciones institucionales vive en `storage/cache`; si se alterna entre CLI y el servidor web, ambos usuarios deben poder leerla y escribirla.

## Inicio rápido

```bash
# 1. Crear el primer usuario administrador
php bin/create-user.php admin admin

# 2. Levantar el servidor de desarrollo
make dev
# http://localhost:5050
```

El servidor de desarrollo usa `router.php`, que bloquea el acceso directo a `storage/`, `docs/`, `grafo/`, `spec/`, `bin/`, `include/` y a archivos `.db`, `.csv`, `.log` y `.md`.

## CI local y hooks

```bash
composer install   # instala dependencias (PHPUnit, MermaidPHP)
make hooks         # activa .githooks/pre-push
```

El hook `pre-push` ejecuta `composer ci` (lint de PHP + PHPUnit) antes de cada push, igual que el workflow de GitHub Actions. Se puede omitir puntualmente con `git push --no-verify`.

## Usuarios, perfiles y roles

| Rol | Inventario y mantenimientos | Tickets | Usuarios y perfiles | Importar / sincronizar |
|---|---|---|---|---|
| `admin` | ✅ | ✅ Global | ✅ | ✅ |
| `operador` | ✅ | ✅ Global | — | ✅ |
| `tecnico` | ✅ Mantenimientos | ✅ Global | — | — |
| `consulta` | Solo lectura | Listado/drawer; sin detalle ni acciones | — | — |
| `personal` | — | ✅ Solo sus tickets en `/personal/` | — | — |

Los administradores gestionan cuentas y perfiles desde `/users.php` mediante un drawer lateral. Allí se editan rol, estado, contraseña, `first_name`, `last_name` y `avatar_url`. La tabla de usuarios muestra avatar, nombre completo y email; la exportación CSV incluye esos datos.

También existe el alta/actualización por CLI:

```bash
php bin/create-user.php <usuario> <rol> [contraseña]
```

Si se omite la contraseña, el script genera una aleatoria y la muestra una sola vez. Las contraseñas se guardan con `password_hash()`.

El perfil se almacena en `profile` con relación uno a uno a `users`. Para sincronizar los perfiles del personal sin duplicar registros ni reemplazar avatares existentes:

```bash
php bin/seed-profiles.php
```

## Migraciones

Las evoluciones versionadas se encuentran en `migrations/` y se registran en `schema_migrations`. Para aplicarlas se requiere un backup SQLite verificable:

```bash
php bin/migrate.php --backup=RUTA_A_BACKUP_VERIFICADO
```

El estado actual incluye las migraciones `001` a `011`: normalización e índices, soporte de tickets, baja lógica, asignaciones de bienes, vínculo mantenimiento–ticket, informes de rendición, cuentas personales, tabla `profile` y corrección de usernames.

## Estructura

```
systec/
├── *.php                  # Rutas de primer nivel
├── conn.php               # Capa de datos (SQLite3 + prepared statements)
├── login.php / logout.php # Autenticación
├── router.php             # Router y denegaciones del servidor embebido
├── .htaccess              # Denegaciones para Apache
├── bin/create-user.php    # Alta/actualización de usuarios por CLI
├── bin/migrate.php        # Migraciones SQL con backup verificado
├── bin/seed-profiles.php  # Sincronización idempotente de perfiles
├── include/               # config, security, auth, layout, drawer de usuarios y helpers
├── migrations/            # Evoluciones SQL versionadas 001-011
├── users/                 # Administración de cuentas y perfiles
├── dashboard/             # Inventario
├── maintenances/          # Mantenimientos e informes de rendición
├── properties/            # Importación de inventario
├── historials/            # Vistas de historial
├── tickets/               # Bandeja global y acciones de tickets
├── personal/              # Tickets propios del rol personal
├── storage/               # db.db, backups, CSV y logs (protegido; idealmente fuera del webroot)
├── docs/                  # Documentación as-built
├── grafo/                 # Grafo de lógica de negocio
└── vendor/                # Bootstrap, iconos y dependencias legacy vendorizadas
```

## Seguridad

- Las rutas funcionales exigen sesión (`require_login()`); `login.php` y los assets públicos son las excepciones.
- Las mutaciones usan `POST` + token CSRF; los GET no cambian datos.
- El SQL usa `prepare()`/`bindValue()` y listas blancas de tabla/columna.
- La salida HTML pasa por `e()`.
- Los datos viven en `storage/` y se deniegan por `router.php`/`.htaccess`.
- Cabeceras de seguridad y errores fuera de pantalla en `include/security.php`. La CSP permite imágenes `self`, `data:` y `http/https` para avatares externos.

Antes de exponer el sistema fuera de la red de confianza: configurar HTTPS/HSTS en el servidor real, revisar `docs/SECURITY.md` y ejecutar un backup con restore probado. Ver `docs/anexos/plan-documentacion.md` para el plan completo.

## Documentación

- `docs/README.md` — índice y proceso documental.
- `docs/ARCHITECTURE.md` — arquitectura y flujo de request.
- `docs/API.md` — rutas, parámetros y roles.
- `docs/DATABASE.md` — esquema, diccionario y calidad de datos.
- `docs/SECURITY.md` — hallazgos, controles y checklist.
- `docs/TODO.md` — backlog y estado por fase.
- `docs/CHANGELOG.md` — bitácora de cambios.
- `documentation.php` — guía funcional publicada para usuarios autenticados.
- `grafo/` — lógica de negocio y modelo de datos.

## Estado

El sistema incluye inventario, mantenimientos, informes de rendición, tickets, portal personal, gestión web de usuarios y perfiles, importaciones, backups programables y asistencia narrativa opcional con NVIDIA. Las tareas operativas pendientes se mantienen en `docs/TODO.md`; HTTPS/HSTS real, auditoría general, revocación centralizada de sesiones y pruebas E2E siguen sujetas a despliegue o aprobación.
