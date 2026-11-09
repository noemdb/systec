# SysTec GMB

Sistema de Gestión de Mantenimientos de Bienes de la institución. Monolito PHP 7.4 + SQLite, sin framework ni Composer.

## Requisitos

- PHP 7.4 o superior con `sqlite3`, `mbstring` y `session`.
- Escritura en `storage/` (base de datos, backups y logs).

## Inicio rápido

```bash
# 1. Crear el primer usuario administrador
php bin/create-user.php admin admin

# 2. Levantar el servidor de desarrollo
make dev
# http://localhost:5050
```

El servidor de desarrollo usa `router.php`, que bloquea el acceso directo a `storage/`, `docs/`, `grafo/`, `spec/`, `bin/`, `include/` y a archivos `.db`, `.csv`, `.log` y `.md`.

## Usuarios y roles

| Rol | Ver | Cambiar estado de bien | Registrar/editar mantenimiento | Importar |
|---|---|---|---|---|
| `admin` | ✅ | ✅ | ✅ | ✅ |
| `operador` | ✅ | ✅ | ✅ | ✅ |
| `tecnico` | ✅ | — | ✅ | — |
| `consulta` | ✅ | — | — | — |

Los usuarios se gestionan por CLI:

```bash
php bin/create-user.php <usuario> <rol> [contraseña]
```

Si se omite la contraseña, el script genera una aleatoria y la muestra una sola vez. Las contraseñas se guardan con `password_hash()`.

## Estructura

```
systec/
├── *.php                  # Rutas de primer nivel
├── conn.php               # Capa de datos (SQLite3 + prepared statements)
├── login.php / logout.php # Autenticación
├── router.php             # Router y denegaciones del servidor embebido
├── .htaccess              # Denegaciones para Apache
├── bin/create-user.php    # Alta/actualización de usuarios
├── include/               # config, security, auth, layout y helpers
├── dashboard/             # Inventario
├── maintenances/          # Mantenimientos
├── properties/            # Importación de inventario
├── historials/            # Vistas de historial
├── storage/               # db.db, backups, CSV y logs (fuera del webroot)
├── docs/                  # Documentación as-built
├── grafo/                 # Grafo de lógica de negocio
└── vendor/                # Bootstrap, DataTables y jQuery vendorizados
```

## Seguridad

- Toda ruta de aplicación exige sesión (`require_login()`).
- Las mutaciones usan `POST` + token CSRF; los GET no cambian datos.
- El SQL usa `prepare()`/`bindValue()` y listas blancas de tabla/columna.
- La salida HTML pasa por `e()`.
- Los datos viven en `storage/` y se deniegan por `router.php`/`.htaccess`.
- Cabeceras de seguridad y errores fuera de pantalla en `include/security.php`.

Antes de exponer el sistema fuera de la red de confianza: configurar HTTPS/HSTS en el servidor real, revisar `docs/SECURITY.md` y ejecutar un backup con restore probado. Ver `docs/anexos/plan-documentacion.md` para el plan completo.

## Documentación

- `docs/README.md` — índice y proceso documental.
- `docs/ARCHITECTURE.md` — arquitectura y flujo de request.
- `docs/API.md` — rutas, parámetros y roles.
- `docs/DATABASE.md` — esquema, diccionario y calidad de datos.
- `docs/SECURITY.md` — hallazgos, controles y checklist.
- `docs/TODO.md` — backlog y estado por fase.
- `docs/CHANGELOG.md` — bitácora de cambios.
- `grafo/` — lógica de negocio y modelo de datos.

## Estado

F1 (contención de seguridad) implementada el 2026-11-09. Quedan pendientes de F2/F3: normalización de datos legacy, migraciones versionadas, índices, reporte de dañados sin duplicados, auditoría y pruebas automatizadas.
