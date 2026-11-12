---
name: better-page
description: "Replicate the compact, responsive PHP and Bootstrap visual patterns of SysTec's dashboard on other pages. Use when creating, redesigning, or making a SysTec page consistent with the dashboard."
---

# Better Page

Apply the visual language and responsive patterns established by SysTec's dashboard to another page in this project. Reuse shared layout and existing Bootstrap components; adapt the dashboard's hierarchy and density without copying inventory-specific content or changing page behavior.

## Workflow

1. Inspect the target page, its PHP data preparation, existing layout include, styles, scripts, and any forms or tables it uses. Identify its purpose and preserve its authorization, validation, CSRF, routing, and business logic.
2. Compare the page with `dashboard.php`, `dashboard/table/index.php`, `include/layout.php`, `include/header.php`, and `responsive.css`. Read the relevant sections rather than replacing working page logic.
3. Keep the shared shell: `include/layout.php` provides the header, main region, footer, and shared head/script assets. Follow the established output-buffer pattern (`ob_start()`, `$main = ob_get_clean()`, then include the layout) where already used.
4. Restructure the target content into a compact, clear hierarchy. Use page title and concise context first, then relevant status/summary cards, filters or primary actions, and the main content. Include only components useful to that page.
5. Use Bootstrap 5 utilities and the existing compact overrides in `responsive.css`. Prefer small, consistent gaps; concise labels; compact cards and forms; and restrained borders, subtle backgrounds, and shadows.
6. Make every layout work at narrow widths: stack content before placing panels side by side, use `.table-responsive` for wide tables, let controls wrap or fill available width, and keep modals scrollable. Do not introduce horizontal overflow.
7. Preserve accessibility: semantic headings and regions, associated form labels, meaningful button/link text or accessible names, keyboard-operable controls, visible focus, and appropriate table headers.
8. Validate modified PHP with `php -l`, run the smallest relevant test suite, and inspect `git diff --check`. Report any visual behavior that could not be verified in a browser.

## Design Rules

- **Compact, not cramped:** reduce excess padding, margin, and gaps while retaining readable text, usable touch targets, and clear grouping. Do not subtract a fixed amount from every font size or shrink body text indiscriminately.
- **Responsive first:** use Bootstrap breakpoints intentionally. Start with one column on small screens; expand into columns only when the content has room.
- **Reuse shared styling:** prefer Bootstrap classes and existing shared CSS. Add narrowly scoped styles only when a component needs behavior Bootstrap does not provide; do not edit vendored Bootstrap files.
- **Consistent actions:** use standard Bootstrap Icons that describe the action. Keep related row actions grouped; icon-only controls need accessible labels and tooltips/titles.
- **Progressive density:** summaries should be scannable; put detailed data in the main section, and use filters only when they help narrow that page's content.
- **Preserve semantics and behavior:** visual refactoring must not silently alter data, permissions, validation, state transitions, or navigation.
- **Avoid dashboard cargo-culting:** do not add inventory cards, filters, charts, DataTables, or modals unless the target page has a corresponding need.

## Reference

Read [dashboard-pattern.md](references/dashboard-pattern.md) for the concrete component patterns and responsive details observed in SysTec.
