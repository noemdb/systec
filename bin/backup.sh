#!/usr/bin/env bash
# =============================================================================
# OPS-01 · Script de respaldo automatizado y verificable para SysTec GMB
#
# Características (según docs/BACKUP.md y ADR-018):
# 1. umask 077 para proteger artefactos temporales y definitivos.
# 2. Snapshot consistente en caliente mediante SQLite3::backup() de PHP.
# 3. Verificación obligatoria de PRAGMA integrity_check = ok sobre la copia.
# 4. Empaquetado de db.db + storage/*.csv + manifest.json (sin PII ni claves).
# 5. Cifrado AES-256-CBC (PBKDF2) opcional/exigible vía SYSTEC_BACKUP_KEY_FILE.
# 6. Publicación atómica y promoción automática a semanal (YYYY-Www) y mensual (YYYY-MM).
# 7. Rotación 30 diarias / 12 semanales / 12 mensuales tras verificar éxito.
# =============================================================================

set -euo pipefail
umask 077

 export TZ="${TZ:-America/Caracas}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STORAGE_DIR="${SYSTEC_STORAGE:-${ROOT_DIR}/storage}"
DB_FILE="${SYSTEC_DB:-${STORAGE_DIR}/db.db}"
BACKUP_DEST="${SYSTEC_BACKUP_DEST:-${STORAGE_DIR}/backups/automated}"
LOG_DIR="$(dirname "${SYSTEC_BACKUP_LOG:-${STORAGE_DIR}/logs/backup.log}")"
LOG_FILE="${SYSTEC_BACKUP_LOG:-${LOG_DIR}/backup.log}"
KEY_FILE="${SYSTEC_BACKUP_KEY_FILE:-}"
REQUIRE_ENCRYPTION=0

DAILY_KEEP="${SYSTEC_BACKUP_KEEP_DAILY:-30}"
WEEKLY_KEEP="${SYSTEC_BACKUP_KEEP_WEEKLY:-12}"
MONTHLY_KEEP="${SYSTEC_BACKUP_KEEP_MONTHLY:-12}"

for arg in "$@"; do
    case "$arg" in
        --require-encryption)
            REQUIRE_ENCRYPTION=1
            ;;
        -h|--help)
            printf 'Uso: %s [--require-encryption]\n' "$0"
            exit 0
            ;;
        *)
            printf 'Argumento no reconocido: %s\n' "$arg" >&2
            exit 2
            ;;
    esac
done

mkdir -p "$LOG_DIR" "$BACKUP_DEST/daily" "$BACKUP_DEST/weekly" "$BACKUP_DEST/monthly"

log_event() {
    local level="$1"
    local message="$2"
    local ts
    ts="$(date '+%Y-%m-%d %H:%M:%S %z')"
    printf '[%s] [%s] %s\n' "$ts" "$level" "$message" | tee -a "$LOG_FILE"
}

if [ ! -f "$DB_FILE" ]; then
    log_event "ERROR" "STATUS=FAIL reason=\"Base de datos no encontrada\" db=\"$DB_FILE\""
    exit 1
fi

if [ "$REQUIRE_ENCRYPTION" -eq 1 ] && [ -z "$KEY_FILE" ]; then
    log_event "ERROR" "STATUS=FAIL reason=\"SYSTEC_BACKUP_KEY_FILE es obligatorio con --require-encryption\""
    exit 1
fi

if [ -n "$KEY_FILE" ] && [ ! -r "$KEY_FILE" ]; then
    log_event "ERROR" "STATUS=FAIL reason=\"Archivo de clave de cifrado no legible\" key_file=\"$KEY_FILE\""
    exit 1
fi

STAMP="${SYSTEC_BACKUP_NOW:-$(date '+%Y-%m-%d_%H%M%S')}"
DATE_PART="${STAMP%%_*}"
WEEK_KEY="${SYSTEC_BACKUP_WEEK:-$(date -d "$DATE_PART" '+%G-W%V' 2>/dev/null || date '+%G-W%V')}"
MONTH_KEY="${SYSTEC_BACKUP_MONTH:-${DATE_PART:0:7}}"

