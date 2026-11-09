<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_login();

$title = 'Dashboard';
$db = db();

$properties = $db->index('properties', array(), '*', 'id ASC');
$propertyCount = count($properties);
$goodPropertyCount = 0;
$attentionPropertyCount = 0;

foreach ($properties as $property) {
    $status = mb_strtoupper(trim((string) $property['status']));
    if ($status === 'BUENO') {
        $goodPropertyCount++;
    }
    if ($status === 'MALO' || $status === 'MUY MALO') {
        $attentionPropertyCount++;
    }
}

$canImportInventory = auth_has_role(array('admin', 'operador'));

ob_start(); ?>

    <div class="container-fluid">

        <h2 class="">Dashboard</h2>
        <p class="">Bienvenido!</p>

        <section class="row g-3 mb-4" aria-label="Indicadores del inventario">
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 border-start border-4 border-primary shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-primary-subtle text-primary p-3" aria-hidden="true">
                            <i class="bi bi-box-seam fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-muted mb-1">Bienes registrados</p>
                            <p class="h3 mb-0"><?= e($propertyCount) ?></p>
                        </div>
                    </div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 border-start border-4 border-success shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-success-subtle text-success p-3" aria-hidden="true">
                            <i class="bi bi-check-circle fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-muted mb-1">En buen estado</p>
                            <p class="h3 mb-0"><?= e($goodPropertyCount) ?></p>
                        </div>
                    </div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 border-start border-4 border-danger shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-danger-subtle text-danger p-3" aria-hidden="true">
                            <i class="bi bi-exclamation-triangle fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-muted mb-1">Requieren atención</p>
                            <p class="h3 mb-0"><?= e($attentionPropertyCount) ?></p>
                            <p class="small text-muted mb-0">Malo o muy malo</p>
                        </div>
                    </div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 border-start border-4 border-info shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-info-subtle text-info p-3" aria-hidden="true">
                            <i class="bi bi-database fs-4"></i>
                        </span>
                        <div class="flex-grow-1">
                            <p class="small text-muted mb-1">Sincronización con legacy</p>
                            <p class="h5 mb-1">Manual</p>
                            <p class="small text-muted mb-2">PostgreSQL se consulta en solo lectura.</p>
                            <?php if ($canImportInventory) : ?>
                                <a class="small fw-semibold" href="<?= e(url('updates.php')) ?>">
                                    Importar bienes <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <div class="card">
            <div class="card-body">

                <h3>Lista de Bienes Registrados</h3>

                <?php include __DIR__ . '/dashboard/table/index.php'; ?>

            </div>
        </div>

    </div>

    <script type="text/javascript">
        const search = <?= js(isset($_GET['search']) ? $_GET['search'] : '') ?>;

        const table = new DataTable('#myTable', {
            search: {
                search: search ?? ''
            },
            order: [4, 'asc']
        });

        table.on('search', () => {
            const url = new URL(window.location.href);
            const params = new URLSearchParams(url);

            params.set('search', table.search())

            const newUrl = window.location.pathname + '?' + params.toString();
            history.replaceState(null, '', newUrl);
        })
    </script>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
