---
name: ui-better
description: "Redesign or create any SysTec page with the system's established visual language: compact Bootstrap 5 layout, KPI cards, filter bars, DataTables, modals/offcanvas, badges, toasts and responsive behavior. Use when a page must match dashboard.php, maintenances.php or tickets.php, or when harmonizing inconsistent UI."
---

# UI Better

Turn any SysTec page into a coherent, compact, responsive screen that feels like the rest of the system. The reference implementations are `dashboard.php` and `maintenances.php` (plus `tickets.php` and `personal/index.php`); this skill distills their shared language into reusable rules and copy-paste recipes.

**Principle:** preserve behavior, change presentation. Never trade authorization, validation, CSRF, routing or data for a nicer layout.

## Non-negotiables

1. Keep the shared shell. Pages prepare data, call `ob_start()`, render markup, then `$main = ob_get_clean(); include __DIR__ . '/include/layout.php';`. Never duplicate header, footer or `<head>`.
2. Keep the page's existing PHP logic, permissions (`auth_has_role`), CSRF (`csrf_field()`), redirects, flash messages and endpoints. Move code only to separate data preparation from markup.
3. Escape everything: `e()` for HTML, `url()` for links/actions, `js()` for values injected into `<script>`, `csrf_field()` in every form.
4. Use Bootstrap 5.3 + Bootstrap Icons already bundled. No new CSS/JS dependencies.
5. Shared styling belongs in `responsive.css`; page-specific tweaks go in a scoped `<style nonce="<?= e(systec_csp_nonce()) ?>">`. Never edit `vendor/`.
6. Every screen must be usable at 320 px wide: stack before going side by side, wrap controls, scroll wide tables inside their wrapper, keep modals scrollable, no horizontal page overflow.

## Workflow

1. **Recon.** Read the target page end to end and its includes. List its features: data shown, actions, filters, forms, modals, role gates, empty states, feedback. Do the same for `dashboard.php` and `maintenances.php` if you need a refresher.
2. **Behavior contract.** Write down what must not change (queries, POST targets, permissions, redirect targets, validation rules, flash keys). Keep it visible while editing.
3. **Map to components.** Assign each block to a canonical component: page header, KPI row, filter card/bar, table card, detail modal, form modal/offcanvas, toast/alerts, empty state. Drop components the page does not need.
4. **Apply the skeleton** (below) and the tokens in [design-tokens.md](references/design-tokens.md).
5. **Build each component** from [components.md](references/components.md). Copy the recipes in [page-recipes.md](references/page-recipes.md) instead of inventing new markup.
6. **Responsive + accessibility pass** using [audit-checklist.md](references/audit-checklist.md).
7. **Verify.** `php -l` on changed files, run `vendor/bin/phpunit`, `git diff --check`, and render the page (CLI include or browser) at narrow and desktop widths. Report anything you could not verify.

## Canonical page skeleton

```php
<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_login();

$title = 'Nombre de la página';
// ... data preparation, permissions, filters, counts, flash messages ...

ob_start(); ?>
<div class="container-fluid">
    <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
             aria-labelledby="page-title">
        <div>
            <h1 class="h3 mb-1" id="page-title">Título</h1>
            <p class="text-body-secondary mb-0">Una frase que explique qué puede hacer el usuario aquí.</p>
        </div>
        <button type="button" class="btn btn-primary align-self-md-start flex-shrink-0"
                data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Acción principal
        </button>
    </section>

    <!-- KPI row (only if the page has meaningful counts) -->
    <section class="row g-3 mb-4" aria-label="Indicadores de la página">
        <!-- col-6 col-md-4 col-xl per card -->
    </section>

    <!-- Filters (only if they narrow real content) -->
    <section class="card mb-4 shadow-sm text-start" aria-labelledby="filters-title">
        <div class="card-body">
            <h2 class="h6 mb-3" id="filters-title">Filtrar</h2>
            <div class="row g-3 align-items-end"><!-- controls --></div>
        </div>
    </section>

    <!-- Main content -->
    <section class="card shadow-sm border-0 text-start" aria-labelledby="content-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <h2 class="h5 mb-0" id="content-title">
                <i class="bi bi-list-task text-primary me-2" aria-hidden="true"></i>Registros
            </h2>
            <span class="badge text-bg-light border text-body-secondary"><?= e((string) $count) ?> registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <!-- table -->
            </div>
        </div>
    </section>
</div>

<!-- modals / offcanvas / scripts -->

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
```

Order matters: **title → context → primary action → indicators → filters → content → detail/forms**. Keep the vertical rhythm `mb-3` between header and first block, `mb-4` between major sections.

## Component index

