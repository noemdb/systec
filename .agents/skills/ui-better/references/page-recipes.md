# Page recipes

Task-oriented recipes. Each one is additive and preserves current page behavior.

## 1. Full page template

Start from the page's existing PHP; keep its logic above `ob_start()`. Replace only the markup below the buffer.

```php
<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_login();

$title = 'Página';
$db = db();

// --- data, permissions, filters, counts (existing logic) ---
$rows = $db->index('table', array(), '*', 'id DESC');
$counts = array('total' => count($rows));
// ...
// --- flash feedback ---
$notice = isset($_SESSION['page_notice']) ? (string) $_SESSION['page_notice'] : '';
unset($_SESSION['page_notice']);
$notifications = $notice !== '' ? array(toast_notification('success', $notice)) : array();

ob_start(); ?>
<div class="container-fluid">
    <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
             aria-labelledby="page-title">
        <div>
            <h1 class="h3 mb-1" id="page-title">Página</h1>
            <p class="text-body-secondary mb-0">Qué puede hacer el usuario en esta pantalla.</p>
        </div>
        <button type="button" class="btn btn-primary align-self-md-start flex-shrink-0"
                data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Registrar
        </button>
    </section>

    <section class="row g-3 mb-4" aria-label="Indicadores">
        <!-- KPI cards -->
    </section>

    <section class="card mb-4 shadow-sm text-start" aria-labelledby="filters-title">
        <div class="card-body">
            <h2 class="h6 mb-3" id="filters-title">Filtrar</h2>
            <form method="get" action="<?= e(url('page.php')) ?>">
                <div class="row g-3 align-items-end">
                    <!-- filters -->
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm border-0 text-start" aria-labelledby="records-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <h2 class="h5 mb-0" id="records-title">
                <i class="bi bi-list-task text-primary me-2" aria-hidden="true"></i>Registros
            </h2>
            <span class="badge text-bg-light border text-body-secondary"><?= e((string) $counts['total']) ?> registros</span>
        </div>
        <?php if ($rows === array()) : ?>
            <?php /* empty state */ ?>
        <?php else : ?>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Registros de la página</caption>
                        <thead class="table-light">
                        <tr>
                            <th scope="col">Campo</th>
                            <th scope="col" class="text-end">Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><?= e((string) $row['field']) ?></td>
                                <td class="text-end"><!-- actions --></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php /* modals / offcanvas / scripts */ ?>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
```

## 2. Add a KPI row from existing counts

1. Compute counts in PHP before the buffer (single pass over the data when possible).
2. Define an array of indicator descriptors: label, value, semantic color, icon.
3. Render with `foreach` to keep markup consistent.

```php
$indicators = array(
    array('Total', $counts['total'], 'primary', 'bi-box-seam'),
    array('En progreso', $counts['in_progress'], 'info', 'bi-hourglass-split'),
    array('Pendientes', $counts['pending'], 'warning', 'bi-exclamation-triangle'),
    array('Finalizados', $counts['closed'], 'success', 'bi-check-circle'),
);
?>
<section class="row g-3 mb-4" aria-label="Indicadores">
    <?php foreach ($indicators as [$label, $value, $color, $icon]) : ?>
        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-<?= e($color) ?> shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-2">
                    <span class="rounded-circle bg-<?= e($color) ?>-subtle text-<?= e($color) ?> p-2" aria-hidden="true">
                        <i class="bi <?= e($icon) ?> fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1"><?= e($label) ?></p>
                        <p class="h3 mb-0"><?= e((string) $value) ?></p>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</section>
```

If the KPIs filter the table, render `<button type="button">` with `aria-pressed` and a `data-filter-*` attribute, then connect them in JS.

## 3. Add filters to an existing table

Client-side (data already in the page):

1. Add a filter card with controls.
2. Add `data-status`, `data-category`, `data-search`, `data-date` attributes to each `<tr>`.
3. Filter rows in JS, toggle `.d-none`, and update a visible counter + `aria-live`.
4. Add a chip container and a reset handler.
5. Keep the URL in sync with `history.replaceState`.

Server-side (many rows):

1. Read filters from `$_GET`, validate them, and build the query.
2. Preserve filters in pagination and KPI links.
3. Render the form with `method="get"` and the current values.
4. Show a validation error with `role="alert"`.

Do not mix both approaches in one table.

## 4. Add a DataTable to a static table

1. Give the table `id="myTable"` and the classes `table table-striped table-sm table-hover align-middle mb-0`.
2. Wrap it in `.table-responsive`.
3. Add `<caption class="visually-hidden">`.
4. Initialize after the markup:

```php
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const table = new DataTable('#myTable', {
        language: {
            search: 'Buscar: _INPUT_',
            emptyTable: 'No hay registros.',
            zeroRecords: 'Ningún registro coincide con los filtros.'
        }
    });
</script>
```

