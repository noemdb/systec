# Components

Canonical markup for SysTec. Copy these; adapt labels and data bindings, not the structure.

All snippets assume the page is inside `ob_start()`/`layout.php` and that `$title`, `e()`, `url()`, `csrf_field()`, `js()` and `systec_csp_nonce()` are available.

## Page header

```php
<section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
         aria-labelledby="page-title">
    <div>
        <h1 class="h3 mb-1" id="page-title">Mantenimientos</h1>
        <p class="text-body-secondary mb-0">Consulta, registra y da seguimiento a los mantenimientos.</p>
    </div>
    <button type="button" class="btn btn-primary align-self-md-start flex-shrink-0"
            data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Registrar
    </button>
</section>
```

- Use `h1.h3`; the subtitle is optional but recommended when the title is generic.
- If the page has a side form instead of a modal, the action can be a link with the same classes.
- Only one primary action. Secondary actions go in `btn-outline-secondary` or the content card header.

## KPI cards

Static:

```php
<section class="row g-3 mb-4" aria-label="Indicadores de mantenimientos">
    <div class="col-6 col-md-4 col-xl">
        <article class="card h-100 border-start border-4 border-primary shadow-sm text-start">
            <div class="card-body d-flex align-items-center gap-2">
                <span class="rounded-circle bg-primary-subtle text-primary p-2" aria-hidden="true">
                    <i class="bi bi-tools fs-4"></i>
                </span>
                <div>
                    <p class="small text-body-secondary mb-1">Total</p>
                    <p class="h3 mb-0"><?= e((string) $counts['total']) ?></p>
                </div>
            </div>
        </article>
    </div>
</section>
```

Interactive (filters the table):

```php
<button type="button"
        class="card h-100 w-100 border-start border-4 border-success shadow-sm text-start text-body js-kpi-filter"
        data-filter-status="finalizado" aria-controls="myTable" aria-pressed="false"
        title="Filtrar finalizados">
    <div class="card-body d-flex align-items-center gap-2">
        <span class="rounded-circle bg-success-subtle text-success p-2" aria-hidden="true">
            <i class="bi bi-check-circle fs-4"></i>
        </span>
        <div>
            <p class="small text-body-secondary mb-1">Finalizados</p>
            <p class="h3 mb-0"><?= e((string) $counts['finalizado']) ?></p>
        </div>
    </div>
</button>
```

Rules:

- Interactive cards are `<button type="button">` with `.w-100`, `text-start`, `text-body`, `aria-controls`, `aria-pressed`.
- Style hover/focus once per page with a scoped `<style nonce>` block (or reuse `.js-inventory-indicator` semantics):

```php
<style nonce="<?= e(systec_csp_nonce()) ?>">
    .js-kpi-filter { appearance: none; font: inherit; cursor: pointer; transition: box-shadow .15s ease, transform .15s ease; }
    .js-kpi-filter:hover { transform: translateY(-1px); }
    .js-kpi-filter:focus-visible,
    .js-kpi-filter[aria-pressed="true"] { outline: 0; box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .2), var(--bs-shadow-sm); }
    @media (prefers-reduced-motion: reduce) {
        .js-kpi-filter { transition: none; }
        .js-kpi-filter:hover { transform: none; }
    }
</style>
```

- Do not mix `border-3` and `border-4`; use `border-4`.
- 3–4 cards: keep the same coherent row; 5 cards: `col-6 col-md-4 col-xl`.

## Filters

### Card form (server-side or URL-driven)

```php
<section class="card mb-4 shadow-sm text-start" aria-labelledby="filters-title">
    <div class="card-body">
        <h2 class="h6 mb-3" id="filters-title">Filtrar</h2>
        <form method="get" action="<?= e(url('page.php')) ?>">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="filterSearch">Buscar</label>
                    <input class="form-control" id="filterSearch" name="q" type="search"
                           value="<?= e($searchTerm) ?>" placeholder="Título, código o descripción">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label fw-semibold" for="filterStatus">Estado</label>
                    <select class="form-select" id="filterStatus" name="status">
                        <option value="">Todos los estados</option>
                        <!-- options -->
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search me-1" aria-hidden="true"></i>Aplicar
                    </button>
                    <a class="btn btn-outline-secondary" href="<?= e(url('page.php')) ?>">
                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar
                    </a>
                </div>
            </div>
            <?php if ($filterError !== '') : ?>
                <p class="small text-danger mb-0 mt-2" role="alert"><?= e($filterError) ?></p>
            <?php endif; ?>
        </form>

        <!-- Active chips (client-side filters) -->
        <div class="d-flex flex-wrap gap-2 mt-2" aria-label="Filtros activos" id="activeFilters"></div>
    </div>
</section>
```

