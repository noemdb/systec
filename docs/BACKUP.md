# Procedimiento de backup y restauración — SysTec GMB

> **Estado:** `bin/backup.sh` implementado y probado en pruebas automatizadas; **no instalado en cron** y sin alertas operativas.
> **Actualizado:** 2026-11-26. **Zona horaria por defecto:** `America/Caracas`.
> La ejecución recomendada es con clave de cifrado y `--require-encryption`. El cifrado es opcional si no se exige el flag, por lo que una ejecución sin clave produce un backup legible.

---

## 1. Alcance actual

El script respalda:

- Un snapshot consistente de SQLite obtenido con `SQLite3::backup()`.
- Los archivos `storage/*.csv` que existan al momento de la ejecución.
- Un `manifest.json` con fecha, hora y resultado de verificación.

No incluye:

- Código fuente, migraciones, plantillas o dependencias.
- `.env`, credenciales o archivo de clave.
- La caché de adscripciones/asignaciones.
- Logs de aplicación.
- Una copia automática a otro host.

La restauración requiere una versión compatible del código SysTec y las dependencias PHP/SQLite correspondientes.

---

## 2. Ejecución recomendada

Requisitos del servidor Linux:

- Bash.
- PHP con extensión SQLite3.
- `tar`.
- `openssl` cuando se use cifrado.
- Utilidades GNU `date`, `find`, `sort` y `mapfile` usadas por el script.
- Espacio suficiente para snapshot temporal, archivo comprimido/cifrado y copias promovidas.

Preparar un archivo de clave fuera del repositorio y fuera del backup, con permisos restrictivos, por ejemplo solo lectura para la cuenta de servicio. No registrar su contenido en logs ni pasar la clave directamente en la línea de comandos.

Ejecución recomendada:

```bash
SYSTEC_BACKUP_KEY_FILE=/ruta/institucional/systec-backup.key \
  /home/nuser/code/systec/bin/backup.sh --require-encryption
```

Comportamiento del cifrado:

| Invocación | Resultado |
|---|---|
| Sin `SYSTEC_BACKUP_KEY_FILE` y sin flag | `.tar.gz` **sin cifrar** |
| Con `SYSTEC_BACKUP_KEY_FILE` | `.tar.gz.enc`, aunque no se pase el flag |
| `--require-encryption` sin clave | Termina con error; no publica backup |
| Clave configurada pero ilegible | Termina con error |

El cifrado usa `openssl enc -aes-256-cbc -pbkdf2 -salt` con la clave leída desde el archivo. El script **no descifra el resultado para probarlo**; un `STATUS=OK` confirma que el comando de cifrado terminó, no que la clave podrá recuperarse.

---

## 3. Variables de configuración

| Variable | Valor por defecto | Uso |
|---|---|---|
| `SYSTEC_STORAGE` | `<repo>/storage` | Directorio de datos |
| `SYSTEC_DB` | `${SYSTEC_STORAGE}/db.db` | Base SQLite origen |
| `SYSTEC_BACKUP_DEST` | `${SYSTEC_STORAGE}/backups/automated` | Destino de artefactos |
| `SYSTEC_BACKUP_LOG` | `${SYSTEC_STORAGE}/logs/backup.log` | Log de ejecución |
| `SYSTEC_BACKUP_KEY_FILE` | Vacío | Archivo de clave; habilita cifrado |
| `SYSTEC_BACKUP_KEEP_DAILY` | `30` | Copias diarias conservadas |
| `SYSTEC_BACKUP_KEEP_WEEKLY` | `12` | Copias semanales conservadas |
| `SYSTEC_BACKUP_KEEP_MONTHLY` | `12` | Copias mensuales conservadas |
| `SYSTEC_BACKUP_NOW` | Fecha/hora actual | Marca `YYYY-MM-DD_HHMMSS`; útil para pruebas o ejecuciones controladas |
| `SYSTEC_BACKUP_WEEK` | Semana ISO `YYYY-Www` | Clave de promoción semanal |
| `SYSTEC_BACKUP_MONTH` | `YYYY-MM` | Clave de promoción mensual |
| `TZ` | `America/Caracas` | Zona usada para fecha y log |

`SYSTEC_BACKUP_NOW`, `SYSTEC_BACKUP_WEEK` y `SYSTEC_BACKUP_MONTH` deben reservarse para pruebas o recuperaciones manuales: una marca no cronológica altera el orden de rotación.

---

## 4. Proceso de creación

