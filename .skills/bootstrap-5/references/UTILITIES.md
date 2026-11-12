# Bootstrap 5.3 Utilities Reference

A practical quick reference for Bootstrap **5.3.x**. This file focuses on utilities commonly used in production interfaces. The installed Bootstrap patch version and compiled Sass configuration remain the source of truth.

## Responsive breakpoints

| Breakpoint | Infix | Minimum width |
|---|---:|---:|
| Extra small | none | 0px |
| Small | `sm` | 576px |
| Medium | `md` | 768px |
| Large | `lg` | 992px |
| Extra large | `xl` | 1200px |
| Extra extra large | `xxl` | 1400px |

Responsive utilities generally follow `{property}-{breakpoint}-{value}`, e.g. `d-none d-md-flex`, `text-start text-lg-end`, `p-2 p-lg-4`.

## Background

```css
.bg-primary .bg-secondary .bg-success .bg-danger .bg-warning .bg-info
.bg-light .bg-dark .bg-body .bg-white .bg-transparent .bg-black
.bg-body-secondary .bg-body-tertiary
.bg-primary-subtle .bg-secondary-subtle .bg-success-subtle .bg-danger-subtle
.bg-warning-subtle .bg-info-subtle .bg-light-subtle .bg-dark-subtle
.bg-gradient
.bg-opacity-10 .bg-opacity-25 .bg-opacity-50 .bg-opacity-75 .bg-opacity-100
```

Use `.text-bg-*` when a matching foreground color is needed: `.text-bg-primary`, `.text-bg-success`, etc.

## Borders and radius

```css
.border .border-top .border-end .border-bottom .border-start
.border-0 .border-top-0 .border-end-0 .border-bottom-0 .border-start-0
.border-primary .border-secondary .border-success .border-danger
.border-warning .border-info .border-light .border-dark .border-white .border-black
.border-opacity-10 .border-opacity-25 .border-opacity-50 .border-opacity-75 .border-opacity-100
.rounded .rounded-top .rounded-end .rounded-bottom .rounded-start
.rounded-circle .rounded-pill
.rounded-0 .rounded-1 .rounded-2 .rounded-3 .rounded-4 .rounded-5
```

Bootstrap 5.3 also provides semantic subtle border colors such as `.border-primary-subtle`, `.border-success-subtle`, and corresponding theme variants.

## Text color

```css
.text-primary .text-secondary .text-success .text-danger .text-warning .text-info
.text-light .text-dark .text-body .text-body-secondary .text-body-tertiary
.text-primary-emphasis .text-secondary-emphasis .text-success-emphasis
.text-danger-emphasis .text-warning-emphasis .text-info-emphasis
.text-light-emphasis .text-dark-emphasis
.text-black .text-white .text-black-50 .text-white-50
.text-opacity-25 .text-opacity-50 .text-opacity-75 .text-opacity-100
```

**Deprecated:** `.text-muted` is deprecated in 5.3. Prefer `.text-body-secondary`.

## Display

```css
.d-none .d-inline .d-inline-block .d-block .d-grid .d-table
.d-table-cell .d-table-row .d-flex .d-inline-flex
.d-{sm|md|lg|xl|xxl}-{none|inline|inline-block|block|grid|table|table-cell|table-row|flex|inline-flex}
```

Print display utilities: `.d-print-none`, `.d-print-inline`, `.d-print-inline-block`, `.d-print-block`, `.d-print-grid`, `.d-print-table`, `.d-print-table-cell`, `.d-print-table-row`, `.d-print-flex`, `.d-print-inline-flex`.

## Flexbox