Chips (rendered by JS after filtering):

```js
const chip = document.createElement('button');
chip.type = 'button';
chip.className = 'btn btn-sm btn-outline-secondary rounded-pill';
chip.dataset.filterKey = key;
chip.setAttribute('aria-label', `Quitar filtro ${label}`);
chip.textContent = `${label} ×`;
container.appendChild(chip);
```

### Inline bar (inside the table card)

Use `form-label small mb-0`, `form-select form-select-sm`, `row g-2 align-items-end` and a `text-md-end` reset column. See `maintenances/table/crud.php`.

Rules:

- Filter only the page's own content.
- Keep the default option explicit ("Todos…", "Todas…").
- Mirror active filters in the URL (`history.replaceState`) when filtering is client-side; keep server filters in the query string.
- A reset control is mandatory; label it "Limpiar filtros".
- Never hide the only way to recover from an empty result: the empty state must offer reset or create.

## DataTable card

```php
<section class="card shadow-sm border-0 text-start" aria-labelledby="records-title">
    <div class="card-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
        <h2 class="h5 mb-0" id="records-title">
            <i class="bi bi-list-task text-primary me-2" aria-hidden="true"></i>Registros
        </h2>
        <span class="small text-body-secondary" id="resultCount" role="status" aria-live="polite">
            Mostrando 0 de 0
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-sm table-hover align-middle mb-0" id="myTable">
                <caption class="visually-hidden">Registros de la página</caption>
                <thead class="table-light">
                <tr>
                    <th scope="col">Código</th>
                    <th scope="col">Descripción</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="text-center">Acciones</th>
                </tr>
                </thead>
                <tbody><!-- rows --></tbody>
            </table>
        </div>
    </div>
</section>
```

Init once, after the table markup, with Spanish language strings:

```js
const table = new DataTable('#myTable', {
    order: [],
    language: {
        search: 'Buscar: _INPUT_',
        searchPlaceholder: 'Código, descripción…',
        emptyTable: 'No hay registros.',
        zeroRecords: 'Ningún registro coincide con los filtros aplicados.',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'Sin registros',
        paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
    }
});
```

Rules:

- DataTables only when the page truly needs search/sort/paging; otherwise use a static table card.
- Keep the existing `#myTable` id if the page already has DataTables scripts.
- Provide a visible result counter with `aria-live` when the table is filtered.
- Add `data-*` attributes on `<tr>` for client-side filtering instead of re-querying the server.
- Wide tables scroll inside `.table-responsive`; never let them widen the page.

## Static table card

```php
<table class="table table-hover align-middle mb-0">
    <caption class="visually-hidden">Descripción de la tabla</caption>
    <thead class="table-light">
    <tr>
        <th scope="col">ID</th>
        <th scope="col">Descripción</th>
        <th scope="col">Estado</th>
        <th scope="col" class="text-end">Acción</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td><span class="fw-semibold">#12</span></td>
        <td>
            <span class="fw-semibold">Nombre principal</span>
            <div class="small text-body-secondary">Detalle secundario</div>
        </td>
        <td><!-- badge --></td>
        <td class="text-end"><!-- actions --></td>
    </tr>
    </tbody>
</table>
```

## Badges

Use helpers instead of ad-hoc classes:

```php
<?= sat_badge(badge_clase_bien((string) $property['status']), property_status_label((string) $property['status'])) ?>
<?= sat_badge(badge_clase_mantenimiento((string) $maintenance['status']), texto_mantenimiento((string) $maintenance['status'])) ?>
```

Semantic map:

| Value | Class |
| --- | --- |
| BUENO / finalizado / activo | `text-bg-success` |
| REGULAR / en revisión / pendiente | `text-bg-warning` |
| MALO / vencido / error | `text-bg-danger` |
| iniciado / en progreso / preventivo | `text-bg-info` |
| preventivo | `text-bg-primary` |
| desconocido / inactivo | `text-bg-secondary` |

