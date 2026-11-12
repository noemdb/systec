# SysTec Dashboard Pattern

This reference records reusable visual and structural decisions from the SysTec dashboard. Treat it as a design system example, not a template to copy wholesale.

## Page Structure

- The shared `include/layout.php` owns the application shell: global header, `<main>`, and footer.
- Page files prepare data and permissions before output, render the page inside the established output buffer, assign it to `$main`, then include the shared layout.
- Use a descriptive page heading followed by a brief supporting sentence when it adds context.
- Place the main content in a Bootstrap `.container-fluid`; separate functional regions with spacing and cards where useful.
- Keep data access and permission checks outside presentation markup where the page already follows that pattern.

## Visual Language

- Bootstrap 5.3 with Bootstrap Icons; use existing project assets and classes.
- Cards use subtle borders/shadows and compact `.card-body` spacing. Summary cards use a restrained colored start border, subtle icon background, a short label, and a prominent value.
- Use semantic and consistent colors for state: success for healthy/completed, warning for pending, danger for attention, and secondary for unavailable/unknown.
- Prefer short labels, modest heading sizes, and whitespace that groups related controls without wasting viewport area.
- Shared `responsive.css` already compacts common spacing utilities, card/form/table padding, typography for large headings, and the application shell. Avoid adding duplicate global overrides.

## Dashboard Components

### Summary indicators

Use a responsive `.row` with gutters and equal-height cards. Each card contains one clear icon, one concise metric label, and one value. Add supporting text only if it clarifies the number or its state. At narrow widths, cards wrap into fewer columns.

### Filters

Group related filters in a compact, labeled card. Use a responsive row, explicit `<label for>` associations, and a clearly named reset action. Filter only the page's actual content and keep the empty/default selection obvious.

### Data table

Wrap wide tables in `.table-responsive`. Use concise headers and preserve clear column meaning. Row actions belong in a `.btn-group`; use familiar action icons, accessible names for icon-only buttons, and avoid forcing the full table to fit a phone screen.

### Modal forms

Use Bootstrap's modal structure with a descriptive title, a scrollable dialog/body, labeled controls, and clear cancel/submit actions. On long forms, constrain the modal body for vertical scrolling. Preserve CSRF protection and the original submission endpoint and validation.

## Responsive Checklist

- Header navigation collapses below the chosen Bootstrap breakpoint; the toggle has an accessible label and targets the correct collapse region.
- Content columns stack on phones and only become adjacent at an appropriate wider breakpoint.
- Cards and controls have `min-width: 0` where flex/grid overflow is possible.
- Wide tables scroll inside their own responsive wrapper instead of widening the page.
- DataTables search/pagination controls wrap cleanly; do not assume desktop-sized controls.
- Modal content scrolls vertically and remains usable on short screens.
- No fixed widths/heights prevent text wrapping or cause viewport overflow.
- Test at a narrow viewport and a desktop viewport when browser access is available.

## Adaptation Boundaries

- Keep dashboard-only inventory metrics, legacy synchronization, classification filters, and maintenance registration behavior on the dashboard unless the target page explicitly needs them.
- Do not add a dependency or initialize DataTables just to imitate the dashboard.
- Do not duplicate shared header/footer/layout markup in a page-specific template.
- If target-page requirements conflict with this reference, preserve the page's correct behavior and use the shared design language rather than forcing identical structure.