5. If the page has column filters, use `.js-col-filter` with `data-column` and call `table.column(n).search(value).draw()`.
6. If filters are custom predicates, register `$.fn.dataTable.ext.search.push()` and always check `settings.nTable.id === 'myTable'`.

## 5. Add a create/edit form

Modal for ≤8 fields; offcanvas for long forms or when the table must stay visible.

```php
<form action="<?= e(url('module/register.php')) ?>" method="POST">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-12 col-md-6">
            <label class="form-label" for="fieldName">Nombre</label>
            <input class="form-control" id="fieldName" name="name" required>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="fieldDate">Fecha</label>
            <input class="form-control" id="fieldDate" name="date" type="date">
        </div>
        <div class="col-12">
            <label class="form-label" for="fieldNotes">Notas <span class="text-body-secondary fw-normal">(opcional)</span></label>
            <textarea class="form-control" id="fieldNotes" name="notes" rows="3"></textarea>
        </div>
    </div>
    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1" aria-hidden="true"></i>Guardar
        </button>
    </div>
</form>
```

Populate edit fields with JSON in `data-*`:

```php
<button type="button" class="btn btn-outline-primary btn-sm"
        data-bs-toggle="modal" data-bs-target="#editModal"
        data-record="<?= e(js($recordData)) ?>"
        aria-label="Editar registro" title="Editar">
    <i class="bi bi-pencil" aria-hidden="true"></i>
</button>
```

```js
document.getElementById('editModal').addEventListener('show.bs.modal', (event) => {
    const data = JSON.parse(event.relatedTarget.dataset.record);
    document.getElementById('editId').value = data.id;
    document.getElementById('editName').value = data.name ?? '';
});
```

## 6. Add flash feedback after a redirect

1. In the action handler, before redirecting:

```php
$_SESSION['module_notice'] = 'Registro guardado correctamente.';
header('Location: ' . url('module/index.php'));
exit();
```

2. In the page, before `ob_start()`:

```php
$notice = isset($_SESSION['module_notice']) ? (string) $_SESSION['module_notice'] : '';
unset($_SESSION['module_notice']);
$notifications = $notice !== '' ? array(toast_notification('success', $notice)) : array();
```

3. `layout.php` renders the toast automatically. Never echo the raw session value.

## 7. Add an empty state

Wrap the table card body:

```php
<?php if ($rows === array()) : ?>
    <div class="card-body py-4 text-center">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary p-3 mb-3" aria-hidden="true">
            <i class="bi bi-inbox fs-3"></i>
        </span>
        <?php if ($hasActiveFilters) : ?>
            <h2 class="h5 mb-1">Sin resultados</h2>
            <p class="text-body-secondary mb-3">Prueba con otros filtros o límpialos.</p>
            <a class="btn btn-outline-secondary" href="<?= e(url('module/index.php')) ?>">Limpiar filtros</a>
        <?php else : ?>
            <h2 class="h5 mb-1">Aún no hay registros</h2>
            <p class="text-body-secondary mb-3">Cuando registres el primero, aparecerá aquí.</p>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Registrar
            </button>
        <?php endif; ?>
    </div>
<?php endif; ?>
```

## 8. Gate actions by role

```php
<?php $canEdit = auth_has_role(array('admin', 'operador', 'tecnico')); ?>
<?php if ($canEdit) : ?>
    <!-- edit / transition buttons -->
<?php endif; ?>
```

Never hide data server-side behind CSS alone; if a user must not see it, do not query/render it.

## 9. Add an accessible toggle (overdue only, hide/show, etc.)

```html
<div class="form-check">
    <input class="form-check-input" type="checkbox" id="onlyOverdue">
    <label class="form-check-label small" for="onlyOverdue">
        Solo vencidos <i class="bi bi-exclamation-triangle-fill text-danger" aria-hidden="true"></i>
    </label>
</div>
```

```js
const onlyOverdue = document.getElementById('onlyOverdue');
onlyOverdue.addEventListener('change', () => {
    table.filterOverdue = onlyOverdue.checked;
    table.draw();
});
```

## 10. Harmonize an inconsistent page

1. Compare against the canonical skeleton; list deviations (headings, spacing, card borders, button order, badge colors, table classes, missing labels).
2. Fix in this order: shell/heading → spacing/grid → cards/KPIs → table → forms/modals → feedback → responsive/accessibility.
3. Keep behavior untouched; if a fix requires logic changes, stop and treat it as a separate change.
4. Remove dead CSS left behind by removed components, but only when nothing else references the selectors (`grep -rn 'selector' .`).
5. Re-check both `php -l` and the rendered page.