```css
/* Direction */
.flex-row .flex-column .flex-row-reverse .flex-column-reverse
.flex-{breakpoint}-row .flex-{breakpoint}-column
.flex-{breakpoint}-row-reverse .flex-{breakpoint}-column-reverse

/* Justify content */
.justify-content-start .justify-content-end .justify-content-center
.justify-content-between .justify-content-around .justify-content-evenly

/* Align items */
.align-items-start .align-items-end .align-items-center
.align-items-baseline .align-items-stretch

/* Align self */
.align-self-start .align-self-end .align-self-center
.align-self-baseline .align-self-stretch

/* Fill, grow, shrink, wrap, order */
.flex-fill .flex-grow-0 .flex-grow-1 .flex-shrink-0 .flex-shrink-1
.flex-wrap .flex-nowrap .flex-wrap-reverse
.order-first .order-0 .order-1 .order-2 .order-3 .order-4 .order-5 .order-last

/* Align content */
.align-content-start .align-content-end .align-content-center
.align-content-between .align-content-around .align-content-stretch
```

Responsive variants exist for the relevant flex utilities. Check the exact utility family before assuming every utility supports every responsive infix.

## Grid and gutters

```css
.row .col .col-auto .col-1 ... .col-12
.col-sm-* .col-md-* .col-lg-* .col-xl-* .col-xxl-*
.row-cols-1 ... .row-cols-6
.g-0 .g-1 .g-2 .g-3 .g-4 .g-5
.gx-0 ... .gx-5
.gy-0 ... .gy-5
.gap-0 .gap-1 .gap-2 .gap-3 .gap-4 .gap-5
```

`.row` gutters and CSS `gap` are not interchangeable in every layout. Use grid gutters for Bootstrap rows and `gap-*` for flex/grid containers.

## Spacing

Format: `{property}{side}-{size}` or `{property}{side}-{breakpoint}-{size}`.

- Property: `m` margin, `p` padding.
- Side: blank (all), `t`, `b`, `s` (start), `e` (end), `x`, `y`.
- Size: `0`, `1`, `2`, `3`, `4`, `5`, `auto` (auto is for margin).

| Class suffix | Default value |
|---|---:|
| `0` | 0 |
| `1` | 0.25rem |
| `2` | 0.5rem |
| `3` | 1rem |
| `4` | 1.5rem |
| `5` | 3rem |
| `auto` | auto margin |

Examples: `.p-3`, `.px-4`, `.mt-2`, `.ms-auto`, `.mb-lg-4`, `.mx-auto`.

## Sizing

```css
/* Width */
.w-25 .w-50 .w-75 .w-100 .w-auto
.mw-100
.vw-100 .min-vw-100

/* Height */
.h-25 .h-50 .h-75 .h-100 .h-auto
.mh-100
.vh-100 .min-vh-100
```

Do not assume `.mw-auto`, `.mh-auto`, `.vh-25`, `.vh-50`, `.vh-75`, or `.min-vh-25` exist in the default Bootstrap 5.3 utility set. Confirm custom Sass utilities before using non-standard variants.

## Text

```css
/* Alignment */
.text-start .text-center .text-end
.text-{sm|md|lg|xl|xxl}-{start|center|end}

/* Wrapping and breaking */
.text-wrap .text-nowrap .text-break .text-truncate

/* Transform */
.text-lowercase .text-uppercase .text-capitalize

/* Weight and style */
.fw-lighter .fw-light .fw-normal .fw-medium .fw-semibold .fw-bold .fw-bolder
.fst-normal .fst-italic

/* Line height */
.lh-1 .lh-sm .lh-base .lh-lg

/* Font family, reset, decoration, size */
.font-monospace .text-reset
.text-decoration-none .text-decoration-underline .text-decoration-line-through
.fs-1 .fs-2 .fs-3 .fs-4 .fs-5 .fs-6
```

## Position

```css
.position-static .position-relative .position-absolute .position-fixed .position-sticky
.fixed-top .fixed-bottom .sticky-top .sticky-bottom
.top-0 .top-50 .top-100
.bottom-0 .bottom-50 .bottom-100
.start-0 .start-50 .start-100
.end-0 .end-50 .end-100
.translate-middle .translate-middle-x .translate-middle-y
```

