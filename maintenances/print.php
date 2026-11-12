<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/inventory_import.php';
require_login();

$title = 'Historiales de mantenimiento';
$db = db();

$getFilter = function (string $key, int $maxLength = 120) {
    if (!isset($_GET[$key]) || !is_string($_GET[$key])) {
        return '';
    }

    $value = trim((string) preg_replace('/\s+/u', ' ', $_GET[$key]));

    return mb_substr($value, 0, $maxLength, 'UTF-8');
};

$category = $getFilter('category');
$search = $getFilter('search', 160);
$statusFilter = $getFilter('status');
$typeFilter = $getFilter('type');
$technicianFilter = $getFilter('technician');
$adscriptionFilter = $getFilter('adscription', 128);
$onlyOverdue = isset($_GET['vencido']) && $_GET['vencido'] === '1';
$next30Filter = isset($_GET['proximo']) && $_GET['proximo'] === '30';

$normalizeDate = function (string $value) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return '';
    }

    $timestamp = strtotime($value . ' 00:00:00');

    return $timestamp !== false ? date('Y-m-d', $timestamp) : '';
};

$dateFromFilter = $normalizeDate($getFilter('desde', 10));
$dateToFilter = $normalizeDate($getFilter('hasta', 10));

$propertiesForAdscription = $db->index(
    'properties',
    array(),
    'id,ident,adscription',
    'id ASC'
);
$identifiers = array_map(function ($property) {
    return trim((string) $property['ident']);
}, $propertiesForAdscription);
$legacyAdscriptionsByIdent = array();
$adscriptionSourceWarning = '';
try {
    $snapshot = legacy_postgres_cached_property_adscriptions($identifiers);
    $legacyAdscriptionsByIdent = $snapshot['adscriptions'];
    if (!empty($snapshot['stale'])) {
        $adscriptionSourceWarning = 'se usó la última consulta guardada de la fuente institucional';
    }
} catch (Throwable $e) {
    error_log('Impresión de mantenimientos no pudo cargar las adscripciones institucionales: ' . $e->getMessage());
    $adscriptionSourceWarning = 'la fuente institucional no estuvo disponible; se usaron datos locales';
}

$adscriptionsByPropertyId = array();
$adscriptionOptions = array();
foreach ($propertiesForAdscription as $property) {
    $propertyId = (int) $property['id'];
    $assignment = property_adscription_assignment($property, $legacyAdscriptionsByIdent);
    $adscriptionsByPropertyId[$propertyId] = $assignment;
    $adscriptionOptions[$assignment['key']] = $assignment['label'];
}

$categoryLabels = array(
    'informatica' => 'Informática',
    'redes' => 'Redes y telecomunicaciones',
    'otros' => 'Otros bienes',
);

$statusLookup = $db->getListMaintenanceStatus();
$statusFilterKey = mb_strtolower($statusFilter);
foreach ($statusLookup as $statusKeyOption => $statusLabelOption) {
    if ($statusFilter !== '' && mb_strtolower($statusLabelOption) === mb_strtolower($statusFilter)) {
        $statusFilterKey = $statusKeyOption;
        break;
    }
}

$typeLookup = $db->getListMaintenanceType();
$typeFilterKey = mb_strtolower($typeFilter);
foreach ($typeLookup as $typeKeyOption => $typeLabelOption) {
    if ($typeFilter !== '' && mb_strtolower($typeLabelOption) === mb_strtolower($typeFilter)) {
        $typeFilterKey = $typeKeyOption;
        break;
    }
}