Legacy badges (`badge text-bg-{color}`) are fine when the status comes as a class already; do not invent new colors.

## Row actions

```php
<td class="text-end">
    <div class="btn-group btn-group-sm" role="group" aria-label="Acciones del registro 12">
        <button type="button" class="btn btn-outline-secondary"
                data-bs-toggle="modal" data-bs-target="#detailModal"
                aria-label="Ver detalle" title="Ver detalle">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
        <button type="button" class="btn btn-outline-primary"
                data-bs-toggle="modal" data-bs-target="#editModal"
                aria-label="Editar" title="Editar">
            <i class="bi bi-pencil" aria-hidden="true"></i>
        </button>
    </div>
</td>
```

- Group related actions in `.btn-group`.
- Icon-only buttons need `aria-label` + `title`.
- Use `.property-action-btn` (2.4 rem square) for uniform square actions.
- Destructive actions use `btn-outline-danger` and a confirmation modal.
- If there are more than three actions, use a dropdown (`btn-outline-secondary dropdown-toggle` + `dropdown-menu`).

## Empty state

```php
<section class="card shadow-sm border-0 text-center" aria-label="Sin registros">
    <div class="card-body py-4">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary p-3 mb-3"
              aria-hidden="true">
            <i class="bi bi-inbox fs-3"></i>
        </span>
        <h2 class="h5 mb-1">Aún no hay registros</h2>
        <p class="text-body-secondary mb-3">Cuando registres el primero, aparecerá aquí.</p>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Registrar
        </button>
    </div>
</section>
```

Differentiate "no data yet" from "no matches for these filters"; the latter offers "Limpiar filtros".

## Form modals

```php
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-start">
            <form action="<?= e(url('module/update.php')) ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="editModalTitle">Editar registro</h2>
                        <p class="small text-body-secondary mb-0">Código: <span id="editCode"></span></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="editName">Nombre</label>
                            <input class="form-control" id="editName" name="name" required>
                        </div>
                        <!-- more fields -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1" aria-hidden="true"></i>Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

Rules:

- `modal-lg modal-dialog-scrollable` for multi-field forms; `modal-dialog-centered` for short ones.
- Header always includes context (code, name) when editing an existing record.
- Footer: cancel left (`btn-outline-secondary`), confirm right (`btn-primary`), icon on confirm.
- Long forms may instead use an end offcanvas (see below).

### Offcanvas create drawer

```php
<div class="offcanvas offcanvas-end" tabindex="-1" id="createDrawer" aria-labelledby="createDrawerTitle"
     style="--bs-offcanvas-width: min(34rem, 100vw);">
    <div class="offcanvas-header border-bottom">
        <div>
            <h2 class="offcanvas-title h5 mb-1" id="createDrawerTitle">Registrar</h2>
            <p class="small text-body-secondary mb-0">Completa los datos para crear el registro.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <form class="d-flex flex-column flex-grow-1 overflow-hidden" action="<?= e(url('module/register.php')) ?>" method="POST">
        <?= csrf_field() ?>
        <div class="offcanvas-body overflow-auto"><!-- fields --></div>
        <div class="border-top p-3 d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary flex-fill">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Registrar
            </button>
        </div>
    </form>
</div>
```

Prefer the shared class pattern from `maintenances.php` (`.maintenance-create-drawer`) over inline styles when the page already has scoped CSS.

## Confirm modal

```php
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-start">
            <form method="POST" action="<?= e(url('module/delete.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="confirmId">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="confirmModalTitle">Confirmar acción</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">¿Confirmas esta acción?</p>
                    <p class="small text-body-secondary mb-0">Registro: <span id="confirmCode"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

- Use a modal, never `confirm()`/`alert()`.
- Populate title, message, confirm color and icon from `data-*` attributes on the trigger.
- For state transitions, keep a single modal and switch the button color/label (see `maintenanceTransitionModal`).

## Detail modal

Standard read-only:

```php
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-start">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="detailModalTitle">Detalle</h2>
                    <p class="small text-body-secondary mb-0">Registro: <span id="detailCode"></span></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <dl class="row g-2 mb-0">
                    <dt class="col-sm-4">Campo</dt>
                    <dd class="col-sm-8 text-break" id="detailField"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
```