## Shadows

```css
.shadow-none .shadow-sm .shadow .shadow-lg
```

Use shadows sparingly and consistently. Shadows are not a substitute for hierarchy or surface contrast.

## Overflow, visibility, opacity

```css
.overflow-auto .overflow-hidden .overflow-visible .overflow-scroll
.overflow-x-auto .overflow-x-hidden .overflow-x-visible .overflow-x-scroll
.overflow-y-auto .overflow-y-hidden .overflow-y-visible .overflow-y-scroll

.visible .invisible

.opacity-0 .opacity-25 .opacity-50 .opacity-75 .opacity-100
```

## Interactions

```css
.user-select-all .user-select-auto .user-select-none
.pe-none .pe-auto
```

Correct class names are `.pe-none` and `.pe-auto` (pointer events), not `.pointer-event` or `.pointer-events-none`.

## Links

```css
.link-primary .link-secondary .link-success .link-danger
.link-warning .link-info .link-light .link-dark .link-body-emphasis
.link-underline
.link-underline-opacity-0 .link-underline-opacity-10 .link-underline-opacity-25
.link-underline-opacity-50 .link-underline-opacity-75 .link-underline-opacity-100
.link-offset-1 .link-offset-2 .link-offset-3
```

## Object fit (Bootstrap 5.3)

```css
.object-fit-contain .object-fit-cover .object-fit-fill
.object-fit-scale .object-fit-none
.object-fit-{sm|md|lg|xl|xxl}-{contain|cover|fill|scale|none}
```

## Vertical alignment

```css
.align-baseline .align-top .align-middle .align-bottom
.align-text-top .align-text-bottom
```

## Z-index

Default utility classes:

```css
.z-n1 .z-0 .z-1 .z-2 .z-3
```

Do not assume responsive `.z-sm-*`, `.z-md-*`, `.z-lg-*`, `.z-xl-*`, or `.z-xxl-*` classes exist in the default build. Bootstrap's component z-index scale is separate from the `.z-*` utility scale.

## Bootstrap component z-index scale

| Layer | z-index |
|---|---:|
| Dropdown | 1000 |
| Sticky | 1020 |
| Fixed | 1030 |
| Offcanvas backdrop | 1040 |
| Offcanvas | 1045 |
| Modal backdrop | 1050 |
| Modal | 1055 |
| Popover | 1070 |
| Tooltip | 1080 |
| Toast | 1090 |

Do not casually override these values. Diagnose stacking contexts before changing z-index.

## Utilities frequently omitted from quick references

```css
.visually-hidden .visually-hidden-focusable
.ratio .ratio-1x1 .ratio-4x3 .ratio-16x9 .ratio-21x9
.stretched-link
```

Also remember helpers such as `.clearfix` and responsive embedding/image utilities when applicable.

## Spacing scale in pixels

Bootstrap's default root font size is typically 16px. The rem values scale with the root font size; pixel values below are approximate.

| Suffix | rem | Approx. px |
|---:|---:|---:|
| `0` | 0 | 0 |
| `1` | .25rem | 4px |
| `2` | .5rem | 8px |
| `3` | 1rem | 16px |
| `4` | 1.5rem | 24px |
| `5` | 3rem | 48px |

## Practical design guidance

- Prefer `text-body-secondary` over deprecated `text-muted`.
- Prefer subtle semantic backgrounds for status panels rather than saturated full-surface fills.
- Use responsive spacing instead of hard-coded dimensions.
- Avoid using `vh-100` for long content or mobile app shells where dynamic browser chrome matters.
- Use `min-vh-100` for a page shell that must fill at least the viewport.
- Check whether a utility is responsive before appending a breakpoint infix.
- The Sass Utility API can add, modify, remove, and generate responsive/state/print variants; generated utilities depend on the project's Sass build configuration.
