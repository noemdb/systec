#!/usr/bin/env bash
# ui-better page audit — heuristic checks for SysTec pages.
# Usage: audit-page.sh path/to/page.php
# Exit code: 0 = no failures, 1 = at least one failure (warnings do not fail).

set -u

FILE=""
MODE="page"
if [ "${1:-}" = "--fragment" ]; then
    MODE="fragment"
    shift
fi
FILE="${1:-}"
if [ -z "$FILE" ] || [ "$FILE" = "-h" ] || [ "$FILE" = "--help" ]; then
    printf 'Uso: %s [--fragment] path/to/page.php\n' "$0"
    printf '  --fragment  audita un include/parcial (omite comprobaciones de página completa)\n'
    exit 0
fi
if [ ! -f "$FILE" ]; then
    printf 'No existe el archivo: %s\n' "$FILE" >&2
    exit 1
fi

FAILURES=0
WARNINGS=0

ok()   { printf '  OK    %s\n' "$1"; }
warn() { printf '  WARN  %s\n' "$1"; WARNINGS=$((WARNINGS + 1)); }
fail() { printf '  FAIL  %s\n' "$1"; FAILURES=$((FAILURES + 1)); }

count() { grep -c -E -- "$1" "$FILE" 2>/dev/null || true; }
has()   { grep -q -E -- "$1" "$FILE" 2>/dev/null; }

if [ "$MODE" = "fragment" ]; then
    printf '\nui-better audit (fragmento): %s\n\n' "$FILE"
else
    printf '\nui-better audit (página): %s\n\n' "$FILE"
fi

# ---------------------------------------------------------------- syntax
printf 'PHP\n'
if command -v php >/dev/null 2>&1; then
    if php -l "$FILE" >/dev/null 2>&1; then
        ok "php -l"
    else
        fail "php -l (ver detalle: php -l $FILE)"
    fi
else
    warn "php no está disponible; se omite php -l"
fi

# ---------------------------------------------------------------- shell
if [ "$MODE" = "page" ]; then
    if has 'ob_start\(' && has 'ob_get_clean\(' && has "layout\.php"; then
        ok "shell con ob_start / ob_get_clean / layout.php"
    else
        fail "no usa el patrón ob_start() → \$main → include layout.php"
    fi

    if has 'container-fluid'; then
        ok "contenedor .container-fluid"
    else
        warn "no se encontró .container-fluid"
    fi

    if has '<h1[^>]*class="[^"]*h3'; then
        ok "título de página h1.h3"
    elif has '<h1'; then
        warn "hay <h1> pero no con class=\"h3\" (canónico: h1.h3)"
    else
        fail "no hay <h1>"
    fi

    if [ "$(count '<h1')" -gt 1 ]; then
        warn "más de un <h1> ($(count '<h1'))"
    fi
else
    printf 'Modo fragmento: se omiten las comprobaciones de shell, .container-fluid y <h1>.\n'
fi

# ---------------------------------------------------------------- layout hygiene
printf '\nLayout\n'
if has 'text-center' && ! has 'text-start'; then
    warn "text-center sin contrapeso text-start en el contenido"
fi

inline_styles=$(count 'style="')
if [ "$inline_styles" -gt 0 ]; then
    warn "$inline_styles atributo(s) style=\"...\" inline; preferir clases o <style nonce>"
else
    ok "sin style=\"...\" inline"
fi

if has '!important'; then
    warn "$(count '!important') uso(s) de !important"
else
    ok "sin !important"
fi

if has 'border-3'; then
    warn "usa border-3; el canónico para KPIs es border-4"
fi
if has 'text-muted'; then
    warn "usa text-muted; el canónico es text-body-secondary"
fi
if has 'h1[^>]*class="[^"]*h2'; then
    warn "título con h1.h2; unificar a h1.h3"
fi

if has 'style="[^"]*(width|height):[[:space:]]*[0-9]+px'; then
    warn "ancho/alto fijo en px dentro de style inline"
fi

# ---------------------------------------------------------------- tables
printf '\nTablas\n'
tables=$(count '<table')
if [ "$tables" -eq 0 ]; then
    ok "sin tablas en este archivo (los includes no se analizan aquí)"