Wide "bento" detail (tickets, personal):

```php
<div class="modal-dialog modal-dialog-scrollable ticket-modal-dialog">
    <div class="modal-content text-start">
        <div class="modal-header">
            <div class="ticket-modal-heading">
                <h2 class="modal-title fs-5" id="detailModalTitle">
                    <i class="bi bi-ticket-detailed text-primary me-2" aria-hidden="true"></i>Ticket
                </h2>
                <p class="small text-body-secondary text-break mb-0" id="detailSubtitle"></p>
            </div>
            <span class="badge text-bg-light border" id="detailStatus"></span>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
            <p class="small text-body-secondary mb-2" id="detailFeedback" role="status" aria-live="polite"></p>
            <div class="row g-3 bento-grid">
                <div class="col-12 col-lg-8">
                    <div class="row g-3">
                        <div class="col-12">
                            <section class="bento-card card border h-100" aria-labelledby="detailBodyTitle">
                                <div class="card-body">
                                    <h3 class="h6 mb-3" id="detailBodyTitle">
                                        <i class="bi bi-card-text me-2" aria-hidden="true"></i>Descripción
                                    </h3>
                                    <div class="ticket-modal-copy text-break" id="detailBody"></div>
                                </div>
                            </section>
                        </div>
                        <!-- more cards -->
                    </div>
                </div>
                <aside class="col-12 col-lg-4" aria-label="Datos del registro">
                    <section class="bento-card card border h-100" aria-labelledby="detailMetaTitle">
                        <div class="card-body">
                            <h3 class="h6 mb-3" id="detailMetaTitle">
                                <i class="bi bi-info-circle me-2" aria-hidden="true"></i>Datos
                            </h3>
                            <dl class="ticket-modal-meta row g-2 mb-0">
                                <dt class="col-5">Campo</dt>
                                <dd class="col-7 text-end" id="detailMetaValue"></dd>
                            </dl>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
    </div>
</div>
```

Bento rules: dialog `min(95vw, 1200px)`, content `max-height: 95vh`, cards `.75rem` radius, heading `h3.h6` in primary color, body padding `1rem`. Async content uses a loading message in `role="status"` and `AbortController` on close.

## Feedback

### Toast (flash message)

```php
$notice = isset($_SESSION['module_notice']) ? (string) $_SESSION['module_notice'] : '';
unset($_SESSION['module_notice']);
$notifications = $notice !== '' ? array(toast_notification('success', $notice)) : array();
```

`include/layout.php` renders `include/toasts.php`; the toast is fixed top-right, auto-hides after 6 s, and uses `role="status"/"alert"`. Types: `success`, `info`, `warning`, `danger`.

### Inline alerts

```php
<?php if ($formError !== '') : ?>
    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <span><?= e($formError) ?></span>
    </div>
<?php endif; ?>
```

Success banners that must persist (e.g. after a redirect) follow the same pattern with `alert-success` and `role="status"`.

### Async loading

```php
<p id="feedback" class="small text-body-secondary" role="status" aria-live="polite"></p>
```

```js
feedback.className = 'small text-body-secondary';
feedback.textContent = 'Cargando…';
// on error
feedback.className = 'small text-danger';
feedback.textContent = error.message;
```

Use `aria-busy="true"` on the container while fetching and reset it in `finally`.

## Pagination

Server-side pagination (from `personal/index.php`):

```php
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 p-3 border-top">
    <span class="small text-body-secondary">Mostrando <?= e((string) $from) ?>–<?= e((string) $to) ?> de <?= e((string) $total) ?></span>
    <nav aria-label="Paginación">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e($prevUrl) ?>" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a>
            </li>
            <li class="page-item disabled"><span class="page-link">Página <?= e((string) $page) ?> de <?= e((string) $totalPages) ?></span></li>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e($nextUrl) ?>" <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente</a>
            </li>
        </ul>
    </nav>
</div>
```

## Tooltips

Bootstrap tooltips for icon-only status hints:

```html
<i class="bi bi-exclamation-triangle-fill text-danger" data-bs-toggle="tooltip"
   data-bs-title="Mantenimiento vencido"></i>
```

```js
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
```

Do not use tooltips as the only label for interactive controls; pair with `aria-label`.
