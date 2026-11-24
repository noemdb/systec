<?php

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';
require_login();
require_non_personal_area();

$db = db();
$repo = $db->renditionReports();

$reportId = isset($_GET['id']) && is_string($_GET['id'])
    ? filter_var($_GET['id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
    : false;
$report = $reportId !== false ? $repo->findById((int) $reportId) : null;

if ($report !== null && isset($_GET['download']) && $_GET['download'] === 'md') {
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $report['report_code']) . '.md';
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store');
    echo (string) $report['content_markdown'];
    exit();
}

/**
 * Convierte el Markdown del Informe de Rendición en HTML seguro para vista e impresión.
 */
function systec_render_rendition_markdown_html(string $markdown): string
{
    $lines = preg_split("/\r\n|\n|\r/", $markdown);
    if (!is_array($lines)) {
        return '';
    }

    $formatInline = static function (string $text): string {
        $escaped = e($text);
        $escaped = (string) preg_replace('/`([^`]+)`/u', '<code>$1</code>', $escaped);
        $escaped = (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $escaped);
        $escaped = (string) preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/u', '<em>$1</em>', $escaped);
        $escaped = (string) preg_replace('/^_(.+)_$/u', '<em class="text-body-secondary">$1</em>', $escaped);
        return $escaped;
    };

    $sanitizeTableTagLine = static function (string $line) use ($formatInline): ?string {
        if (preg_match('/^<table(?:\s+class="([A-Za-z0-9_\-\s]+)")?(?:\s+border="1")?\s*>$/i', $line, $m)) {
            $cls = isset($m[1]) && trim($m[1]) !== ''
                ? trim($m[1])
                : 'table table-sm table-bordered align-middle mb-0 rendition-markdown-table';
            return '<div class="table-responsive my-2"><table class="' . e($cls) . '">';
        }
        if (preg_match('/^<\/table>$/i', $line)) {
            return '</table></div>';
        }
        if (preg_match('/^<(thead|tbody)(?:\s+class="([A-Za-z0-9_\-\s]+)")?\s*>$/i', $line, $m)) {
            $tag = strtolower($m[1]);
            $clsAttr = isset($m[2]) && trim($m[2]) !== '' ? ' class="' . e(trim($m[2])) . '"' : '';
            return '<' . $tag . $clsAttr . '>';
        }
        if (preg_match('/^<\/(thead|tbody|tr)>$/i', $line, $m)) {
            return '</' . strtolower($m[1]) . '>';
        }
        if (preg_match('/^<tr(?:\s+class="([A-Za-z0-9_\-\s]+)")?\s*>$/i', $line, $m)) {
            $clsAttr = isset($m[1]) && trim($m[1]) !== '' ? ' class="' . e(trim($m[1])) . '"' : '';
            return '<tr' . $clsAttr . '>';
        }
        if (preg_match('/^<(th|td)([^>]*)>(.*)<\/\1>$/is', $line, $m)) {
            $tag = strtolower($m[1]);
            $rawAttrs = $m[2];
            $innerRaw = $m[3];
            $safeAttrs = array();
            if (preg_match('/\bscope="(col|row)"/i', $rawAttrs, $am)) {
                $safeAttrs[] = 'scope="' . strtolower($am[1]) . '"';
            }
            if (preg_match('/\browspan="(\d{1,2})"/i', $rawAttrs, $am)) {
                $safeAttrs[] = 'rowspan="' . (int) $am[1] . '"';
            }
            if (preg_match('/\bcolspan="(\d{1,2})"/i', $rawAttrs, $am)) {
                $safeAttrs[] = 'colspan="' . (int) $am[1] . '"';
            }
            if (preg_match('/\bclass="([A-Za-z0-9_\-\s]+)"/i', $rawAttrs, $am)) {
                $safeAttrs[] = 'class="' . e(trim($am[1])) . '"';
            }
            $normalizedInner = (string) preg_replace('/<\/?strong>/i', '**', $innerRaw);
            $attrStr = $safeAttrs !== array() ? ' ' . implode(' ', $safeAttrs) : '';
            return '<' . $tag . $attrStr . '>' . $formatInline($normalizedInner) . '</' . $tag . '>';
        }
        return null;
    };

    $html = array();
    $inTable = false;
    $inHtmlTable = false;
    $tableHeaderDone = false;
    $inList = false;
    $paragraphBuffer = array();

    $flushParagraph = static function () use (&$paragraphBuffer, &$html, $formatInline): void {
        if ($paragraphBuffer === array()) {
            return;
        }
        $renderedLines = array();
        foreach ($paragraphBuffer as $pLine) {
            $renderedLines[] = $formatInline($pLine);
        }
        $html[] = '<p class="mb-2">' . implode('<br>', $renderedLines) . '</p>';
        $paragraphBuffer = array();
    };

    $closeTable = static function () use (&$inTable, &$tableHeaderDone, &$html): void {
        if ($inTable) {
            if ($tableHeaderDone) {
                $html[] = '</tbody>';
            }
            $html[] = '</table></div>';
            $inTable = false;
            $tableHeaderDone = false;
        }
    };

    $closeList = static function () use (&$inList, &$html): void {
        if ($inList) {
            $html[] = '</ul>';
            $inList = false;
        }
    };

    foreach ($lines as $rawLine) {
        $trimmed = trim((string) $rawLine);

        if ($trimmed === '') {
            if ($inHtmlTable) {
                continue;
            }
            $flushParagraph();
            $closeTable();
            $closeList();
            continue;
        }

        if (preg_match('/^<!--.*-->$/', $trimmed)) {
            continue;
        }

        $safeHtmlTableLine = $sanitizeTableTagLine($trimmed);
        if ($safeHtmlTableLine !== null) {
            $flushParagraph();
            $closeTable();
            $closeList();
            if (stripos($trimmed, '<table') === 0) {
                $inHtmlTable = true;
            } elseif (stripos($trimmed, '</table') === 0) {
                $inHtmlTable = false;
            }
            $html[] = $safeHtmlTableLine;
            continue;
        }

        if (preg_match('/^>\s*(.+)$/u', $trimmed, $matches)) {
            $flushParagraph();
            $closeTable();
            $closeList();
            $html[] = '<blockquote class="blockquote-footer bg-body-tertiary border-start border-3 border-primary-subtle ps-2 py-1 my-2 small text-body-secondary">'
                . $formatInline(trim($matches[1]))
                . '</blockquote>';
            continue;
        }

        if (preg_match('/^---+$/', $trimmed)) {
            $flushParagraph();
            $closeTable();
            $closeList();
            $html[] = '<hr class="my-3">';
            continue;
        }

        if (preg_match('/^(#{1,3})\s+(.+)$/u', $trimmed, $matches)) {
            $flushParagraph();
            $closeTable();
            $closeList();
            $level = strlen($matches[1]);
            $headingText = $formatInline(trim($matches[2]));
            if ($level === 1) {
                $html[] = '<h1 class="h4 fw-bold text-uppercase border-bottom pb-2 mb-3">' . $headingText . '</h1>';
            } elseif ($level === 2) {
                $html[] = '<h2 class="h6 fw-bold text-uppercase text-primary-emphasis mt-3 mb-2">' . $headingText . '</h2>';
            } else {
                $html[] = '<h3 class="h6 fw-semibold mt-2 mb-1">' . $headingText . '</h3>';
            }
            continue;
        }

        if (strpos($trimmed, '|') === 0 && substr($trimmed, -1) === '|') {
            $flushParagraph();
            $closeList();

            $inner = substr($trimmed, 1, -1);
            $cells = preg_split('/(?<!\\\\)\|/', $inner);
            if (!is_array($cells)) {
                $cells = array($inner);
            }
            $cells = array_map(static function (string $c): string {
                return str_replace('\\|', '|', trim($c));
            }, $cells);

            $isSeparator = true;
            foreach ($cells as $c) {
                if (!preg_match('/^:?-{2,}:?$/', $c)) {
                    $isSeparator = false;
                    break;
                }
            }
            if ($isSeparator) {
                continue;
            }

            if (!$inTable) {
                $inTable = true;
                $tableHeaderDone = false;
                $html[] = '<div class="table-responsive my-2"><table class="table table-sm table-bordered align-middle mb-0 rendition-markdown-table">';
                $html[] = '<thead class="table-light"><tr>';
                foreach ($cells as $cell) {
                    $html[] = '<th scope="col">' . $formatInline($cell) . '</th>';
                }
                $html[] = '</tr></thead>';
                $tableHeaderDone = true;
                $html[] = '<tbody>';
            } else {
                $html[] = '<tr>';
                foreach ($cells as $cell) {
                    $html[] = '<td>' . $formatInline($cell) . '</td>';
                }
                $html[] = '</tr>';
            }
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/u', $trimmed, $matches)) {
            $flushParagraph();
            $closeTable();
            if (!$inList) {
                $inList = true;
                $html[] = '<ul class="mb-2">';
            }
            $html[] = '<li>' . $formatInline(trim($matches[1])) . '</li>';
            continue;
        }

        $closeTable();
        $closeList();
        $paragraphBuffer[] = $trimmed;
    }

    $flushParagraph();
    $closeTable();
    $closeList();

    return implode("\n", $html);
}

$title = $report !== null
    ? 'Informe de Rendición ' . $report['report_code']
    : 'Informe de Rendición no encontrado';

ob_start(); ?>

<div class="container-fluid text-start rendition-print-sheet">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3 d-print-none">
        <a href="<?= e(url('maintenances.php')) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a mantenimientos
        </a>
        <?php if ($report !== null) : ?>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= e(url('maintenances/rendition-print.php?id=' . (int) $report['id'] . '&download=md')) ?>"
                   class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-markdown me-1" aria-hidden="true"></i>Descargar Markdown (.md)
                </a>
                <button type="button" class="btn btn-primary btn-sm" id="printRenditionDocument">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir informe
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($report === null) : ?>
        <section class="card shadow-sm border-0 text-center" aria-label="Informe no encontrado">
            <div class="card-body py-5">
                <h1 class="h5 mb-2">Informe de Rendición no encontrado</h1>
                <p class="text-body-secondary mb-0">Verifica el identificador del informe o vuelve al módulo de mantenimientos.</p>
            </div>
        </section>
    <?php else : ?>
        <article class="card shadow-sm border-0 rendition-document-card" aria-label="Informe de Rendición <?= e((string) $report['report_code']) ?>">
            <div class="card-header bg-body-tertiary d-flex flex-wrap align-items-center justify-content-between gap-2 d-print-none">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold"><?= e((string) $report['report_code']) ?></span>
                    <?php if ($report['status'] === 'aprobado') : ?>
                        <span class="badge rounded-pill text-bg-success">
                            <i class="bi bi-patch-check-fill me-1" aria-hidden="true"></i>Aprobado
                        </span>
                    <?php else : ?>
                        <span class="badge rounded-pill text-bg-warning">
                            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Borrador
                        </span>
                    <?php endif; ?>
                </div>
                <span class="small text-body-secondary font-monospace" title="Huella SHA-256 de unicidad del período y alcance">
                    Hash: <?= e(substr((string) $report['scope_hash'], 0, 16)) ?>…
                </span>
            </div>
            <div class="card-body p-4 rendition-rendered-body">
                <?= systec_render_rendition_markdown_html((string) $report['content_markdown']) ?>
            </div>
        </article>
    <?php endif; ?>
</div>

<style nonce="<?= e(systec_csp_nonce()) ?>">
    .rendition-print-sheet {
        max-width: 215mm;
        margin: 0 auto;
    }

    .rendition-markdown-table {
        font-size: .8125rem;
    }

    @page {
        margin: 14mm;
    }

    @media print {
        body.d-flex {
            display: block !important;
            background: #fff !important;
            color: #000 !important;
        }

        .app-header,
        .app-footer {
            display: none !important;
        }

        .app-shell,
        .app-main,
        .rendition-print-sheet {
            max-width: none !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .rendition-document-card {
            border: 0 !important;
            box-shadow: none !important;
        }

        .rendition-rendered-body {
            padding: 0 !important;
        }

        .rendition-rendered-body .table-responsive {
            overflow: visible !important;
        }

        .rendition-markdown-table {
            font-size: .75rem !important;
        }
    }
</style>

<script nonce="<?= e(systec_csp_nonce()) ?>">
    (function () {
        const printBtn = document.getElementById('printRenditionDocument');
        if (printBtn) {
            printBtn.addEventListener('click', () => window.print());
        }
    })();
</script>

<?php
$main = ob_get_clean();
include __DIR__ . '/../include/layout.php';
