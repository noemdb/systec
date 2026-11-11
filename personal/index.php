<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';
require_personal_scope();

$followupSaved = !empty($_SESSION['personal_ticket_followup_saved']);
unset($_SESSION['personal_ticket_followup_saved']);

$db = db();
$user = auth_user();
$conditions = array('created_by' => (int) $user['id']);
$tickets = $db->index('tickets', $conditions, '*', 'updated_at DESC');
$availableProperties = $db->index('properties', array(), '*', 'id ASC');
$statuses = $db->getTicketStatuses();
$properties = array();
$ticketHistories = array();
$summaryCounts = array(
    'total' => count($tickets),
    'in_progress' => 0,
    'delayed' => 0,
    'waiting' => 0,
    'closed' => 0,
);
$statusClasses = array(
    'iniciado' => 'primary',
    'en_progreso' => 'info',
    'con_retraso' => 'warning',
    'en_espera' => 'secondary',
    'finalizado' => 'success',
);
$inactiveTicketCutoff = strtotime('-7 days');

foreach ($tickets as $ticket) {
    $property = $db->getFirstForId('properties', (int) $ticket['property_id']);
    $properties[(int) $ticket['id']] = $property ?: array('description' => 'Bien eliminado');
    $ticketHistories[(int) $ticket['id']] = $db->ticketHistoryWithActors((int) $ticket['id']);

    $statusCode = isset($statuses[(int) $ticket['status_id']]['code']) ? (string) $statuses[(int) $ticket['status_id']]['code'] : '';
    switch ($statusCode) {
        case 'en_progreso':
            $summaryCounts['in_progress']++;
            break;
        case 'con_retraso':
            $summaryCounts['delayed']++;
            break;
        case 'en_espera':
            $summaryCounts['waiting']++;
            break;
        case 'finalizado':
            $summaryCounts['closed']++;
            break;
    }
}