WORK_DIR="$(mktemp -d "${BACKUP_DEST}/.tmp_backup_XXXXXX")"
cleanup() {
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

SNAPSHOT_DIR="${WORK_DIR}/systec-backup-${STAMP}"
mkdir -p "$SNAPSHOT_DIR"
SNAPSHOT_DB="${SNAPSHOT_DIR}/db.db"

# 1. Snapshot consistente mediante API SQLite3::backup y verificación PRAGMA integrity_check
VERIFY_JSON="$(php -r '
$srcFile = $argv[1];
$dstFile = $argv[2];

$src = new SQLite3($srcFile, SQLITE3_OPEN_READONLY);
$src->busyTimeout(5000);
$dst = new SQLite3($dstFile, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
$dst->busyTimeout(5000);

if (!$src->backup($dst)) {
    fwrite(STDERR, "Fallo en SQLite3::backup\n");
    exit(1);
}
$dst->close();
$src->close();

$checkDb = new SQLite3($dstFile, SQLITE3_OPEN_READONLY);
$integrity = $checkDb->querySingle("PRAGMA integrity_check");
if ($integrity !== "ok") {
    fwrite(STDERR, "PRAGMA integrity_check devolvio: " . var_export($integrity, true) . "\n");
    exit(1);
}

$tables = array("properties", "maintenances", "users", "tickets");
$counts = array();
foreach ($tables as $t) {
    $exists = $checkDb->querySingle("SELECT name FROM sqlite_master WHERE type=\x27table\x27 AND name=\x27" . $t . "\x27");
    $counts[$t] = $exists ? (int) $checkDb->querySingle("SELECT COUNT(*) FROM " . $t) : 0;
}
$checkDb->close();

echo json_encode(array(
    "integrity_check" => $integrity,
    "counts" => $counts,
));
' "$DB_FILE" "$SNAPSHOT_DB")" || {
    log_event "ERROR" "STATUS=FAIL reason=\"Fallo en snapshot o PRAGMA integrity_check\" stamp=\"$STAMP\""
    exit 1
}

# 2. Copiar CSV fuente si existen en storage/
shopt -s nullglob
for csv_file in "${STORAGE_DIR}"/*.csv; do
    cp -p "$csv_file" "$SNAPSHOT_DIR/"
done
shopt -u nullglob

# 3. Escribir manifest.json de verificación (sin secretos ni PII)
printf '{\n  "stamp": "%s",\n  "created_at": "%s",\n  "verification": %s\n}\n' \
    "$STAMP" "$(date -Iseconds)" "$VERIFY_JSON" > "${SNAPSHOT_DIR}/manifest.json"

# 4. Empaquetar y cifrar si corresponde
ARCHIVE_NAME="systec-backup-${STAMP}.tar.gz"
STAGE_ARCHIVE="${WORK_DIR}/${ARCHIVE_NAME}"
tar -C "$WORK_DIR" -czf "$STAGE_ARCHIVE" "systec-backup-${STAMP}"

ENCRYPTED=0
if [ -n "$KEY_FILE" ]; then
    ARCHIVE_NAME="${ARCHIVE_NAME}.enc"
    STAGE_ENC="${WORK_DIR}/${ARCHIVE_NAME}"
    openssl enc -aes-256-cbc -pbkdf2 -salt \
        -in "$STAGE_ARCHIVE" \
        -out "$STAGE_ENC" \
        -pass file:"$KEY_FILE"
    rm -f "$STAGE_ARCHIVE"
    STAGE_ARCHIVE="$STAGE_ENC"
    ENCRYPTED=1
fi

# 5. Publicación atómica en daily/ y promoción a weekly/ y monthly/
FINAL_DAILY="${BACKUP_DEST}/daily/${ARCHIVE_NAME}"
mv -f "$STAGE_ARCHIVE" "$FINAL_DAILY"

EXT=".tar.gz"
if [ "$ENCRYPTED" -eq 1 ]; then
    EXT=".tar.gz.enc"
fi

cp -p "$FINAL_DAILY" "${BACKUP_DEST}/weekly/systec-weekly-${WEEK_KEY}${EXT}.tmp"
mv -f "${BACKUP_DEST}/weekly/systec-weekly-${WEEK_KEY}${EXT}.tmp" "${BACKUP_DEST}/weekly/systec-weekly-${WEEK_KEY}${EXT}"

cp -p "$FINAL_DAILY" "${BACKUP_DEST}/monthly/systec-monthly-${MONTH_KEY}${EXT}.tmp"
mv -f "${BACKUP_DEST}/monthly/systec-monthly-${MONTH_KEY}${EXT}.tmp" "${BACKUP_DEST}/monthly/systec-monthly-${MONTH_KEY}${EXT}"

# 6. Rotación 30 diarias / 12 semanales / 12 mensuales
rotate_dir() {
    local dir="$1"
    local keep="$2"
    local pattern="$3"
    mapfile -t files < <(find "$dir" -maxdepth 1 -type f -name "$pattern" -printf '%f\n' | sort -r)
    local total="${#files[@]}"
    if [ "$total" -gt "$keep" ]; then
        for ((i = keep; i < total; i++)); do
            rm -f "${dir}/${files[$i]}"
        done
    fi
}

rotate_dir "${BACKUP_DEST}/daily" "$DAILY_KEEP" "systec-backup-*"
rotate_dir "${BACKUP_DEST}/weekly" "$WEEKLY_KEEP" "systec-weekly-*"
rotate_dir "${BACKUP_DEST}/monthly" "$MONTHLY_KEEP" "systec-monthly-*"

log_event "INFO" "STATUS=OK integrity_check=ok stamp=\"$STAMP\" encrypted=${ENCRYPTED} file=\"$FINAL_DAILY\" verification=${VERIFY_JSON}"
