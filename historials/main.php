<?php
$types = array();
$technician = '';
$ticketActionLabels = array(
    'created' => 'Ticket creado',
    'status_change' => 'Cambio de estado',
    'comment' => 'Seguimiento',
    'deleted' => 'Ticket eliminado',
);
?>

<div class="table-responsive">
    <table class="table table-bordered table-sm">
        <caption class="visually-hidden">Historial de mantenimientos del bien</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Fecha</th>
                <th scope="col">Tipo</th>
                <th scope="col">Técnico</th>
                <th scope="col">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($maintenances as $maintenance) : ?>
                <tr>
                    <td class="small"><?= e($property['id']) ?></td>
                    <td class="small text-nowrap"><?= e(date('d-m-Y', strtotime($maintenance['date']))) ?></td>
                    <td class="small"><?= sat_badge(badge_clase_tipo((string) $maintenance['type']), texto_tipo((string) $maintenance['type'])) ?></td>
                    <td class="small"><?= e($maintenance['technician']) ?></td>
                    <td class="small"><?= sat_badge(badge_clase_mantenimiento((string) $maintenance['status']), texto_mantenimiento((string) $maintenance['status'])) ?></td>
                </tr>
                <tr>
                    <td colspan="5" class="small" style="border-bottom: 5px #ccc solid !important;">
                        <i>Descripción:</i> <?= e($maintenance['description']) ?>
                    </td>
                </tr>
                <?php $types[$maintenance['type']] = array_key_exists($maintenance['type'], $types) ? $types[$maintenance['type']] + 1 : 1; ?>
                <?php $technician = $maintenance['technician']; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<section class="container-fluid text-start mt-2" aria-labelledby="ticket-history-title">
    <h2 class="h6 mb-1" id="ticket-history-title">
        <i class="bi bi-ticket-detailed text-primary me-2" aria-hidden="true"></i>
        Tickets asociados al bien
        <span class="badge text-bg-light border text-body-secondary"><?= e((string) count($tickets)) ?></span>
    </h2>
    <?php if ($tickets === array()) : ?>
        <p class="text-body-secondary mb-0">No hay tickets registrados para este bien.</p>
    <?php else : ?>
        <div class="d-grid gap-1">
            <?php foreach ($tickets as $ticket) :
                $ticketId = (int) $ticket['id'];
                $ticketDetails = $ticketDetailsById[$ticketId];
                $status = $ticketDetails['status'];
                ?>
                <article class="card shadow-sm">
                    <div class="card-header py-1 px-2 d-flex flex-wrap align-items-center justify-content-between gap-1">
                        <div class="d-flex flex-wrap align-items-baseline column-gap-2 row-gap-0">
                            <span class="fw-semibold">Ticket #<?= e((string) $ticketId) ?></span>
                            <span class="small text-body-secondary"><?= e((string) $ticket['created_at']) ?></span>
                            <h3 class="h6 mb-0 text-break"><?= e((string) $ticket['title']) ?></h3>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="badge text-bg-<?= e($ticketDetails['status_class']) ?>">
                                <?= e((string) $status['name']) ?>
                            </span>
                            <?php if (!empty($ticket['deleted_at'])) : ?>
                                <span class="badge text-bg-dark">Eliminado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body py-1 px-2">
                        <div class="d-flex flex-wrap column-gap-3 row-gap-0 small text-body-secondary mb-1">
                            <span><i class="bi bi-person me-1" aria-hidden="true"></i>Creado: <?= e((string) $ticketDetails['creator']['username']) ?></span>
                            <span><i class="bi bi-person-check me-1" aria-hidden="true"></i>Asignado: <?= e((string) ($ticketDetails['assignee']['username'] ?? 'Sin asignar')) ?></span>
                            <?php if (!empty($ticket['closed_at'])) : ?>
                                <span><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Cerrado: <?= e((string) $ticket['closed_at']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($ticket['deleted_at'])) : ?>
                                <span><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminado: <?= e((string) $ticket['deleted_at']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap column-gap-3 row-gap-0 small mb-1 text-break">
                            <span><span class="text-body-secondary">Descripción:</span> <?= nl2br(e((string) $ticket['description'])) ?></span>
                            <?php if (!empty($ticket['observation'])) : ?>
                                <span><span class="text-body-secondary">Observación:</span> <?= nl2br(e((string) $ticket['observation'])) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="border-top pt-1">
                            <h4 class="small fw-semibold mb-1">Seguimiento</h4>
                            <?php if ($ticketDetails['history'] === array()) : ?>
                                <p class="small text-body-secondary mb-0">Todavía no hay acciones registradas.</p>
                            <?php else : ?>
                                <ol class="list-unstyled mb-0">
                                    <?php foreach ($ticketDetails['history'] as $entry) : ?>
                                        <li class="border-start border-2 ps-2 mb-1">
                                            <div class="d-flex flex-wrap align-items-baseline column-gap-2 row-gap-0 small text-break">
                                                <span class="fw-semibold"><?= e((string) ($ticketActionLabels[$entry['action_type']] ?? $entry['action_type'])) ?></span>
                                                <span class="text-body-secondary"><?= e((string) ($entry['actor_username'] ?? 'Usuario no disponible')) ?></span>
                                                <time class="small text-body-secondary ms-sm-auto"><?= e((string) $entry['created_at']) ?></time>
                                                <span class="text-break"><?= nl2br(e((string) $entry['description'])) ?></span>
                                            </div>
                                            <?php if (!empty($entry['observation'])) : ?>
                                                <div class="small text-body-secondary text-break">
                                                    <span class="fw-semibold">Observación:</span> <?= nl2br(e((string) $entry['observation'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="container-fluid text-start" style="font-size:0.8rem">
    <div>Observaciones:</div>
    <ul class="list-group list-group-numbered list-group-flush">
        <?php foreach ($maintenances as $maintenance) : ?>
            <?php if (!empty($maintenance['notes'])) : ?>
                <li class="list-group-item my-0 p-0">
                    <small class="small">[<?= e(date('d-m-Y', strtotime($maintenance['date']))) ?>]</small>
                    <?= e($maintenance['notes']) ?>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</div>

<p>&nbsp;</p>
