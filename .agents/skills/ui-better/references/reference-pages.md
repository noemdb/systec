# Reference pages study

Observed directly in `dashboard.php`, `maintenances.php` and their includes. Use this to decide which pattern to copy and which inconsistencies to normalize.

## `dashboard.php` — feature inventory

**Purpose:** operational overview of inventory plus quick maintenance actions.

| Area | Behavior |
| --- | --- |
| Data | Properties, completed maintenance count, ticket count (role-gated), maintenance counts per property, adscription map from the legacy source. |
| KPI row | 5–6 cards: total properties, good condition, needs attention, completed maintenance, tickets (role-gated), legacy sync status. Three are interactive buttons that filter the table by status; the rest are static `<article>` cards. |
| Filter panel | Card with category, status, adscription and reset; active filter chips rendered by JS; result counter with `aria-live`. |
| Table | DataTables (`#myTable`, `table-striped table-sm table-hover`), row `data-category/adscription/status`, hidden caption, result count, active chips, per-row icon action group: register maintenance, history, change property status (role-gated). |
| Modals | Register maintenance (modal-lg scrollable), wide history modal (90 vw/90 vh, async fetch + print), change status (centered, form). |
| Feedback | Toast from `$_SESSION['dashboard_maintenance_feedback']`; reopen maintenance modal when the previous submission failed (`danger`). |
| JS behaviors | Sync filters to URL, indicator `aria-pressed` state, smooth scroll to table, DataTables custom predicates, generated chips, abortable history fetch with loading/error/`aria-busy`, print window. |

**Patterns worth reusing:** interactive KPI cards wired to a table filter; filter chips; abortable async modal with loading/error states; role-gated row actions; toast feedback driven by session; URL state sync.

## `maintenances.php` — feature inventory

**Purpose:** register, inspect and move maintenance records through their lifecycle.

| Area | Behavior |
| --- | --- |
| Data | All maintenances, property catalog with categories, counts by status and overdue count. |
| KPI row | 5 cards: total, started, in review, finished, overdue, with semantic colors and icons. |
| Table | DataTables with category/status/type/technician column filters, "solo vencidos" toggle, reset, per-row detail/edit/transition actions, overdue badge and tooltip. |
| Create | End offcanvas drawer (34 rem) with the shared form fields, CSRF, submit/cancel footer. |
| Edit | Legacy side panel (`modeEdit=true`) **and** a modal edit form populated from JSON. |
| State changes | Confirmation modal with dynamic title, message, color and icon for "send to review" / "finish". |
| Detail | Modal with `<dl>` for all fields. |
| Feedback | Validation errors handled server-side; offcanvas reopens with `?modeCreate=true`. |
| JS behaviors | DataTables init, URL sync for category/search, column filters, custom overdue filter, tooltips, modal population. |

**Patterns worth reusing:** KPI summary trio/five; inline filter bar; column filters; single transition modal reused for multiple actions; offcanvas for long create forms; detail modal with `<dl>`; overdue tooltip; role-gated edit/transition buttons.

## Shared visual grammar (copy this)

- Page wrapper: `.container-fluid`, sections separated by `mb-3`/`mb-4`.
- KPIs: `card h-100 border-start border-4 border-{semantic} shadow-sm`, icon circle `p-2` + `fs-4`, `small text-muted/body-secondary` label, `h3` value.
- Cards: `shadow-sm`; content cards often `border-0`; header with `h2.h5` + icon + count badge.
- Tables: `table-responsive` > `table table-striped table-sm table-hover align-middle mb-0`, hidden caption, `table-light` or `table-secondary` head, icon `btn-group` actions with `aria-label` + `title`.
- Forms: `form-label`, `form-control`/`form-select`, `row g-2`/`g-3`, labels associated with `for`, required fields explicit.
- Modals: `modal fade` + `modal-dialog` variant, `modal-content text-start`, header with context line, footer cancel/confirm, CSRF on every form.
- Feedback: session toasts (`include/toasts.php`), inline alerts with roles, `aria-live` counters, `aria-busy` async regions.

## Divergences to normalize when touching a page

These are real inconsistencies found between the two references. Prefer the canonical column when redesigning; do not "fix" a page just to fix them unless you are already editing that area.

| Topic | Dashboard | Maintenances | Canonical |
| --- | --- | --- | --- |
| Page heading | bare `<h2>`, no section wrapper | `<h1 class="h2">` | `<h1 class="h3 mb-1">` + subtitle inside a flex header section |
| KPI accent | `border-4` | `border-3` | `border-4` |
| KPI icon circle | `p-3` | `p-2` (no `fs-4`) | `p-2` + `fs-4` |
| Muted copy | `text-muted` | `text-body-secondary` | `text-body-secondary` |
| Filter title | `h3.h6` | inline `form-label small` bar | `h2.h6` in a filter card, or the inline bar for table-level filters |
| DataTables language | Spanish labels configured | default English labels | Spanish language block |
| Create form | modal | offcanvas | offcanvas for long forms, modal for ≤8 fields |
| Edit form | modal (not present) | side panel + modal | one path only; modal preferred for consistency |
| Table head | `table-secondary` | default | `table-light` for content tables, `table-secondary` acceptable for dense inventory |
| Row actions | square icon buttons (`property-action-btn`) | `btn-group-sm` outline icons | icon `btn-group`, square only when the table is dense |
| Table wrapper overflow | default | default | add an overflow-y-hidden helper only for DataTables |

## Limits

- Do not move dashboard-only logic (legacy PostgreSQL sync, inventory import, adscription resolution) into other pages.
- Do not copy the maintenance lifecycle into unrelated modules.
- Do not add DataTables to a page that already renders server-side pagination.
- Do not change permissions while restyling; the reference pages gate actions with `auth_has_role`.