1. Aplica `umask 077`.
2. Comprueba base, destino y clave cuando el cifrado es obligatorio.
3. Crea un directorio temporal bajo el destino.
4. Abre la base origen en modo lectura y copia con `SQLite3::backup()`; ambas conexiones usan un busy timeout de 5 segundos.
5. Ejecuta `PRAGMA integrity_check` sobre el snapshot y exige exactamente `ok`.
6. Copia los CSV fuente si existen.
7. Escribe `manifest.json`.
8. Empaqueta el snapshot con `tar -czf`.
9. Si hay clave, cifra el `.tar.gz` y elimina el archivo sin cifrar del directorio temporal.
10. Publica el archivo diario con `mv` atómico.
11. Copia el mismo artefacto a las claves semanal y mensual mediante archivo `.tmp` y `mv`.
12. Aplica rotación y escribe el resultado en el log.

Un fallo no capturado antes de la publicación deja las copias anteriores intactas y devuelve un código de salida distinto de cero.

---

## 5. Artefactos

Con cifrado, la estructura es:

```text
DEST/
├── daily/
│   └── systec-backup-2026-11-26_020000.tar.gz.enc
├── weekly/
│   └── systec-weekly-2026-W48.tar.gz.enc
├── monthly/
│   └── systec-monthly-2026-11.tar.gz.enc
└── logs/backup.log              # si SYSTEC_BACKUP_LOG apunta aquí
```

Sin clave, los artefactos terminan en `.tar.gz`.

Dentro de cada archivo comprimido:

```text
systec-backup-2026-11-26_020000/
├── db.db
├── manifest.json
├── properties.csv               # si existe
└── maintenances.csv             # si existe
```

Las copias semanales y mensuales son promociones del archivo diario, no snapshots nuevos. La clave semanal se sobrescribe con la última ejecución de esa semana; la mensual, con la última del mes.

---

## 6. Verificación y manifiesto

El snapshot se verifica con:

```sql
PRAGMA integrity_check;
```

El manifiesto registra:

- `stamp`.
- `created_at`.
- `verification.integrity_check`.
- `verification.counts` para `properties`, `maintenances`, `users` y `tickets`.

Limitaciones:

- Los conteos se obtienen del snapshot; **no se comparan con la base origen ni con una copia anterior**.
- Si una de esas cuatro tablas no existe, el conteo se registra como `0`; no se exige su presencia.
- No se registran ni verifican conteos de `profile`, `ticket_histories`, `rendition_reports`, `ticket_status`, asignaciones ni otras tablas.
- No se genera un checksum del archivo `.tar.gz`/`.enc`.
- Los CSV se copian, pero no se validan ni se relacionan criptográficamente con el snapshot.
- La verificación de integridad de SQLite no garantiza integridad semántica ni que el backup sea completo para el negocio.

---

## 7. Rotación

Después de publicar y promover la copia, el script conserva por defecto:

- 30 diarias.
- 12 semanales.
- 12 mensuales.

La rotación ordena nombres de archivo en orden descendente y elimina los excedentes. Solo corre después de una ejecución exitosa. No hay bloqueo entre ejecuciones concurrentes; el programador operativo debe evitar solapamientos.

---

## 8. Restauración manual en entorno aislado

El repositorio no incluye un comando de restauración. El procedimiento es manual y debe ejecutarse **fuera de producción**.

### 8.1 Descifrar y extraer

Con una copia cifrada:

```bash
RESTORE_DIR=/ruta/aislada/systec-restore
BACKUP_FILE=/ruta/backups/daily/systec-backup-2026-11-26_020000.tar.gz.enc
KEY_FILE=/ruta/institucional/systec-backup.key

mkdir -p "$RESTORE_DIR"
openssl enc -d -aes-256-cbc -pbkdf2 -salt \
  -in "$BACKUP_FILE" \
  -out "$RESTORE_DIR/systec-backup.tar.gz" \
  -pass file:"$KEY_FILE"

tar -xzf "$RESTORE_DIR/systec-backup.tar.gz" -C "$RESTORE_DIR"
```

Para una copia sin cifrar, omitir `openssl` y extraer directamente el `.tar.gz`.

### 8.2 Verificar

1. Confirmar que el descifrado y `tar` terminaron sin error.
2. Localizar `systec-backup-*/db.db` y `manifest.json`.
3. Ejecutar `PRAGMA integrity_check` sobre la base extraída y exigir `ok`.
4. Comparar los conteos de `properties`, `maintenances`, `users` y `tickets` con el manifiesto.
5. Revisar manualmente conteos de `profile`, `ticket_histories` y `rendition_reports`, porque el manifiesto no los incluye.
6. Confirmar que los CSV esperados estén presentes y correspondan al mismo `stamp`.

