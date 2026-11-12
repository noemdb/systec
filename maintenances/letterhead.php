<header class="batch-letterhead" aria-labelledby="maintenance-batch-title">
    <div class="batch-letterhead-top">
        <div>
            <p class="batch-office">Oficina de Informática</p>
            <p class="batch-location">San Felipe, Estado Yaracuy</p>
        </div>
        <div class="batch-meta">
            <p>Fecha de emisión: <?= e(date('d-m-Y')) ?></p>
            <p><?= e((string) $matchedCount) ?> <?= $matchedCount === 1 ? 'mantenimiento' : 'mantenimientos' ?></p>
            <p><?= e((string) count($histories)) ?> <?= count($histories) === 1 ? 'bien' : 'bienes' ?></p>
        </div>
    </div>
    <h1 class="h3 batch-title" id="maintenance-batch-title">Historiales de mantenimiento</h1>
    <p class="batch-subtitle">
        Lote de historiales por bien. Cada historial incluye todos los mantenimientos registrados del bien
        y se imprime en una página independiente.
    </p>
    <p class="batch-criteria"><?= e($criteriaText) ?></p>
</header>