$filterSummary = array();
if ($category !== '') {
    $filterSummary[] = array('Categoría', $categoryLabels[$category] ?? $category);
}
if ($statusFilter !== '') {
    $filterSummary[] = array('Estado', $statusLookup[$statusFilterKey] ?? $statusFilter);
}
if ($typeFilter !== '') {
    $filterSummary[] = array('Tipo', $typeLookup[$typeFilterKey] ?? $typeFilter);
}
if ($technicianFilter !== '') {
    $filterSummary[] = array('Técnico', $technicianFilter);
}
if ($adscriptionFilter !== '') {
    $filterSummary[] = array(
        'Unidad de adscripción',
        $adscriptionOptions[$adscriptionFilter] ?? 'Unidad seleccionada (no disponible)'
    );
    if ($adscriptionSourceWarning !== '') {
        $filterSummary[] = array('Fuente de adscripción', $adscriptionSourceWarning);
    }
}
if ($dateFromFilter !== '' || $dateToFilter !== '') {
    $dateFromLabel = $dateFromFilter !== '' ? date('d-m-Y', strtotime($dateFromFilter)) : '';
    $dateToLabel = $dateToFilter !== '' ? date('d-m-Y', strtotime($dateToFilter)) : '';
    if ($dateFromLabel !== '' && $dateToLabel !== '') {
        $dateRangeLabel = $dateFromLabel . ' a ' . $dateToLabel;
    } elseif ($dateFromLabel !== '') {
        $dateRangeLabel = 'Desde ' . $dateFromLabel;
    } else {
        $dateRangeLabel = 'Hasta ' . $dateToLabel;
    }
    $filterSummary[] = array('Fecha', $dateRangeLabel);
}
if ($onlyOverdue) {
    $filterSummary[] = array('Condición', 'Solo vencidos');
}
if ($next30Filter) {
    $filterSummary[] = array('Condición', 'Próximo mantenimiento en 30 días');
}
if ($search !== '') {
    $filterSummary[] = array('Búsqueda', '«' . $search . '»');
}

$criteriaParts = array();
foreach ($filterSummary as $filterItem) {
    $criteriaParts[] = $filterItem[0] . ': ' . $filterItem[1];
}
$criteriaText = $criteriaParts !== array()
    ? implode(' · ', $criteriaParts)
    : 'Sin filtros aplicados';

$searchTerms = array();
if ($search !== '') {
    foreach (preg_split('/\s+/u', mb_strtolower($search, 'UTF-8')) as $term) {
        if ($term !== '') {
            $searchTerms[] = $term;
        }
    }
}

$maintenances = $db->index('maintenances', array(), '*', 'date ASC');
$historyCounts = array();
foreach ($maintenances as $maintenanceRow) {
    $propertyIdRow = (int) $maintenanceRow['property_id'];
    $historyCounts[$propertyIdRow] = ($historyCounts[$propertyIdRow] ?? 0) + 1;
}
$propertyCache = array();
$selectedPropertyIds = array();
$matchedCount = 0;

foreach ($maintenances as $maintenance) {
    $propertyId = (int) $maintenance['property_id'];
    if (!array_key_exists($propertyId, $propertyCache)) {
        $propertyCache[$propertyId] = $db->getFirstForId('properties', $propertyId);
    }

    $property = $propertyCache[$propertyId];
    if (!$property) {
        continue;
    }

    $dateRaw = trim((string) $maintenance['date']);
    $dateTimestamp = $dateRaw !== '' ? strtotime($dateRaw) : false;
    $dateLabel = $dateTimestamp !== false ? date('d-m-Y H:i', $dateTimestamp) : '—';

    $nextRaw = trim((string) $maintenance['next_maintenance_date']);
    $nextTimestamp = $nextRaw !== '' ? strtotime($nextRaw) : false;
    $nextLabel = $nextTimestamp !== false ? date('d-m-Y', $nextTimestamp) : '—';

    $hoursLabel = trim((string) $maintenance['time_taken']) !== ''
        ? (string) $maintenance['time_taken']
        : '—';

    $statusKey = mb_strtolower(trim((string) $maintenance['status']));
    $vencido = ($statusKey !== 'finalizado' && $nextTimestamp !== false && $nextTimestamp < time());
    $maintenanceDateIso = $dateTimestamp !== false ? date('Y-m-d', $dateTimestamp) : '';
    $next30 = $nextTimestamp !== false
        && $nextTimestamp >= strtotime('today')
        && $nextTimestamp <= strtotime('+30 days');

    if ($dateFromFilter !== '' && ($maintenanceDateIso === '' || $maintenanceDateIso < $dateFromFilter)) {
        continue;
    }

    if ($dateToFilter !== '' && ($maintenanceDateIso === '' || $maintenanceDateIso > $dateToFilter)) {
        continue;
    }

    if ($next30Filter && !$next30) {
        continue;
    }

    if ($category !== '' && property_category($property) !== $category) {
        continue;
    }

    if ($adscriptionFilter !== ''
        && ($adscriptionsByPropertyId[$propertyId]['key'] ?? '__unassigned__') !== $adscriptionFilter) {
        continue;
    }

    $typeLabel = texto_tipo((string) $maintenance['type']);
    if ($typeFilter !== '' && mb_strtolower(trim((string) $maintenance['type'])) !== $typeFilterKey) {
        continue;
    }

    $statusLabel = texto_mantenimiento((string) $maintenance['status']);
    if ($statusFilter !== '' && $statusKey !== $statusFilterKey) {
        continue;
    }

    if ($technicianFilter !== '' && (string) $maintenance['technician'] !== $technicianFilter) {
        continue;
    }

    if ($onlyOverdue && !$vencido) {
        continue;
    }

    if ($searchTerms !== array()) {
        $haystack = mb_strtolower(implode(' ', array(
            (string) $maintenance['code'],
            $typeLabel,
            (string) $maintenance['description'],
            $dateLabel,
            $hoursLabel,
            (string) $maintenance['technician'],
            $statusLabel,
            $nextLabel,
        )), 'UTF-8');

        $matchesSearch = true;
        foreach ($searchTerms as $term) {
            if (mb_strpos($haystack, $term, 0, 'UTF-8') === false) {
                $matchesSearch = false;
                break;
            }
        }

        if (!$matchesSearch) {
            continue;
        }
    }

    $matchedCount++;
    if (!in_array($propertyId, $selectedPropertyIds, true)) {
        $selectedPropertyIds[] = $propertyId;
    }
}