else
    if has 'table-responsive'; then
        ok "$tables tabla(s) con contenedor .table-responsive"
    else
        fail "$tables tabla(s) sin .table-responsive"
    fi

    if has '<caption[^>]*visually-hidden'; then
        ok "caption accesible (visually-hidden)"
    else
        warn "tabla(s) sin <caption class=\"visually-hidden\">"
    fi

    if has '<th[^>]*scope='; then
        ok "encabezados con scope"
    else
        warn "encabezados de tabla sin scope"
    fi

    if has 'table-hover'; then
        ok "tabla con table-hover"
    else
        warn "tabla sin table-hover"
    fi

    if has 'new DataTable|DataTable\('; then
        if has 'language[[:space:]]*:'; then
            ok "DataTables con bloque language"
        else
            warn "DataTables sin language (la UI saldrá en inglés)"
        fi
    fi
fi

# ---------------------------------------------------------------- forms
printf '\nFormularios\n'
forms=$(count '<form')
if [ "$forms" -eq 0 ]; then
    ok "sin formularios"
else
    post_forms=$(count '<form[^>]*method="post"')
    csrf=$(count 'csrf_field\(')
    if [ "$csrf" -ge "$post_forms" ]; then
        ok "$post_forms formulario(s) POST con csrf_field()"
    else
        warn "$post_forms formulario(s) POST y $csrf csrf_field(); verificar"
    fi

    if has '<label[^>]*for='; then
        ok "labels asociados con for"
    else
        warn "sin <label for=...> en este archivo; si los campos vienen de includes, audítalos también"
    fi
fi

if has 'onclick=|onchange=|onsubmit='; then
    warn "handlers inline (onclick/onchange/onsubmit); mover a listeners"
else
    ok "sin handlers inline"
fi

# ---------------------------------------------------------------- accessibility
printf '\nAccesibilidad\n'
if has 'aria-labelledby|aria-label'; then
    ok "regiones/controles con aria-label(ledby)"
else
    warn "sin aria-label/aria-labelledby"
fi

if has '<i class="bi' && ! has 'aria-hidden="true"'; then
    warn "iconos sin aria-hidden=\"true\""
fi

if has 'btn-close' && ! has 'btn-close[^>]*aria-label='; then
    warn "btn-close sin aria-label"
fi

if has '<img' && ! has '<img[^>]*alt='; then
    warn "imágenes sin atributo alt"
fi

if has 'role="status"|aria-live'; then
    ok "feedback dinámico anunciado (role=status / aria-live)"
else
    warn "sin regiones aria-live para contadores/feedback"
fi

# ---------------------------------------------------------------- modals
modals=$(count 'class="modal fade"')
if [ "$modals" -gt 0 ]; then
    printf '\nModales\n'
    labelled=$(count 'aria-labelledby=')
    if [ "$labelled" -ge "$modals" ]; then
        ok "$modals modal(es) con aria-labelledby"
    else
        warn "$modals modal(es) y $labelled aria-labelledby"
    fi

    if has 'modal-dialog-scrollable'; then
        ok "algún modal con scroll interno"
    else
        warn "modales sin modal-dialog-scrollable (revisar formularios largos)"
    fi
fi

# ---------------------------------------------------------------- includes
INCLUDES=$(grep -oE "include[[:space:]]+__DIR__[[:space:]]*\.[[:space:]]*'[^']+'" "$FILE" 2>/dev/null | sed -E "s/.*'([^']+)'/\1/" || true)
if [ -n "$INCLUDES" ]; then
    printf '\nIncludes (no auditados aquí)\n'
    while IFS= read -r inc; do
        [ -n "$inc" ] && printf '  - %s\n' "$inc"
    done <<< "$INCLUDES"
    printf '  Ejecuta este script también sobre los includes que contengan tablas, formularios o modales.\n'
fi

# ---------------------------------------------------------------- summary
printf '\nResultado: %d fallo(s), %d advertencia(s)\n' "$FAILURES" "$WARNINGS"
if [ "$FAILURES" -gt 0 ]; then
    printf 'Revisa los FAIL antes de cerrar el refactor.\n\n'
    exit 1
fi
printf 'Sin fallos bloqueantes.\n\n'
