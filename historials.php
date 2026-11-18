<?php

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_login();

$title = 'Historial';
$db = db();

$property_id = isset($_GET['property_id']) ? filter_var($_GET['property_id'], FILTER_VALIDATE_INT) : false;
$property = ($property_id !== false && $property_id !== null) ? $db->getFirstForId('properties', $property_id) : null;
$maintenances = $property ? $db->index('maintenances', array('property_id' => $property_id), '*', 'date ASC') : array();
$tickets = $property ? $db->index('tickets', array('property_id' => (int) $property_id), '*', 'created_at ASC') : array();
$ticketDetailsById = array();
$ticketStatusClasses = array(
    'iniciado' => 'primary',
    'en_progreso' => 'info',
    'con_retraso' => 'warning',
    'en_espera' => 'secondary',
    'finalizado' => 'success',
);
foreach ($tickets as $ticket) {
    $ticketId = (int) $ticket['id'];
    $status = $db->getFirstForId('ticket_status', (int) $ticket['status_id']);
    $creator = $db->getFirstForId('users', (int) $ticket['created_by']);
    $assigneeId = (int) ($ticket['assigned_to'] ?? 0);
    $assignee = $assigneeId > 0 ? $db->getFirstForId('users', $assigneeId) : null;
    $ticketDetailsById[$ticketId] = array(
        'status' => $status ?: array('name' => 'Sin estado', 'code' => ''),
        'status_class' => $ticketStatusClasses[(string) ($status['code'] ?? '')] ?? 'secondary',
        'creator' => $creator ?: array('username' => 'Usuario no disponible'),
        'assignee' => $assignee ?: null,
        'history' => $db->ticketHistoryWithActors($ticketId),
    );
}
$user = auth_user();
$lastMaintenance = !empty($maintenances) ? end($maintenances) : null;
$technician = '';
if ($lastMaintenance && !empty($lastMaintenance['technician'])) {
    $technician = (string) $lastMaintenance['technician'];
} elseif (is_array($user) && !empty($user['username'])) {
    $technician = (string) $user['username'];
}

if (isset($_GET['embed']) && $_GET['embed'] === '1') {
    if (!$property) {
        echo '<div class="alert alert-warning mb-0" role="alert">No se encontró el bien solicitado.</div>';
        exit();
    }

    echo '<div class="container-fluid p-3">';
    include __DIR__ . '/historials/membrete.php';
    echo '<hr class="my-2">';
    include __DIR__ . '/historials/main.php';
    echo '</div>';
    exit();
}

$no_footer = true;
$header_file = __DIR__ . '/historials/membrete.php';

ob_start(); ?>

    <style nonce="<?= e(systec_csp_nonce()) ?>">
        @media print {
            .history-print-content {
                break-after: avoid;
                page-break-after: avoid;
            }

            .history-signature {
                break-inside: avoid;
                page-break-inside: avoid;
                break-before: avoid;
                page-break-before: avoid;
            }
        }
    </style>

    <section class="container-fluid history-print-content" aria-labelledby="history-page-title">
        <h1 class="h3 mb-3" id="history-page-title">Historial de mantenimiento</h1>
        <?php if (!$property) : ?>
            <div class="alert alert-warning">No se encontró el bien solicitado.</div>
        <?php else : ?>
            <?php include __DIR__ . '/historials/main.php'; ?>
        <?php endif; ?>
    </section>

    <div class="container-fluid text-center history-signature">
        <div class="my-0 py-0">____________________________________________</div>
        <div class="my-0 py-0">Realizado por: <span class="text-uppercase"><?= e($technician) ?></span></div>
        <div class="my-0 py-0"><?= e(date('d-m-Y H:i')) ?></div>
    </div>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