### 8.3 Abrir una aplicación aislada

- Usar una copia del código compatible con el backup.
- Apuntar `SYSTEC_DB` a la base restaurada en el entorno aislado.
- No copiar la base restaurada sobre `storage/db.db` productivo.
- Validar login, listados y consultas básicas sin enviar datos a servicios externos no autorizados.
- Medir duración y registrar resultado, responsable y acciones correctivas.

**Esta prueba debe incluir el descifrado real.** El script de backup no la realiza.

---

## 9. Backups anteriores a la migración 009

Un backup previo a 009 **no es directamente compatible con `users.php`**:

- Puede no tener `users.email`.
- No tiene la tabla `profile` creada por 010.
- `users.php` selecciona ambas mediante `LEFT JOIN profile`, por lo que falla antes de poder administrar cuentas.
- `conn.php` detecta que `users` ya existe y omite la creación inicial del esquema; no repara esas columnas/tablas automáticamente.

Procedimiento:

1. Restaurar y verificar el backup en un entorno aislado.
2. Hacer una copia verificada de la base restaurada antes de migrar.
3. Ejecutar las migraciones pendientes hasta **011** con `bin/migrate.php`, apuntando `SYSTEC_DB` a la base aislada:

```bash
SYSTEC_DB=/ruta/aislada/systec-restore/systec-backup-STAMP/db.db \
  php /home/nuser/code/systec/bin/migrate.php \
  --backup=/ruta/aislada/copia-verificada-antes-de-migrar.db
```

4. `bin/migrate.php` exige que el backup exista, supere `PRAGMA integrity_check` y tenga los mismos conteos de `properties`, `maintenances` y `users` que la base destino.
5. Revisar el resultado de 001–011 y validar `users`, `profile`, tickets, historiales e informes antes de usar la aplicación.
6. Reconciliar el nombre canónico de los perfiles antes de ejecutar `bin/seed-profiles.php`; la discrepancia actual `abrizuela`/`abrizzuela` hace que el seeder revierta toda la carga.

No ejecutar estas migraciones directamente sobre la base productiva como parte de una restauración.

---

## 10. Cron, alertas y monitoreo

El script **no instala cron** y **no envía alertas**. Solo escribe en el log configurado y en la salida estándar/error.

Antes de programarlo, TI debe:

1. Aprobar destino absoluto, permisos y separación física del host.
2. Definir custodio y suplente de la clave, recuperación y rotación.
3. Confirmar cuenta de servicio, rutas y zona horaria.
4. Programar una tarea diaria, por ejemplo a las 02:00, evitando solapamientos.
5. Definir quién revisa `STATUS=FAIL` y cómo recibe avisos.
6. Copiar los artefactos a un medio/host que no dependa del mismo servidor.
7. Probar restauración y descifrado de forma trimestral en aislamiento.

Una expresión cron propuesta es `0 2 * * *`, pero debe instalarse solo después de aprobar host, paths, clave y procedimiento de alerta.

---

## 11. Prueba trimestral y criterio de aceptación

En cada prueba:

- [ ] Elegir una copia dentro de la retención.
- [ ] Recuperar la clave por el procedimiento aprobado.
- [ ] Descifrar y extraer en un entorno aislado.
- [ ] Verificar `PRAGMA integrity_check = ok`.
- [ ] Contrastar el manifiesto y revisar tablas no incluidas en él.
- [ ] Abrir la aplicación con datos de prueba.
- [ ] Aplicar migraciones si la copia es anterior al esquema vigente.
- [ ] Medir RPO/RTO y registrar resultado sin claves ni hashes.
- [ ] Abrir incidencia y conservar evidencia si falla.

Criterios para considerar el servicio operativo:

- [ ] Destino fuera del host aprobado y probado.
- [ ] Cifrado obligatorio y custodia de clave documentados.
- [ ] Cron instalado y sin solapamientos.
- [ ] Alertas de fallo recibidas por un responsable.
- [ ] Restauración/descifrado trimestral completado.
- [ ] Retención 30/12/12 verificada.
- [ ] RPO de 24 horas y RTO de 1 día hábil revisados.

---

Ver también: `SECURITY.md`, `ARCHITECTURE.md`, `DATABASE.md`, `TODO.md` y `tests/Unit/BackupAndUsersTest.php`.
