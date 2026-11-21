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

/** Crea una notificación toast para el componente compartido. */
function toast_notification(string $type, string $message): array
{
    if (!in_array($type, array('success', 'info', 'warning', 'danger'), true)) {
        throw new InvalidArgumentException('Tipo de notificación toast no admitido.');
    }

    return array('type' => $type, 'message' => $message);
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
            return 'text-bg-danger';
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

/** Adscripción de un bien, priorizando la fuente institucional y usando el dato local como respaldo. */
function property_adscription_assignment(array $property, array $legacyAdscriptionsByIdent = array()): array
{
    $ident = str_pad(trim((string) ($property['ident'] ?? '')), 4, '0', STR_PAD_LEFT);
    $assignment = $legacyAdscriptionsByIdent[$ident] ?? null;
    if (is_array($assignment)
        && isset($assignment['key'], $assignment['label'])
        && is_string($assignment['key'])
        && is_string($assignment['label'])
        && trim($assignment['label']) !== '') {
        return array('key' => $assignment['key'], 'label' => $assignment['label']);
    }

    $localAdscription = trim((string) ($property['adscription'] ?? ''));
    if ($localAdscription === '') {
        return array('key' => '__unassigned__', 'label' => 'Sin adscripción');
    }

    return array(
        'key' => 'local:' . hash('sha256', mb_strtolower($localAdscription)),
        'label' => $localAdscription,
    );
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

/** Formatea una antigüedad en segundos a una etiqueta legible en español. */
function format_relative_age(?int $ageSeconds): string
{
    if ($ageSeconds === null) {
        return '—';
    }

    $seconds = max(0, $ageSeconds);
    if ($seconds < 60) {
        return 'hace menos de 1 min';
    }
    if ($seconds < 3600) {
        $minutes = (int) floor($seconds / 60);
        return 'hace ' . $minutes . ' min';
    }
    if ($seconds < 86400) {
        $hours = (int) floor($seconds / 3600);
        return 'hace ' . $hours . ' h';
    }

    $days = (int) floor($seconds / 86400);
    return $days === 1 ? 'hace 1 día' : 'hace ' . $days . ' días';
}

/** Etiqueta legible del estado laboral/asignación de un responsable o usuario asignado. */
function assignment_status_label(string $status, string $emptyFallback = 'Pendiente de sincronizar'): string
{
    $statusLabels = array(
        'ACTIVO' => 'Activo',
        'INACTIVO' => 'Inactivo',
        'JUBILADO' => 'Jubilado',
        'REMOCION' => 'Remoción',
        'RENUNCIA' => 'Renuncia',
        'VACACIONES' => 'Vacaciones',
        'SIN ASIGNAR' => 'Sin asignar',
        'SIN FICHA' => 'Sin ficha',
        'SIN ESTADO' => 'Estado no registrado',
    );

    $normalized = mb_strtoupper(trim($status), 'UTF-8');
    if (isset($statusLabels[$normalized])) {
        return $statusLabels[$normalized];
    }

    return $normalized !== '' ? ucfirst(mb_strtolower($normalized, 'UTF-8')) : $emptyFallback;
}

/** Construye una URL de retorno segura a dashboard.php preservando solo filtros permitidos. */
function dashboard_return_url(array $input, string $fallbackSearch = ''): string
{
    $returnQuery = array();
    $allowedReturnFilters = array(
        'category' => array('informatica', 'redes', 'otros'),
        'status' => array('__attention__', 'BUENO', 'REGULAR', 'MALO', 'MUY MALO'),
    );

    foreach ($allowedReturnFilters as $filter => $allowedValues) {
        $postKey = 'return_' . $filter;
        if (isset($input[$postKey]) && is_string($input[$postKey])) {
            $value = trim($input[$postKey]);
            if ($value !== '' && in_array($value, $allowedValues, true)) {
                $returnQuery[$filter] = $value;
            }
        }
    }

    if (isset($input['return_adscription'])
        && is_string($input['return_adscription'])
        && preg_match('/^[A-Za-z0-9_.:\/-]{1,128}$/D', $input['return_adscription'])) {
        $returnQuery['adscription'] = $input['return_adscription'];
    }

    foreach (array('responsible', 'assigned') as $filter) {
        $postKey = 'return_' . $filter;
        if (isset($input[$postKey])
            && is_string($input[$postKey])
            && preg_match('/^[a-f0-9]{64}$/D', $input[$postKey])) {
            $returnQuery[$filter] = $input[$postKey];
        }
    }

    if (isset($input['return_search']) && is_string($input['return_search'])) {
        $search = trim($input['return_search']);
        if ($search !== '') {
            $returnQuery['search'] = $search;
        }
    }

    if ($returnQuery === array() && trim($fallbackSearch) !== '') {
        $returnQuery['search'] = trim($fallbackSearch);
    }

    return url('dashboard.php') . ($returnQuery ? '?' . http_build_query($returnQuery) : '');
}

/** Construye una URL de retorno segura a maintenances.php preservando solo filtros permitidos. */
function maintenances_return_url(string $rawReturnQuery = '', array $extraParams = array()): string
{
    $allowedReturnParams = array(
        'category', 'adscription', 'responsible', 'assigned', 'search',
        'estado', 'tipo', 'tecnico', 'desde', 'hasta', 'proximo', 'vencido',
    );
    $returnParams = array();

    if ($rawReturnQuery !== '') {
        $parsed = array();
        parse_str(ltrim($rawReturnQuery, '?'), $parsed);
        foreach ($allowedReturnParams as $key) {
            if (!isset($parsed[$key]) || !is_string($parsed[$key])) {
                continue;
            }
            $value = trim($parsed[$key]);
            if ($value === '' || strlen($value) > 200) {
                continue;
            }
            if (($key === 'responsible' || $key === 'assigned') && !preg_match('/^[a-f0-9]{64}$/D', $value)) {
                continue;
            }
            if (($key === 'desde' || $key === 'hasta') && !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                continue;
            }
            $returnParams[$key] = $value;
        }
    }

    foreach ($extraParams as $key => $value) {
        if (is_string($key) && $key !== '' && $value !== null && $value !== '') {
            $returnParams[$key] = (string) $value;
        }
    }

    return url('maintenances.php') . ($returnParams ? '?' . http_build_query($returnParams) : '');
}

/** Construye una URL de retorno segura a tickets.php preservando solo filtros permitidos. */
function tickets_return_url(string $rawReturnQuery = ''): string
{
    $allowedStatuses = array('abiertos', 'iniciado', 'en_progreso', 'con_retraso', 'en_espera', 'finalizado');
    $returnParams = array();

    if ($rawReturnQuery !== '') {
        $parsed = array();
        parse_str(ltrim($rawReturnQuery, '?'), $parsed);

        if (isset($parsed['status']) && is_string($parsed['status'])) {
            $status = trim($parsed['status']);
            if ($status !== '' && in_array($status, $allowedStatuses, true)) {
                $returnParams['status'] = $status;
            }
        }
        if (isset($parsed['search']) && is_string($parsed['search'])) {
            $search = trim($parsed['search']);
            if ($search !== '' && strlen($search) <= 200) {
                $returnParams['search'] = $search;
            }
        }
    }

    return url('tickets.php') . ($returnParams ? '?' . http_build_query($returnParams) : '');
}
