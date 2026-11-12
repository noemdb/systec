# Audit checklist

Use before, during and after a `ui-better` refactor. Mark items only when verified in code or in a rendered page.

## 1. Before touching code

- [ ] Read the target page end to end, including includes it pulls (table, forms, modals, actions).
- [ ] List its features: data shown, KPIs, filters, actions, forms, modals, feedback, empty states.
- [ ] List behavior that must not change: queries, POST/GET targets, permissions (`auth_has_role`), CSRF, validation, redirects, session keys.
- [ ] Identify the page's route guards (`require_login()`, `require_personal_scope()`, role checks).
- [ ] Check whether the page already has DataTables, client filters or server pagination; do not add a second mechanism.
- [ ] Note the current git state; work on a branch or with a clean diff you can review.
- [ ] Search for existing classes before inventing new ones: `grep -rn 'class-name' --include=*.php --include=*.css .`.

## 2. Structure and shell

- [ ] Page still uses `ob_start()` → `$main = ob_get_clean()` → `include __DIR__ . '/include/layout.php'`.
- [ ] No duplicated `<head>`, header, footer or `<html>` markup.
- [ ] Content wrapped in `.container-fluid`; no page-level centered text (use `text-start` where the shell centers).
- [ ] One `<h1 class="h3 mb-1">` per page; section titles are `h2`.
- [ ] Sections that start a region have `aria-label` or `aria-labelledby`.
- [ ] Vertical rhythm: header `mb-3`, major blocks `mb-4`, `row g-3` for card grids.
- [ ] Primary action is a single `btn-primary`, top-right on desktop, full/stacked on mobile.

## 3. Components

- [ ] KPI cards use `border-start border-4` + semantic color, `h-100`, icon circle, `small` label, `h3` value.
- [ ] Interactive KPIs are `<button>` with `aria-pressed` and `aria-controls`; static KPIs are `<article>`.
- [ ] Filters live in a card (or inline bar), have labels, explicit default option and a reset action.
- [ ] Empty state distinguishes "no data" from "no matches"; the latter offers reset.
- [ ] Tables are inside `.table-responsive`, have a hidden `<caption>`, scope on headers, concise column names.
- [ ] Row actions are grouped, ordered consistently, icon-only ones have `aria-label` + `title`.
- [ ] Destructive actions use `btn-danger` (outline in tables) and a confirmation modal.
- [ ] Modals have `aria-labelledby`, a labeled close button, cancel/confirm footer, CSRF, original endpoint.
- [ ] Long modals use `modal-dialog-scrollable`; long create forms may use an end offcanvas.
- [ ] Feedback uses session toasts or inline alerts with `role="status"`/`role="alert"`.
- [ ] Dynamic counters/loading regions use `aria-live` and `aria-busy`.

## 4. Styling

- [ ] No new hex colors; only Bootstrap semantic classes/CSS variables.
- [ ] No new fonts, icon sets, animation libraries or dependencies.
- [ ] Shared changes are in `responsive.css`; page-specific tweaks in `<style nonce="<?= e(systec_csp_nonce()) ?>">`.
- [ ] No inline `style="..."` for layout (CSS custom properties for widths are acceptable when scoped and documented).
- [ ] No `!important` added in page code unless overriding an inline/utility conflict.
- [ ] Consistent badges (`sat_badge()` / `text-bg-*`), no mixed `border-3`/`border-4` or `h1.h2`/`h1.h3` within the page.
- [ ] Removed components left no dead CSS or JS (search selectors before deleting).

## 5. Responsive

- [ ] At 320 px: no horizontal page scroll, controls wrap, buttons remain tappable (≥ 2.4 rem).
- [ ] At 576 px: KPI grid 2-up, header still stacked.
- [ ] At 768 px: header switches to a row; filter columns 2–3-up; modal margins apply.
- [ ] At 1200 px+: full nav, KPI row complete, content + side panel side by side.
- [ ] Tables scroll inside their wrapper, not the page.
- [ ] Long words/URLs break (`text-break`, `overflow-wrap`) and flex/grid children have `min-width: 0`.
- [ ] Modals fit short screens; body scrolls; footer remains reachable.
- [ ] No fixed `width`/`height` in px on containers, table cells or modal dialogs.

## 6. Accessibility

- [ ] Heading order is logical (no skipped levels for styling).
- [ ] Every form control has a `<label for>`; required fields marked; help text linked with `aria-describedby` when needed.
- [ ] Icon-only buttons and links have accessible names; decorative icons are `aria-hidden="true"`.
- [ ] Toggle controls expose state (`aria-pressed`, `aria-expanded`, checkboxes).
- [ ] Keyboard: all actions reachable, focus visible, no focus trapped in non-modal content.
- [ ] Color is not the only carrier of meaning (add text/icon to state badges).
- [ ] Toasts and errors announce (`role`, `aria-live`).
- [ ] Motion respects `prefers-reduced-motion`.

## 7. Behavior preserved

- [ ] Authorization checks unchanged and still server-side.
- [ ] CSRF field present in every state-changing form.
- [ ] Endpoints, HTTP methods and hidden inputs unchanged.
- [ ] Validation rules and error messages preserved (translation/wording may improve).
- [ ] Redirects and flash keys unchanged.
- [ ] Filters behave identically (same fields, same semantics, same reset result).
- [ ] Counters/KPIs show the same numbers as before the refactor.
- [ ] No data query moved into the template or executed twice unintentionally.

## 8. Verification commands

```bash
php -l path/to/page.php
vendor/bin/phpunit
git diff --check
git diff --stat
bash ~/.agents/skills/ui-better/scripts/audit-page.sh path/to/page.php
bash ~/.agents/skills/ui-better/scripts/audit-page.sh --fragment include/partial.php
```

The audit script is heuristic: it reads one file and lists (but does not follow) its includes. Run it in `--fragment` mode on partials that contain tables, forms or modals, since page-level checks (shell, `.container-fluid`, `<h1>`) do not apply to them. A `FAIL` blocks; a `WARN` needs a conscious decision.

Optional render smoke test (adapt user/role):

```php
<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/page.php';
require_once '/path/to/conn.php';
require_once '/path/to/include/auth.php';
$_SESSION['user'] = array('id' => 1, 'username' => 'admin', 'role' => 'admin');
ob_start();
include '/path/to/page.php';
$html = ob_get_clean();
file_put_contents('/tmp/page.html', $html);
echo strlen($html), PHP_EOL;
```

Then grep the output for the expected building blocks (heading, KPI cards, table wrapper, modal ids, CSRF fields) and open it in a browser when available.

## 9. Report

Close with:

- What changed visually and structurally, grouped by area.
- What behavior was preserved.
- What could not be verified (browser-only behavior, data-dependent states).
- Any follow-up inconsistency left intentionally (reference vs canonical), with file/line.
