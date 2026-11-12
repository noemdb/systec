<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_login();

$title = 'Reporte de bienes dañados';
$db = db();
$properties = $db->indexWithMaintenances('properties.*', 'date ASC');

$maloCount = 0;
$veryBadCount = 0;
foreach ($properties as $propertyRow) {
    $statusValue = mb_strtoupper(trim((string) $propertyRow['status']));
    if ($statusValue === 'MALO') {
        $maloCount++;
    }
    if ($statusValue === 'MUY MALO') {
        $veryBadCount++;
    }
}
$identifiedCount = count($properties);

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
                        Cada bien se imprime en una página independiente.
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 flex-shrink-0 align-self-lg-start">
            <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a bienes
            </a>
            <?php if ($properties !== array()) : ?>
                <button type="button" class="btn btn-primary" id="printDamagedReport">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir reporte
                </button>
            <?php endif; ?>
        </div>
    </header>

    <section class="row g-3 mb-4 report-summary" aria-label="Resumen de bienes dañados">
        <div class="col-12 col-md-4">
            <article class="card h-100 border-start border-4 border-primary shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-box-seam fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Bienes identificados</p>
                        <p class="h3 mb-0"><?= e((string) $identifiedCount) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="card h-100 border-start border-4 border-danger shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-danger-subtle text-danger p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Condición mala</p>
                        <p class="h3 mb-0"><?= e((string) $maloCount) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="card h-100 border-start border-4 border-danger shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-danger-subtle text-danger p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-exclamation-octagon fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Condición muy mala</p>
                        <p class="h3 mb-0"><?= e((string) $veryBadCount) ?></p>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <?php if ($properties === array()) : ?>
        <section class="card shadow-sm border-0 text-center" aria-label="Sin bienes para reportar">
            <div class="card-body py-4">
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
        <?php foreach ($properties as $property) : ?>
            <?php $propertyCode = property_code($property); ?>
            <article class="card damaged-report-record border-0 shadow-sm mb-3"
                     aria-labelledby="damagedPropertyHeading<?= e((string) $property['id']) ?>">
                <div class="card-header bg-body-tertiary d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 damaged-report-heading">
                        <span class="rounded-circle bg-danger-subtle text-danger p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-box-seam"></i>
                        </span>
                        <div class="damaged-report-heading">
                            <h2 class="h5 mb-0 text-break" id="damagedPropertyHeading<?= e((string) $property['id']) ?>">
                                Bien <?= e($propertyCode) ?>
                            </h2>
                            <?php if (trim((string) $property['description']) !== '') : ?>
                                <p class="small text-body-secondary mb-0 text-break">
                                    <?= e($property['description']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <?= sat_badge(
                            badge_clase_bien((string) $property['status']),
                            property_status_label((string) $property['status'])
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
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style nonce="<?= e(systec_csp_nonce()) ?>">
    @page {
        margin: 12mm;
    }

    .damaged-report-heading {
        min-width: 0;
    }

    .damaged-report-letterhead #historyLetterhead {
        padding: .75rem 1rem;
        border: 1px solid var(--bs-border-color);
        border-radius: var(--bs-border-radius);
        background-color: var(--bs-tertiary-bg);
    }

    .damaged-report-letterhead #historyLetterhead h5 {
        color: var(--bs-emphasis-color);
        font-size: 1rem;
        font-weight: 600;
    }

    .damaged-report-letterhead #historyLetterhead .small {
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

        .damaged-report-record + .damaged-report-record {
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

        .damaged-report-letterhead #historyLetterhead {
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
    const printDamagedReportButton = document.getElementById('printDamagedReport');
    if (printDamagedReportButton) {
        printDamagedReportButton.addEventListener('click', function () {
            window.print();
        });
    }
</script>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
