<?php
/**
 * Helpers de presentación y reglas simples compartidas.
 *
 * Funciones puras: no acceden a la base de datos ni a la sesión.
 * Compatibles con PHP 7.4 (sin match, sin str_contains).
 */

/** Escape seguro para HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Serializa un valor para incrustarlo en JavaScript sin romper el HTML. */
function js($value): string
{
    return (string) json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
}

/** Badges de tablas en pill redondeado sutil */
function sat_badge(string $classKey, string $texto): string
{
    return '<span class="badge rounded-pill ' . e($classKey) . '">' . e($texto) . '</span>';
}

/** Estado del BIEN (properties.status): BUENO | REGULAR | MALO | MUY MALO */
function badge_clase_bien(string $status): string
{
    switch (mb_strtoupper(trim($status))) {
        case 'BUENO':
            return 'text-bg-success';
        case 'REGULAR':
            return 'text-bg-warning';
        case 'MALO':
            return 'text-bg-danger';
        case 'MUY MALO':
            return 'text-bg-danger-emphasis';
        default:
            return 'text-bg-secondary';
    }
}

/** Etiqueta legible y consistente del estado de un bien. */
function property_status_label(string $status): string
{
    switch (mb_strtoupper(trim($status))) {
        case 'BUENO':
            return 'Bueno';
        case 'REGULAR':
            return 'Regular';
        case 'MALO':
            return 'Malo';
        case 'MUY MALO':
            return 'Muy malo';
        default:
            return trim($status);
    }
}

/** Estado del MANTENIMIENTO: iniciado | revisión | finalizado */
function badge_clase_mantenimiento(string $status): string
{
    switch (mb_strtolower(trim($status))) {
        case 'iniciado':
            return 'text-bg-info';
        case 'revision':
            return 'text-bg-warning';
        case 'finalizado':
            return 'text-bg-success';
        default:
            return 'text-bg-secondary';
    }
}

/** Etiqueta legible del estado de mantenimiento (iniciado → Iniciado, revision → Revisión) */
function texto_mantenimiento(string $status): string
{
    switch (mb_strtolower(trim($status))) {
        case 'iniciado':
            return 'Iniciado';
        case 'revision':
            return 'Revisión';
        case 'finalizado':
            return 'Finalizado';
        default:
            return ucfirst(trim($status));
    }
}

/** Tipo de mantenimiento: preventivo | correctivo | predictivo */
function badge_clase_tipo(string $type): string
{
    switch (mb_strtolower(trim($type))) {
        case 'preventivo':
            return 'text-bg-primary';
        case 'correctivo':
            return 'text-bg-danger';
        case 'predictivo':
            return 'text-bg-info';
        case 'evaluativo':
            return 'text-bg-secondary';
        default:
            return 'text-bg-secondary';
    }
}

/** Etiqueta legible del tipo de mantenimiento */
function texto_tipo(string $type): string
{
    if (trim($type) === '') {
        return '';
    }

    switch (mb_strtolower(trim($type))) {
        case 'preventivo':
            return 'Preventivo';
        case 'correctivo':
            return 'Correctivo';
        case 'predictivo':
            return 'Predictivo';
        case 'evaluativo':
            return 'Evaluativo';
        default:
            return ucfirst(trim($type));
    }
}

/** Siguiente estado del bien dentro del ciclo actual de la UI. */
function property_next_status(string $status): string
{
    switch (mb_strtoupper(trim($status))) {
        case 'BUENO':
            return 'MALO';
        case 'REGULAR':
            return 'BUENO';
        case 'MALO':
            return 'REGULAR';
        default:
            return 'BUENO';
    }
}

/** Categoría del bien según su código de grupo, subgrupo y sección. */
function property_category(array $property): string
{
    $group = trim((string) ($property['grupo'] ?? ''));
    $subgroup = str_pad(trim((string) ($property['subgrupo'] ?? '')), 4, '0', STR_PAD_LEFT);
    $section = str_pad(trim((string) ($property['seccion'] ?? '')), 3, '0', STR_PAD_LEFT);

    if ($group === '2' && ($subgroup === '0101' || ($subgroup === '0012' && $section === '005'))) {
        return 'informatica';
    }

    if ($group === '2' && $subgroup === '0005' && in_array($section, array('003', '008', '037'), true)) {
        return 'redes';
    }

    return 'otros';
}

/** Código legible de un bien, con el mismo formato en todas las vistas. */
function property_code(array $property): string
{
    return trim((string) ($property['grupo'] ?? ''))
        . '-' . str_pad(trim((string) ($property['subgrupo'] ?? '')), 4, '0', STR_PAD_LEFT)
        . '-' . str_pad(trim((string) ($property['seccion'] ?? '')), 4, '0', STR_PAD_LEFT)
        . '-' . str_pad(trim((string) ($property['ident'] ?? '')), 4, '0', STR_PAD_LEFT);
}

/** Transiciones permitidas para un mantenimiento. */
function maintenance_transition_allowed(string $from, string $to): bool
{
    $from = mb_strtolower(trim($from));
    $to = mb_strtolower(trim($to));

    if ($from === $to) {
        return true;
    }

    $allowed = array(
        'iniciado' => array('revision', 'finalizado'),
        'revision' => array('finalizado'),
        'finalizado' => array('revision'),
    );

    return isset($allowed[$from]) && in_array($to, $allowed[$from], true);
}
