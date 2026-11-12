# Design tokens in effect

Values below are what the app actually renders, after `vendor/bootstrap`, `responsive.css` and the shared shell. Use them instead of guessing.

## Typography

| Element | Rule | Effective size |
| --- | --- | --- |
| Root | `html { font-size: 90% }` | 14.4 px base |
| `h1` / `.h1` / `.fs-1` | `clamp(1.25rem, calc(1.375rem + 1.5vw - 1rem), 2rem)` | 18–28.8 px |
| `h2` / `.h2` / `.fs-2` | `clamp(1.25rem, calc(1.325rem + .9vw - 1rem), 1.5rem)` | 18–21.6 px |
| `h3` / `.h3` / `.fs-3` | `clamp(1.125rem, calc(1.75rem - 1rem), 1.25rem)` | 16.2–18 px |
| `.h5` (card titles) | Bootstrap default | 1.25 rem → 18 px |
| `.h6` (filter/section titles) | Bootstrap default | 1 rem → 14.4 px |
| `small`, `.small` | Bootstrap default | 0.875 em |
| Body copy | Bootstrap default, `text-body-secondary` for support | 1 rem → 14.4 px |
| Table text | `.table-striped.table-sm`, `#myTable_length select` | 0.875 rem → 12.6 px |
| Icons in KPI circles | `.fs-4` | 1.5 rem |
| Bento/card headings | `.95rem`, weight 600, `var(--bs-primary)` | ~13.7 px |

Canonical headings:

- Page title: `<h1 class="h3 mb-1">`
- Card/section title: `<h2 class="h5 mb-0">`
- Filter panel title: `<h2 class="h6 mb-3">`
- Subtitle: `<p class="text-body-secondary mb-0">`

## Spacing

`responsive.css` compresses Bootstrap spacing under `.app-main`. Effective values:

| Utility | Nominal | Effective |
| --- | --- | --- |
| `p-3` | 1rem | **.75rem** |
| `p-4` | 1.5rem | **1rem** |
| `p-5` | 3rem | **1.5rem** |
| `m-3` / `m-4` | 1rem / 1.5rem | **.75rem** |
| `mb-3` / `mt-3` | 1rem | **.5rem** |
| `mb-4` / `mt-4` | 1.5rem | **.75rem** |
| `mb-5` / `mt-5` | 3rem | **1.5rem** |
| `gap-3` | 1rem | **.5rem** |
| `gap-4` | 1.5rem | **.75rem** |
| `gap-5` | 3rem | **1rem** |
| `g-3` row | 1.5rem / 1.5rem | **1rem x / .5rem y** |
| `g-4` row | 1.5rem / 1.5rem | **1.25rem x / .75rem y** |

Component padding:

| Component | Padding |
| --- | --- |
| `.card-body` | **.75rem** |
| `.card-header` / `.card-footer` | **.5rem .75rem** |
| `.modal-header` / `.modal-footer` | **.5rem .75rem** |
| `.modal-body` | **.75rem** |
| `.form-label` | margin-bottom **.25rem** |
| `.form-control` / `.form-select` | **.375rem .625rem** |
| `.btn` (not sm/lg) | **.375rem .625rem** |
| `.table` cells | **.375rem .5rem** |
| `.app-main` | `0 .75rem .75rem`, top margin .5rem |
| `.container-fluid` inside main | inline **.5rem** |

Rhythm: `mb-3` between header and first block, `mb-4` between major sections, `row g-3` for card grids, `gap-2`/`gap-3` inside flex rows.

## Colors

Use Bootstrap semantic tokens only. No custom hex in page code (print styles are the exception).

| Meaning | Token | Background accent |
| --- | --- | --- |
| Neutral / global / total | `primary` | `bg-primary-subtle text-primary` |
| In progress / informational | `info` | `bg-info-subtle text-info` |
| Pending / warning / overdue soon | `warning` | `bg-warning-subtle text-warning` |
| Critical / overdue / destructive | `danger` | `bg-danger-subtle text-danger` |
| Completed / healthy | `success` | `bg-success-subtle text-success` |
| Unknown / idle / closed-out | `secondary` | `bg-secondary-subtle text-secondary` |

Rules:

- KPI cards: `border-start border-4 border-{semantic}`.
- Icons: `rounded-circle bg-{semantic}-subtle text-{semantic} p-2` + `fs-4`.
- Badges: `badge rounded-pill text-bg-{semantic}` via `sat_badge()`.
- Muted text: `text-body-secondary` (canonical) or `text-muted` (legacy, avoid mixing).
- Links inside cards: `small fw-semibold` with `bi bi-arrow-right`.
- Focus ring: `box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .2)`.
- App shell: `bg-secondary-subtle` header/footer, `data-bs-theme="light"`.

## Borders, radii, shadows

- Card border: Bootstrap default; content cards often `border-0` + `shadow-sm`.
- KPI accent: `border-start border-4`.
- Radius: cards `.375rem`; bento/modal cards `.75rem`; badges and chips `rounded-pill`; icon circles `rounded-circle`.
- Shadows: `shadow-sm` on cards; bento cards `0 1px 3px rgba(0,0,0,.1)`.
- Dividers: `border-top` / `border-bottom`; avoid heavy rules.

## Layout widths

- KPI grid: 5 cards → `col-6 col-md-4 col-xl`; 4–6 wide cards → `col-12 col-sm-6 col-xl`; 3 cards → `col-12 col-md-4`.
- Content + side panel: `col-12 col-xl-8` / `col-12 col-xl-4`.
- Bento detail modal: `col-12 col-lg-8` / `col-12 col-lg-4`, dialog `min(95vw, 1200px)`, `max-height: 95vh`.
- Follow-up/confirm modal: `min(95vw, 560px)`.
- Create offcanvas: `min(34rem, 100vw)`.
- DataTable search input: `max-width: min(60vw, 18rem)`.
- Row action square buttons: `2.4rem × 2.4rem` (`.property-action-btn`).
- App minimum width: 320 px.

## Breakpoints

| Name | Min width | Typical use |
| --- | --- | --- |
| xs | 0 | single column, stacked controls |
| sm | 576 px | 2-up KPI cards, side-by-side buttons |
| md | 768 px | page header row, 3-up filters, modal margins |
| lg | 992 px | detail columns |
| xl | 1200 px | full nav, 5-up KPI cards, content + side panel |

Navbar collapses below `xl` (`navbar-expand-xl`, forced by `responsive.css` under 1200 px).

## Motion

- Micro-interactions: `transition: transform .15s ease, box-shadow .15s ease`; hover `translateY(-1px)`.
- Always wrap motion in `@media (prefers-reduced-motion: reduce)` when adding new transitions.
- No decorative animation, parallax or auto-carousels.

## Z-index / layering

- Toasts: `app-toast-container` at `z-index: 1090` (above modals) — do not raise page elements above it.
- Bootstrap defaults for modals (1055), offcanvas (1045), dropdowns (1000), tooltips (1080).