$histories = array();
foreach ($selectedPropertyIds as $propertyId) {
    $histories[] = $propertyCache[$propertyId];
}
usort($histories, function (array $left, array $right): int {
    return strcmp(property_code($left), property_code($right));
});

$no_footer = true;
$header_file = __DIR__ . '/letterhead.php';

ob_start(); ?>

    <div class="container-fluid maintenance-batch-sheet text-start">
        <?php if ($histories === array()) : ?>
            <section class="batch-empty" aria-label="Sin resultados">
                <h2>Sin resultados</h2>
                <p>Ningún mantenimiento coincide con los criterios aplicados.</p>
            </section>
        <?php else : ?>
            <?php foreach ($histories as $property) : ?>
                <?php
                $propertyIdValue = (int) $property['id'];
                $historyCount = $historyCounts[$propertyIdValue] ?? 0;
                ?>
                <section class="batch-history" aria-labelledby="batchPropertyHeading<?= e((string) $propertyIdValue) ?>">
                    <div class="batch-history-header">
                        <div class="batch-history-heading">
                            <h2 id="batchPropertyHeading<?= e((string) $propertyIdValue) ?>">
                                Bien <?= e(property_code($property)) ?>
                            </h2>
                            <?php if (trim((string) $property['description']) !== '') : ?>
                                <p class="batch-history-description"><?= e($property['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <p class="batch-history-count">
                            <?= e((string) $historyCount) ?> <?= $historyCount === 1 ? 'mantenimiento' : 'mantenimientos' ?>
                        </p>
                    </div>

                    <div class="batch-history-letterhead">
                        <?php include __DIR__ . '/../historials/membrete.php'; ?>
                    </div>

                    <h3 class="batch-history-title">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        Historial de mantenimiento
                    </h3>
                    <?php include __DIR__ . '/../historials/report.php'; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <style nonce="<?= e(systec_csp_nonce()) ?>">
        body {
            background-color: #fff;
        }

        .app-header {
            padding: 0 !important;
            background-color: #fff !important;
            border-bottom: 0 !important;
            box-shadow: none !important;
        }

        .app-header > .container-fluid {
            max-width: 210mm;
            margin: 0 auto;
            padding: 1.5rem 1.75rem 0;
        }

        .app-main {
            margin-top: 0;
            padding-left: 0;
            padding-right: 0;
        }

        .maintenance-batch-sheet {
            max-width: 210mm;
            margin: 0 auto;
            padding: 0 1.75rem 2.5rem;
            background-color: #fff;
            color: #212529;
            font-size: .9375rem;
        }

        .batch-letterhead {
            padding-bottom: .75rem;
            margin-bottom: 1.25rem;
            border-bottom: 2px solid #212529;
        }

        .batch-letterhead-top {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: .75rem 1.5rem;
        }

        .batch-office {
            margin: 0;
            font-size: .8125rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .batch-location {
            margin: 0;
            font-size: .8125rem;
            color: #495057;
        }

        .batch-meta {
            margin: 0;
            font-size: .75rem;
            color: #495057;
            text-align: right;
        }

        .batch-meta p {
            margin: 0;
        }

        .batch-title {
            margin: .9rem 0 .15rem;
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .batch-subtitle {
            margin: 0;
            font-size: .8125rem;
            color: #495057;
        }

        .batch-criteria {
            margin: .5rem 0 0;
            font-size: .75rem;
            color: #343a40;
        }

        .batch-empty {
            padding: 1rem 1.25rem;
            border: 1px solid #ced4da;
            font-size: .875rem;
        }

        .batch-empty h2 {
            margin: 0 0 .25rem;
            font-size: 1rem;
            font-weight: 700;
        }

        .batch-empty p {
            margin: 0;
            color: #495057;
        }

        .batch-history {
            margin-top: 1.5rem;
        }

        .batch-history + .batch-history {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #dee2e6;
        }

        .batch-history-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: .25rem 1rem;
            margin-bottom: .75rem;
            break-after: avoid;
            page-break-after: avoid;
        }

        .batch-history-heading {
            min-width: 0;
        }

        .batch-history-heading h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .batch-history-description {
            margin: .15rem 0 0;
            font-size: .8125rem;
            color: #495057;
            overflow-wrap: anywhere;
        }

        .batch-history-count {
            margin: 0;
            font-size: .75rem;
            color: #495057;
            white-space: nowrap;
        }

        .batch-history-letterhead #historyLetterhead {
            padding: .75rem 1rem;
            border: 1px solid #ced4da;
            border-radius: .35rem;
            background-color: #f8f9fa;
        }

        .batch-history-letterhead #historyLetterhead h5 {
            font-size: 1rem;
            font-weight: 600;
        }

        .batch-history-letterhead #historyLetterhead .small {
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .batch-history-title {
            display: flex;
            align-items: center;
            gap: .35rem;
            margin: .9rem 0 .5rem;
            padding-bottom: .35rem;
            border-bottom: 1px solid #dee2e6;
            font-size: .8125rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #495057;
            break-after: avoid;
            page-break-after: avoid;
        }

        .batch-history table {
            font-size: .8125rem;
        }

        @page {
            margin: 14mm;
        }

        @media print {
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                color: #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body.d-flex {
                display: block !important;
            }

            .app-shell,
            .app-main {
                display: block !important;
                width: 100% !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .app-header > .container-fluid {
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .maintenance-batch-sheet {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }

            .batch-letterhead {
                padding-bottom: .5rem;
                margin-bottom: 1rem;
            }

            .batch-title {
                font-size: 1.2rem;
            }

            .batch-history + .batch-history {
                margin-top: 0;
                padding-top: 0;
                border-top: 0;
                break-before: page;
                page-break-before: always;
            }

            .batch-history-letterhead #historyLetterhead {
                border-radius: .35rem;
                background-color: #f8f9fa;
            }

            .batch-history-letterhead #historyLetterhead > .d-flex {
                flex-direction: row !important;
                align-items: flex-start !important;
                justify-content: space-between !important;
                gap: .25rem 1rem !important;
            }

            .batch-history-letterhead #historyLetterhead > .d-flex > p {
                margin-left: auto;
                text-align: right !important;
                white-space: nowrap;
            }

            .batch-history-letterhead #historyLetterhead dl.row {
                --bs-gutter-x: .5rem;
            }

            .batch-history-letterhead #historyLetterhead dl.row > .col {
                flex: 0 0 auto;
                width: 33.33333333%;
            }

            .batch-history .table-responsive {
                overflow: visible !important;
                border: 0 !important;
            }

            .batch-history table {
                font-size: .75rem !important;
            }

            .batch-history tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>

<?php
$main = ob_get_clean();
include __DIR__ . '/../include/layout.php';