| Need | Component | Reference |
| --- | --- | --- |
| Page title + action | Header section | [components.md](references/components.md#page-header) |
| Counts / state summary | KPI card row | [components.md](references/components.md#kpi-cards) |
| Narrow content | Filter card + active chips | [components.md](references/components.md#filters) |
| Table with search/paging | DataTable card | [components.md](references/components.md#datatable-card) |
| Simple list | Static table card | [components.md](references/components.md#static-table-card) |
| Row states | Badges | [components.md](references/components.md#badges) |
| Row actions | Button group | [components.md](references/components.md#row-actions) |
| No data | Empty state | [components.md](references/components.md#empty-state) |
| Create/edit form | Modal or offcanvas | [components.md](references/components.md#form-modals) |
| Destructive/state change | Confirm modal | [components.md](references/components.md#confirm-modal) |
| Read-only details | Detail modal / bento | [components.md](references/components.md#detail-modal) |
| Feedback | Toast / alert | [components.md](references/components.md#feedback) |

## Design rules in one paragraph

Compact, not cramped: the shared CSS already reduces font size to 90%, compresses gutters (`g-3` = 1rem/.5rem), card body to `.75rem`, card header/footer to `.5rem .75rem`, margins `mb-3`→`.5rem`, `mb-4`→`.75rem`. Do not shrink body text or touch targets further. Use `shadow-sm`, `border-start border-4` on KPI cards, subtle `bg-*-subtle` icon circles, `text-body-secondary` for supporting copy, `table-light` headers, and semantic colors only (primary = neutral/global, info = in progress, warning = pending/attention, danger = critical/overdue, success = done, secondary = unknown/idle). Titles use `h1.h3`, section titles `h2.h5` (card header) or `h2.h6` (filter card). Icons are `fs-4` inside KPI circles, `me-1`/`me-2` when inline.

## Responsive rules

- Cards: `col-6 col-md-4 col-xl` for 5 KPIs; `col-12 col-sm-6 col-xl` for 4–6 wide cards; single column below `sm` for dense content.
- Tables: always inside `.table-responsive`; add an overflow-y-hidden helper class when DataTables needs it.
- Header: `flex-column flex-md-row` and `align-self-md-start` on the action.
- Modals: `modal-lg modal-dialog-scrollable` for forms; `modal-dialog-centered` for confirmations; add `margin: .5rem` behavior already comes from `responsive.css` below 768 px.
- Long forms: modal body scrolls (`modal-dialog-scrollable`), or use an end offcanvas `width: min(34rem, 100vw)`.
- Never set fixed `width`/`height` in px on containers or table cells; use `min-width: 0`, `text-nowrap` only on codes/dates, `text-break` on long descriptions.

## Accessibility rules

- One `h1` per page; section titles `h2`; keep heading levels in order.
- Every `<section>` that starts a region has `aria-label` or `aria-labelledby`.
- Every input has a `<label for>`; icon-only buttons have `aria-label` + `title`.
- Interactive KPI cards are `<button type="button">` with `aria-pressed`; static KPIs are `<article>`.
- Dynamic counts/feedback use `role="status"` + `aria-live="polite"`; errors use `role="alert"`.
- Tables include a `<caption class="visually-hidden">`; wide tables never widen the page.
- Preserve visible focus; respect `prefers-reduced-motion` when adding transitions.

## Verification

```bash
php -l path/to/page.php
vendor/bin/phpunit
git diff --check
bash ~/.agents/skills/ui-better/scripts/audit-page.sh path/to/page.php
bash ~/.agents/skills/ui-better/scripts/audit-page.sh --fragment include/partial.php   # includes/tablas/modales
```

The script checks one file at a time: `--fragment` skips page-shell checks for includes and partials.

Then render and inspect at 320 px, 768 px and desktop widths. Check: no horizontal scroll, no clipped modal, focus order sensible, empty state correct, filters reset, counters update.

## Anti-patterns

- Copying dashboard inventory metrics, adscription sync or import actions into unrelated pages.
- Adding DataTables, charts or modals just to look like the dashboard.
- New global CSS, new colors, new fonts, new icon sets, or edits to `vendor/`.
- Inline `style="..."` for layout; duplicated layout/header/footer markup.
- `text-center` on page content (the shell centers text by default; page wrappers use `text-start`).
- Icon-only actions without accessible names; clickable `<div>`s; `onclick` handlers.
- Changing endpoints, validation, permissions or flash keys during a visual refactor.
- Replacing real empty states with lorem ipsum or hiding them.
- Mixing `border-3`/`border-4`, `h1.h2`/`h1.h3`, `text-muted`/`text-body-secondary` in the same screen.

## Reference map

- [references/reference-pages.md](references/reference-pages.md) — feature inventory of `dashboard.php` and `maintenances.php`, and which patterns to reuse.
- [references/design-tokens.md](references/design-tokens.md) — typography, spacing, color, border, shadow and breakpoint values actually in effect.
- [references/components.md](references/components.md) — markup and rules per component.
- [references/page-recipes.md](references/page-recipes.md) — full-page template and task-oriented recipes.
- [references/audit-checklist.md](references/audit-checklist.md) — pre/post refactor checklist.
