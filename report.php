<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';
require_login();
require_non_personal_area();

$title = 'Reporte de bienes dañados';
$db = db();
$properties = $db->indexWithMaintenances('properties.*', 'date ASC');

$propertyIds = array();
$maloCount = 0;
$veryBadCount = 0;
foreach ($properties as $propertyRow) {
    $propertyIds[] = (int) $propertyRow['id'];
    $statusValue = mb_strtoupper(trim((string) $propertyRow['status']));
    if ($statusValue === 'MALO') {
        $maloCount++;
    }
    if ($statusValue === 'MUY MALO') {
        $veryBadCount++;
    }
}
$identifiedCount = count($properties);

// Precarga en una sola consulta SQL todos los mantenimientos agrupados por bien (evita 2N+1)
$maintenancesByProperty = $db->maintenances()->maintenancesGroupedByPropertyIds($propertyIds);

// Resuelve adscripciones desde caché local o respaldo en SQLite sin bloquear con PostgreSQL
$localCache = legacy_postgres_read_local_cache();
$legacyAdscriptionsByIdent = isset($localCache['adscriptions']) && is_array($localCache['adscriptions'])
    ? $localCache['adscriptions']
    : array();

ob_start();
?>
<div class="container-fluid text-start damaged-report">
    <header class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-3 report-actions"
            aria-labelledby="damaged-report-title">
        <div class="d-flex align-items-start gap-3 damaged-report-heading">
            <span class="rounded-circle bg-danger-subtle text-danger p-2 flex-shrink-0" aria-hidden="true">
                <i class="bi bi-exclamation-triangle fs-4"></i>
            </span>
            <div>
                <h1 class="h3 mb-1" id="damaged-report-title">Reporte de bienes dañados</h1>
                <p class="text-body-secondary mb-1">
                    Bienes en condición <strong>Malo</strong> o <strong>Muy malo</strong> con al menos una nota de
                    desincorporación en su historial de mantenimiento.
                </p>
                <?php if ($properties !== array()) : ?>
                    <p class="small text-body-secondary mb-0">
                        <i class="bi bi-printer me-1" aria-hidden="true"></i>
                        Cada bien visible se imprime en una página independiente.
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 flex-shrink-0 align-self-lg-start">
            <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a bienes
            </a>
            <?php if ($properties !== array()) : ?>
                <button type="button" class="btn btn-outline-success" id="exportDamagedReportCsv"
                        title="Exportar los bienes visibles a CSV">
                    <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Exportar CSV
                </button>
                <button type="button" class="btn btn-primary" id="printDamagedReport">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir reporte
                </button>
            <?php endif; ?>
        </div>
    </header>

    <section class="row g-3 mb-3 report-summary" aria-label="Resumen de bienes dañados">
        <div class="col-12 col-md-4">
            <button type="button"
                    class="card h-100 w-100 border-start border-4 border-primary shadow-sm text-start text-body js-damaged-kpi"
                    data-filter-status="" aria-pressed="true"
                    title="Mostrar todos los bienes identificados">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-box-seam fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Bienes identificados</p>
                        <p class="h3 mb-0"><?= e((string) $identifiedCount) ?></p>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-12 col-md-4">
            <button type="button"
                    class="card h-100 w-100 border-start border-4 border-warning shadow-sm text-start text-body js-damaged-kpi"
                    data-filter-status="MALO" aria-pressed="false"
                    title="Filtrar bienes en condición mala">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-warning-subtle text-warning p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Condición mala</p>
                        <p class="h3 mb-0"><?= e((string) $maloCount) ?></p>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-12 col-md-4">
            <button type="button"
                    class="card h-100 w-100 border-start border-4 border-danger shadow-sm text-start text-body js-damaged-kpi"
                    data-filter-status="MUY MALO" aria-pressed="false"
                    title="Filtrar bienes en condición muy mala">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-danger-subtle text-danger p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-exclamation-octagon fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Condición muy mala</p>
                        <p class="h3 mb-0"><?= e((string) $veryBadCount) ?></p>
                    </div>
                </div>
            </button>
        </div>
    </section>

    <?php if ($properties === array()) : ?>
        <section class="card shadow-sm border-0 text-center" aria-label="Sin bienes para reportar">
            <div class="card-body py-4" role="status" aria-live="polite">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success p-3 mb-3"
                      aria-hidden="true">
                    <i class="bi bi-check-circle fs-3"></i>
                </span>
                <h2 class="h5 mb-1">No hay bienes para reportar</h2>
                <p class="text-body-secondary mb-0">
                    Ningún bien cumple los criterios del reporte: condición mala o muy mala con nota de desincorporación.
                </p>
            </div>
        </section>
    <?php else : ?>
        <section class="card shadow-sm border-0 mb-3 d-print-none" aria-label="Filtrar bienes del reporte">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md">
                        <label class="visually-hidden" for="damagedReportSearch">Buscar en el reporte</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body-tertiary text-body-secondary" aria-hidden="true">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="search" class="form-control" id="damagedReportSearch"
                                   placeholder="Filtrar por código, descripción, adscripción, serial o nota…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-auto">
                        <span class="small text-body-secondary" id="damagedReportCount" role="status" aria-live="polite">
                            Mostrando <?= e((string) $identifiedCount) ?> de <?= e((string) $identifiedCount) ?> bienes
                        </span>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetDamagedReportFilters">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="alert alert-info d-none d-print-none" id="damagedReportNoMatches" role="status">
            Ningún bien coincide con el filtro aplicado. Ajusta la búsqueda o limpia los filtros para ver los informes.
        </div>

        <?php
        $letterheadOmitId = true;
        foreach ($properties as $property) :
            $propertyId = (int) $property['id'];
            $propertyCode = property_code($property);
            $identPadded = str_pad(trim((string) ($property['ident'] ?? '')), 4, '0', STR_PAD_LEFT);
            $statusUpper = mb_strtoupper(trim((string) $property['status']));
            $statusLabel = property_status_label((string) $property['status']);
            $adscriptionInfo = property_adscription_assignment($property, $legacyAdscriptionsByIdent);
            $propertyAdscriptionLabel = $adscriptionInfo['label'];
            $preloadedMaintenances = $maintenancesByProperty[$propertyId] ?? array();
            $notesCollection = array();
            foreach ($preloadedMaintenances as $mRow) {
                $noteText = trim((string) ($mRow['notes'] ?? ''));
                if ($noteText !== '') {
                    $notesCollection[] = $noteText;
                }
            }
            $joinedNotes = implode(' | ', $notesCollection);
            $searchHaystack = mb_strtolower(implode(' ', array(
                $propertyCode,
                $identPadded,
                (string) ($property['description'] ?? ''),
                $propertyAdscriptionLabel,
                (string) ($property['model'] ?? ''),
                (string) ($property['serial'] ?? ''),
                $statusLabel,
                $joinedNotes,
            )), 'UTF-8');
            ?>
            <article class="card damaged-report-record border-0 shadow-sm mb-3"
                     data-status="<?= e($statusUpper) ?>"
                     data-search="<?= e($searchHaystack) ?>"
                     data-export-code="<?= e($propertyCode) ?>"
                     data-export-description="<?= e((string) ($property['description'] ?? '')) ?>"
                     data-export-adscription="<?= e($propertyAdscriptionLabel) ?>"
                     data-export-model="<?= e((string) ($property['model'] ?? '')) ?>"
                     data-export-serial="<?= e((string) ($property['serial'] ?? '')) ?>"
                     data-export-status="<?= e($statusLabel) ?>"
                     data-export-maintenances="<?= e((string) count($preloadedMaintenances)) ?>"
                     data-export-notes="<?= e($joinedNotes) ?>"
                     aria-labelledby="damagedPropertyHeading<?= e((string) $propertyId) ?>">
                <div class="card-header bg-body-tertiary d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 damaged-report-heading">
                        <span class="rounded-circle bg-danger-subtle text-danger p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-box-seam"></i>
                        </span>
                        <div class="damaged-report-heading">
                            <h2 class="h5 mb-0 text-break" id="damagedPropertyHeading<?= e((string) $propertyId) ?>">
                                Bien <?= e($propertyCode) ?>
                            </h2>
                            <?php if (trim((string) $property['description']) !== '') : ?>
                                <p class="small text-body-secondary mb-0 text-break">
                                    <?= e($property['description']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                        <div class="btn-group btn-group-sm d-print-none" role="group" aria-label="Accesos rápidos del bien <?= e($propertyCode) ?>">
                            <a href="<?= e(url('dashboard.php?search=' . rawurlencode($identPadded))) ?>"
                               class="btn btn-outline-secondary"
                               title="Consultar bien en Dashboard">
                                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                                <span class="visually-hidden">Ver en Dashboard</span>
                            </a>
                            <a href="<?= e(url('maintenances.php?search=' . rawurlencode($identPadded))) ?>"
                               class="btn btn-outline-secondary"
                               title="Ver mantenimientos del bien">
                                <i class="bi bi-tools" aria-hidden="true"></i>
                                <span class="visually-hidden">Ver mantenimientos</span>
                            </a>
                            <a href="<?= e(url('historials.php?property_id=' . $propertyId)) ?>"
                               class="btn btn-outline-primary"
                               target="_blank" rel="noopener"
                               title="Abrir ficha individual imprimible">
                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                <span class="visually-hidden">Abrir ficha individual</span>
                            </a>
                        </div>
                        <?= sat_badge(
                            badge_clase_bien((string) $property['status']),
                            $statusLabel
                        ) ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="damaged-report-letterhead">
                        <?php include __DIR__ . '/historials/membrete.php'; ?>
                    </div>

                    <section class="mt-3" aria-label="Historial y observaciones del bien <?= e($propertyCode) ?>">
                        <h3 class="h6 d-flex align-items-center gap-2 border-bottom pb-2 mb-2">
                            <i class="bi bi-clock-history text-body-secondary" aria-hidden="true"></i>
                            Historial de mantenimiento
                        </h3>
                        <?php include __DIR__ . '/historials/report.php'; ?>
                    </section>
                </div>
            </article>
        <?php endforeach;
        unset($letterheadOmitId, $preloadedMaintenances, $propertyAdscriptionLabel);
        ?>
    <?php endif; ?>
</div>

<style nonce="<?= e(systec_csp_nonce()) ?>">
    @page {
        margin: 12mm;
    }

    .damaged-report-heading {
        min-width: 0;
    }

    .js-damaged-kpi {
        appearance: none;
        font: inherit;
        cursor: pointer;
        transition: box-shadow .15s ease, transform .15s ease;
    }

    .js-damaged-kpi:hover {
        transform: translateY(-1px);
    }

    .js-damaged-kpi:focus-visible,
    .js-damaged-kpi[aria-pressed="true"] {
        outline: 0;
        box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .2), var(--bs-box-shadow-sm);
    }

    @media (prefers-reduced-motion: reduce) {
        .js-damaged-kpi {
            transition: none;
        }

        .js-damaged-kpi:hover {
            transform: none;
        }
    }

    .damaged-report-letterhead .history-letterhead {
        padding: .75rem 1rem;
        border: 1px solid var(--bs-border-color);
        border-radius: var(--bs-border-radius);
        background-color: var(--bs-tertiary-bg);
    }

    .damaged-report-letterhead .history-letterhead h5 {
        color: var(--bs-emphasis-color);
        font-size: 1rem;
        font-weight: 600;
    }

    .damaged-report-letterhead .history-letterhead .small {
        line-height: 1.6;
        overflow-wrap: anywhere;
    }

    .damaged-report-record table {
        font-size: .8125rem;
    }

    @media print {
        body.d-flex {
            display: block !important;
            margin: 0;
            padding: 0;
            color: #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .app-header,
        .app-footer,
        .report-actions,
        .report-summary {
            display: none !important;
        }

        .app-shell,
        .app-main,
        .damaged-report {
            display: block !important;
            width: 100% !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .damaged-report-record {
            border: 0 !important;
            box-shadow: none !important;
            margin: 0 !important;
            break-inside: auto;
            page-break-inside: auto;
        }

        .damaged-report-record:not(.d-none) ~ .damaged-report-record:not(.d-none) {
            break-before: page;
            page-break-before: always;
        }

        .damaged-report-record .card-body {
            padding: 0 !important;
        }

        .damaged-report-record .card-header {
            padding: 0 0 .5rem !important;
            border: 0 !important;
            background: transparent !important;
        }

        .damaged-report-letterhead .history-letterhead {
            border-radius: 0;
        }

        .damaged-report-record .table-responsive {
            overflow: visible !important;
        }

        .damaged-report-record table {
            font-size: .75rem !important;
        }

        .damaged-report-record tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
</style>

<script nonce="<?= e(systec_csp_nonce()) ?>">
    (function () {
        const printButton = document.getElementById('printDamagedReport');
        const exportCsvButton = document.getElementById('exportDamagedReportCsv');
        const searchInput = document.getElementById('damagedReportSearch');
        const resetButton = document.getElementById('resetDamagedReportFilters');
        const countLabel = document.getElementById('damagedReportCount');
        const noMatchesAlert = document.getElementById('damagedReportNoMatches');
        const kpiButtons = Array.from(document.querySelectorAll('.js-damaged-kpi'));
        const records = Array.from(document.querySelectorAll('.damaged-report-record'));
        let activeStatus = '';

        const getVisibleRecords = () => records.filter((record) => !record.classList.contains('d-none'));

        const applyFilters = () => {
            const term = (searchInput ? searchInput.value : '').trim().toLowerCase();
            let visibleCount = 0;

            records.forEach((record) => {
                const statusMatch = !activeStatus || record.dataset.status === activeStatus;
                const searchMatch = !term || (record.dataset.search || '').includes(term);
                const visible = statusMatch && searchMatch;
                record.classList.toggle('d-none', !visible);
                if (visible) visibleCount++;
            });

            kpiButtons.forEach((button) => {
                const btnStatus = button.dataset.filterStatus || '';
                const pressed = btnStatus === '' ? activeStatus === '' : activeStatus === btnStatus;
                button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            });

            if (countLabel) {
                countLabel.textContent = `Mostrando ${visibleCount} de ${records.length} bienes`;
            }
            if (noMatchesAlert) {
                noMatchesAlert.classList.toggle('d-none', visibleCount > 0 || records.length === 0);
            }
            if (printButton) {
                printButton.disabled = visibleCount === 0;
            }
            if (exportCsvButton) {
                exportCsvButton.disabled = visibleCount === 0;
            }
        };

        kpiButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const targetStatus = button.dataset.filterStatus || '';
                activeStatus = (targetStatus !== '' && activeStatus === targetStatus) ? '' : targetStatus;
                applyFilters();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        if (resetButton) {
            resetButton.addEventListener('click', () => {
                activeStatus = '';
                if (searchInput) searchInput.value = '';
                applyFilters();
            });
        }

        if (printButton) {
            printButton.addEventListener('click', () => {
                window.print();
            });
        }

        if (exportCsvButton) {
            const csvEscape = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
            exportCsvButton.addEventListener('click', () => {
                const columns = [
                    { header: 'Código', key: 'exportCode' },
                    { header: 'Descripción', key: 'exportDescription' },
                    { header: 'Adscripción', key: 'exportAdscription' },
                    { header: 'Modelo', key: 'exportModel' },
                    { header: 'Serial', key: 'exportSerial' },
                    { header: 'Condición', key: 'exportStatus' },
                    { header: 'Mantenimientos', key: 'exportMaintenances' },
                    { header: 'Notas / Observaciones', key: 'exportNotes' }
                ];
                const lines = [columns.map((col) => csvEscape(col.header)).join(';')];
                getVisibleRecords().forEach((record) => {
                    const values = columns.map((col) => (record.dataset[col.key] || '').replace(/\s+/g, ' ').trim());
                    lines.push(values.map(csvEscape).join(';'));
                });

                const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                const downloadUrl = URL.createObjectURL(blob);
                const today = new Date();
                const dateStamp = [
                    today.getFullYear(),
                    String(today.getMonth() + 1).padStart(2, '0'),
                    String(today.getDate()).padStart(2, '0')
                ].join('-');
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = `bienes-danados-${dateStamp}.csv`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(downloadUrl);
            });
        }

        applyFilters();
    })();
</script>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
