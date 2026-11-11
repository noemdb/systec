<?php
$toastTypes = array(
    'success' => array('label' => 'Éxito', 'icon' => 'bi-check-circle-fill', 'role' => 'status'),
    'info' => array('label' => 'Información', 'icon' => 'bi-info-circle-fill', 'role' => 'status'),
    'warning' => array('label' => 'Atención', 'icon' => 'bi-exclamation-circle-fill', 'role' => 'alert'),
    'danger' => array('label' => 'Error', 'icon' => 'bi-exclamation-triangle-fill', 'role' => 'alert'),
);
$notifications = isset($notifications) && is_array($notifications) ? $notifications : array();
?>
<?php if ($notifications) : ?>
<div class="toast-container app-toast-container position-fixed top-0 end-0 p-3">
    <?php foreach ($notifications as $notification) :
        if (!is_array($notification)) {
            continue;
        }
        $type = isset($notification['type'])
            && is_string($notification['type'])
            && isset($toastTypes[$notification['type']])
            ? $notification['type']
            : 'info';
        $message = isset($notification['message']) && is_string($notification['message'])
            ? trim($notification['message'])
            : '';
        if ($message === '') {
            continue;
        }
        $toast = $toastTypes[$type];
    ?>
    <div class="toast show app-toast border-start border-4 border-<?= e($type) ?> text-start"
         role="<?= e($toast['role']) ?>"
         aria-live="<?= $toast['role'] === 'alert' ? 'assertive' : 'polite' ?>"
         aria-atomic="true"
         data-bs-autohide="true"
         data-bs-delay="6000">
        <div class="toast-header">
            <i class="bi <?= e($toast['icon']) ?> text-<?= e($type) ?> me-2" aria-hidden="true"></i>
            <strong class="me-auto"><?= e($toast['label']) ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
        <div class="toast-body"><?= e($message) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