$title = 'Tickets personales';
ob_start();
?>
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Mi área de tickets</h1>
            <p class="text-muted mb-0">Seguimiento y registro de incidencias del personal.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerPersonalTicketModal">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Nuevo ticket
        </button>
    </div>

    <?php if ($followupSaved) : ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="status">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span>El seguimiento se registró correctamente.</span>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <section class="row g-3 mb-4" aria-label="Indicadores del área personal">
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="card h-100 w-100 border-start border-4 border-primary shadow-sm text-start text-body ticket-status-indicator"
                    data-status-filter="" <?= $tickets !== array() ? 'aria-controls="personalTicketTableBody"' : 'disabled' ?> aria-pressed="false"
                    aria-label="Mostrar todos los tickets">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2" aria-hidden="true">
                        <i class="bi bi-ticket-detailed fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">Total</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['total']) ?></p>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="card h-100 w-100 border-start border-4 border-info shadow-sm text-start text-body ticket-status-indicator"
                    data-status-filter="en_progreso" <?= $tickets !== array() ? 'aria-controls="personalTicketTableBody"' : 'disabled' ?> aria-pressed="false"
                    aria-label="Filtrar tickets en progreso">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-info-subtle text-info p-2" aria-hidden="true">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">En progreso</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['in_progress']) ?></p>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="card h-100 w-100 border-start border-4 border-warning shadow-sm text-start text-body ticket-status-indicator"
                    data-status-filter="con_retraso" <?= $tickets !== array() ? 'aria-controls="personalTicketTableBody"' : 'disabled' ?> aria-pressed="false"
                    aria-label="Filtrar tickets con retraso">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-warning-subtle text-warning p-2" aria-hidden="true">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">Con retraso</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['delayed']) ?></p>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="card h-100 w-100 border-start border-4 border-secondary shadow-sm text-start text-body ticket-status-indicator"
                    data-status-filter="en_espera" <?= $tickets !== array() ? 'aria-controls="personalTicketTableBody"' : 'disabled' ?> aria-pressed="false"
                    aria-label="Filtrar tickets en espera">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-secondary-subtle text-secondary p-2" aria-hidden="true">
                        <i class="bi bi-pause-circle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">En espera</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['waiting']) ?></p>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="card h-100 w-100 border-start border-4 border-success shadow-sm text-start text-body ticket-status-indicator"
                    data-status-filter="finalizado" <?= $tickets !== array() ? 'aria-controls="personalTicketTableBody"' : 'disabled' ?> aria-pressed="false"
                    aria-label="Filtrar tickets finalizados">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-success-subtle text-success p-2" aria-hidden="true">
                        <i class="bi bi-check-circle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">Finalizados</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['closed']) ?></p>
                    </div>
                </div>
            </button>
        </div>
    </section>

    <section class="card shadow-sm text-start" aria-labelledby="my-tickets-title">
        <div class="card-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
            <h2 class="h5 mb-0" id="my-tickets-title">
                <i class="bi bi-list-task text-primary me-2" aria-hidden="true"></i>Mis tickets
            </h2>
            <span class="small text-body-secondary"><?= e((string) $summaryCounts['total']) ?> registros</span>
        </div>

        <?php if ($tickets === array()) : ?>
            <div class="card-body py-4">
                <div class="text-center py-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary p-3 mb-3" aria-hidden="true">
                        <i class="bi bi-inbox fs-3"></i>
                    </span>
                    <h3 class="h6 mb-1">Aún no tienes tickets</h3>
                    <p class="text-body-secondary mb-3">Cuando registres una incidencia, podrás seguir su estado desde aquí.</p>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#registerPersonalTicketModal">
                        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Registrar incidencia
                    </button>
                </div>
            </div>
        <?php else : ?>
            <div class="card-body border-bottom">
                    <h3 class="h6 mb-3" id="personalTicketFiltersTitle">
                        <i class="bi bi-funnel me-2 text-primary" aria-hidden="true"></i>Buscar y filtrar
                    </h3>
                    <form id="personalTicketFilters" role="search" aria-labelledby="personalTicketFiltersTitle">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-6">
                                <label for="personalTicketSearch" class="form-label small fw-semibold">Buscar</label>
                                <input type="search" class="form-control form-control-sm" id="personalTicketSearch"
                                       placeholder="Título, descripción, bien o código" autocomplete="off">
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <label for="personalTicketStatusFilter" class="form-label small fw-semibold">Estado</label>
                                <select class="form-select form-select-sm" id="personalTicketStatusFilter">
                                    <option value="">Todos los estados</option>
                                    <?php foreach ($statuses as $statusOption) : ?>
                                        <option value="<?= e((string) ($statusOption['code'] ?? '')) ?>">
                                            <?= e((string) ($statusOption['name'] ?? 'Sin estado')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <label for="personalTicketPropertyFilter" class="form-label small fw-semibold">Bien</label>
                                <select class="form-select form-select-sm" id="personalTicketPropertyFilter">
                                    <option value="">Todos los bienes</option>
                                    <?php
                                    $seenPropertyIds = array();
                                    foreach ($tickets as $ticket) :
                                        $propertyId = (int) $ticket['property_id'];
                                        if (isset($seenPropertyIds[$propertyId])) {
                                            continue;
                                        }
                                        $seenPropertyIds[$propertyId] = true;
                                        $property = $properties[(int) $ticket['id']];
                                        $propertyLabel = trim((string) ($property['ident'] ?? ''));
                                        $propertyDescription = trim((string) ($property['description'] ?? 'Bien desconocido'));
                                        if ($propertyLabel !== '') {
                                            $propertyLabel .= ' · ';
                                        }
                                        $propertyLabel .= $propertyDescription;
                                        ?>
                                        <option value="<?= e((string) $propertyId) ?>"><?= e($propertyLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="personalTicketDateFrom" class="form-label small fw-semibold">Actualizado desde</label>
                                <input type="date" class="form-control form-control-sm" id="personalTicketDateFrom"
                                       aria-describedby="personalTicketDateError">
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="personalTicketDateTo" class="form-label small fw-semibold">Actualizado hasta</label>
                                <input type="date" class="form-control form-control-sm" id="personalTicketDateTo"
                                       aria-describedby="personalTicketDateError">
                            </div>
                            <div class="col-12 col-md-6 d-flex flex-wrap align-items-center gap-2">
                                <button type="reset" class="btn btn-sm btn-outline-secondary" id="personalTicketResetFilters">
                                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Limpiar filtros
                                </button>
                                <span class="small text-body-secondary">Sin actividad: 7 o más días en tickets abiertos.</span>
                            </div>
                        </div>
                        <div class="invalid-feedback" id="personalTicketDateError" role="alert">
                            La fecha inicial no puede ser posterior a la fecha final.
                        </div>
                    </form>
                    <p class="small text-body-secondary mb-0 mt-2" id="personalTicketFilterCount" role="status" aria-live="polite"></p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive personal-ticket-table-responsive">
                    <table class="table table-hover align-middle mb-0">
                            <caption class="visually-hidden">Tickets personales, ordenados por última actualización</caption>
                        <thead class="table-light">
                        <tr>
                            <th scope="col">Ticket</th>
                            <th scope="col">Bien</th>
                            <th scope="col">Incidencia</th>
                            <th scope="col">Estado</th>
                            <th scope="col" aria-sort="descending">Actualizado</th>
                            <th scope="col" class="text-end">Acción</th>
                        </tr>
                        </thead>
                        <tbody id="personalTicketTableBody">
                        <?php foreach ($tickets as $ticket) :
                            $property = $properties[(int) $ticket['id']];
                            $status = $statuses[(int) $ticket['status_id']] ?? array('name' => 'Sin estado');
                            $statusCode = isset($status['code']) ? (string) $status['code'] : '';
                            $statusClass = isset($statusClasses[$statusCode]) ? $statusClasses[$statusCode] : 'light';
                            $updatedAt = (string) ($ticket['updated_at'] ?? '');
                            $updatedAtTimestamp = strtotime($updatedAt);
                            $isInactive = $statusCode !== 'finalizado'
                                && $updatedAtTimestamp !== false
                                && $updatedAtTimestamp <= $inactiveTicketCutoff;
                            $rowClass = $statusCode === 'con_retraso' || $isInactive ? 'table-warning' : '';
                            $searchText = mb_strtolower(implode(' ', array(
                                (string) $ticket['id'],
                                (string) $ticket['title'],
                                (string) $ticket['description'],
                                (string) ($property['description'] ?? ''),
                                (string) ($property['ident'] ?? ''),
                            )), 'UTF-8');
                            $ticketHistory = array();
                            foreach ($ticketHistories[(int) $ticket['id']] as $historyEntry) {
                                $ticketHistory[] = array(
                                    'created_at' => (string) $historyEntry['created_at'],
                                    'action_type' => (string) $historyEntry['action_type'],
                                    'description' => (string) $historyEntry['description'],
                                    'observation' => (string) ($historyEntry['observation'] ?? ''),
                                    'actor_username' => (string) ($historyEntry['actor_username'] ?? ''),
                                );
                            }
                            $ticketDetails = array(
                                'id' => (int) $ticket['id'],
                                'title' => (string) $ticket['title'],
                                'property' => (string) ($property['description'] ?? 'Bien desconocido'),
                                'property_code' => (string) ($property['ident'] ?? 'Sin código'),
                                'property_status' => (string) ($property['status'] ?? 'No disponible'),
                                'property_category' => property_category($property),
                                'status' => (string) ($status['name'] ?? 'Sin estado'),
                                'status_id' => (int) $ticket['status_id'],
                                'status_class' => $statusClass,
                                'created_by_name' => (string) ($user['username'] ?? ''),
                                'description' => (string) $ticket['description'],
                                'observation' => (string) ($ticket['observation'] ?? ''),
                                'created_at' => (string) ($ticket['created_at'] ?? ''),
                                'updated_at' => (string) ($ticket['updated_at'] ?? ''),
                                'closed_at' => (string) ($ticket['closed_at'] ?? ''),
                                'created_by' => (int) $ticket['created_by'],
                                'assigned_to' => (string) ($ticket['assigned_to'] ?? ''),
                                'history' => $ticketHistory,
                            );
                            ?>
                            <tr class="<?= e($rowClass) ?>"
                                data-status-code="<?= e($statusCode) ?>"
                                data-property-id="<?= e((string) $ticket['property_id']) ?>"
                                data-updated-date="<?= e(substr($updatedAt, 0, 10)) ?>"
                                data-ticket-search="<?= e($searchText) ?>">
                                <td><span class="fw-semibold">#<?= e((string) $ticket['id']) ?></span></td>
                                <td>
                                    <span class="fw-semibold"><?= e((string) ($property['description'] ?? 'Bien desconocido')) ?></span>
                                    <div class="small text-body-secondary"><?= e((string) ($property['ident'] ?? 'Sin código')) ?></div>
                                </td>
                                <td><?= e((string) $ticket['title']) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= e($statusClass) ?>"><?= e((string) ($status['name'] ?? 'Sin estado')) ?></span>
                                    <?php if ($isInactive) : ?>
                                        <span class="badge text-bg-warning ms-1" title="Sin cambios durante 7 días o más">
                                            <i class="bi bi-clock-history me-1" aria-hidden="true"></i>Sin actividad
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= e($updatedAt) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group"
                                         aria-label="Acciones para el ticket #<?= e((string) $ticket['id']) ?>">
                                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap"
                                                aria-label="Ver detalle del ticket #<?= e((string) $ticket['id']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#personalTicketDetailsModal"
                                                data-ticket="<?= e(js($ticketDetails)) ?>">
                                            <i class="bi bi-eye me-1" aria-hidden="true"></i>Detalle
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap"
                                                aria-label="Añadir seguimiento al ticket #<?= e((string) $ticket['id']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#personalTicketFollowupModal"
                                                data-ticket="<?= e(js($ticketDetails)) ?>">
                                            <i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Seguimiento
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="personalTicketNoMatches" class="d-none">
                            <td colspan="6" class="py-4 text-center text-body-secondary">
                                <i class="bi bi-search me-1" aria-hidden="true"></i>No hay tickets que coincidan con los filtros.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include __DIR__ . '/../include/personal_ticket_details_modal.php'; ?>
<?php include __DIR__ . '/../include/personal_ticket_followup_modal.php'; ?>

<div class="modal fade" id="registerPersonalTicketModal" tabindex="-1"
     aria-labelledby="registerPersonalTicketTitle" aria-describedby="registerPersonalTicketHelp" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-start">
            <form method="post" action="<?= e(url('personal/actions/register.php')) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="registerPersonalTicketTitle">Registrar ticket</h2>
                        <p class="small text-body-secondary mb-0" id="registerPersonalTicketHelp">Describe la incidencia y selecciona el bien afectado.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 property-picker">
                        <label for="personalTicketPropertyCategory" class="form-label fw-semibold small">Categoría del bien</label>
                        <select id="personalTicketPropertyCategory" class="form-select form-select-sm mb-2" data-property-picker-category>
                            <option value="">Todas las categorías</option>
                            <option value="informatica">Informática</option>
                            <option value="redes">Redes y telecomunicaciones</option>
                            <option value="otros">Otros bienes</option>
                        </select>

                        <label for="personalTicketPropertySearch" class="form-label fw-semibold small">Buscar bien por código o descripción</label>
                        <input type="search" class="form-control form-control-sm mb-2"
                               id="personalTicketPropertySearch" data-property-picker-search="personalTicketPropertyId"
                               placeholder="Ej.: 2-0101, laptop, impresora" autocomplete="off">
                        <div class="form-text mb-2" id="personalTicketPropertyMatches" aria-live="polite" aria-atomic="true"></div>

                        <fieldset class="border-0 p-0 m-0">
                            <legend class="form-label fw-semibold small" id="personalTicketPropertyLabel">
                                Bien asociado <span class="text-danger" aria-hidden="true">*</span>
                            </legend>
                            <div class="list-group property-picker-results" id="personalTicketPropertyId" role="radiogroup"
                                 aria-labelledby="personalTicketPropertyLabel"
                                 data-property-picker data-search-input="personalTicketPropertySearch"
                                 data-result-count="personalTicketPropertyMatches"
                                 data-category-filter="personalTicketPropertyCategory"
                                 data-validation-message="personalTicketPropertyError">
                            <?php foreach ($availableProperties as $property) :
                                $optionCode = property_code($property);
                                $optionDescription = trim((string) ($property['description'] ?? ''));
                                $optionStatus = property_status_label((string) ($property['status'] ?? ''));
                                $optionCategory = property_category($property);
                                $optionSearchText = implode(' ', array(
                                    $optionCode,
                                    $optionDescription,
                                    $optionStatus,
                                    (string) $property['id'],
                                ));
                                ?>
                                <label class="list-group-item list-group-item-action d-flex align-items-start gap-2 property-picker-option"
                                       data-property-picker-option data-search="<?= e($optionSearchText) ?>"
                                       data-category="<?= e($optionCategory) ?>">
                                    <input class="form-check-input flex-shrink-0 mt-1" type="radio" name="property_id"
                                           value="<?= e((string) $property['id']) ?>" required>
                                    <span class="d-flex flex-column text-break">
                                        <span class="fw-semibold"><?= e($optionCode) ?></span>
                                        <span><?= e($optionDescription !== '' ? $optionDescription : 'Sin descripción') ?></span>
                                        <span class="small text-body-secondary">Estado: <?= e($optionStatus) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            </div>
                            <div class="invalid-feedback" id="personalTicketPropertyError" role="alert">
                                Selecciona un bien de la lista para registrar el ticket.
                            </div>
                        </fieldset>
                    </div>
                    <div class="mb-3">
                        <label for="personalTicketTitle" class="form-label fw-semibold">Título</label>
                        <input type="text" class="form-control" id="personalTicketTitle" name="title" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="personalTicketDescription" class="form-label fw-semibold">Descripción</label>
                        <textarea class="form-control" id="personalTicketDescription" name="description" rows="4" required></textarea>
                    </div>
                    <div>
                        <label for="personalTicketObservation" class="form-label fw-semibold">Observación <span class="text-body-secondary fw-normal">(opcional)</span></label>
                        <textarea class="form-control" id="personalTicketObservation" name="observation" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Registrar ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="<?= e(url('assets/js/property-picker.js')) ?>"></script>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    document.querySelectorAll('[data-property-picker]').forEach((select) => {
        window.Systec.initPropertyPicker(select);
    });

    const registerPersonalTicketModal = document.getElementById('registerPersonalTicketModal');
    registerPersonalTicketModal.addEventListener('hide.bs.modal', () => {
        if (registerPersonalTicketModal.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });

    const personalTicketFilters = document.getElementById('personalTicketFilters');
    if (personalTicketFilters) {
        const statusFilter = document.getElementById('personalTicketStatusFilter');
        const propertyFilter = document.getElementById('personalTicketPropertyFilter');
        const searchFilter = document.getElementById('personalTicketSearch');
        const dateFromFilter = document.getElementById('personalTicketDateFrom');
        const dateToFilter = document.getElementById('personalTicketDateTo');
        const dateError = document.getElementById('personalTicketDateError');
        const filterCount = document.getElementById('personalTicketFilterCount');
        const noMatchesRow = document.getElementById('personalTicketNoMatches');
        const ticketRows = Array.from(document.querySelectorAll('#personalTicketTableBody tr[data-status-code]'));
        const statusIndicators = Array.from(document.querySelectorAll('.ticket-status-indicator'));
        const normalizeSearchText = (value) => String(value)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('es');

        const applyPersonalTicketFilters = () => {
            const searchTerm = normalizeSearchText(searchFilter.value.trim());
            const fromDate = dateFromFilter.value;
            const toDate = dateToFilter.value;
            const invalidDateRange = Boolean(fromDate && toDate && fromDate > toDate);

            [dateFromFilter, dateToFilter].forEach((input) => {
                input.classList.toggle('is-invalid', invalidDateRange);
                if (invalidDateRange) {
                    input.setAttribute('aria-invalid', 'true');
                } else {
                    input.removeAttribute('aria-invalid');
                }
            });
            dateError.classList.toggle('d-block', invalidDateRange);

            let visibleCount = 0;
            ticketRows.forEach((row) => {
                const updatedDate = row.dataset.updatedDate || '';
                const matches = !invalidDateRange
                    && (!statusFilter.value || row.dataset.statusCode === statusFilter.value)
                    && (!propertyFilter.value || row.dataset.propertyId === propertyFilter.value)
                    && (!searchTerm || normalizeSearchText(row.dataset.ticketSearch || '').includes(searchTerm))
                    && (!fromDate || updatedDate >= fromDate)
                    && (!toDate || updatedDate <= toDate);
                row.classList.toggle('d-none', !matches);
                if (matches) visibleCount++;
            });

            noMatchesRow.classList.toggle('d-none', invalidDateRange || visibleCount > 0);
            filterCount.textContent = invalidDateRange
                ? 'Corrige el rango de fechas para ver los resultados.'
                : `${visibleCount} de ${ticketRows.length} tickets`;

            statusIndicators.forEach((indicator) => {
                indicator.setAttribute('aria-pressed', String(indicator.dataset.statusFilter === statusFilter.value));
            });
        };

        personalTicketFilters.addEventListener('input', applyPersonalTicketFilters);
        personalTicketFilters.addEventListener('change', applyPersonalTicketFilters);
        personalTicketFilters.addEventListener('reset', () => window.requestAnimationFrame(applyPersonalTicketFilters));
        statusIndicators.forEach((indicator) => {
            indicator.addEventListener('click', () => {
                const nextStatus = indicator.dataset.statusFilter || '';
                statusFilter.value = statusFilter.value === nextStatus ? '' : nextStatus;
                applyPersonalTicketFilters();
            });
        });

        applyPersonalTicketFilters();
    }
</script>
<?php
$main = ob_get_clean();
include __DIR__ . '/../include/layout.php';
