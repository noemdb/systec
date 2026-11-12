---
name: bootstrap-5
description: Design, implement, audit, and optimize premium-quality responsive web interfaces using Bootstrap 5.3.x. Use for dashboards, enterprise applications, administrative systems, data-heavy interfaces, forms, reports, and polished web experiences built with semantic HTML, Bootstrap utilities/components, and restrained custom CSS.
license: MIT
metadata:
  author: OpenAI
  version: "2.0.0"
  bootstrap_version: "5.3.x"
---

# Bootstrap 5.3 Premium Interface Engineering

## Mission

Act as a senior UI/UX designer, visual art director, and frontend performance engineer. Build interfaces that feel intentional, coherent, premium, fast, accessible, and production-ready.

Bootstrap is the implementation foundation—not the visual identity. Do not produce generic Bootstrap demos. Use its grid, utilities, components, CSS variables, Sass system, and JavaScript plugins as a disciplined design system.

Priorities, in order:

1. Correctness, usability, accessibility, and content clarity.
2. Information architecture and visual hierarchy.
3. Responsive behavior and interaction quality.
4. Performance, maintainability, and low implementation complexity.
5. A distinctive, refined visual language appropriate to the product and audience.

## Scope and constraints

- Target Bootstrap **5.3.x**. Verify the exact installed patch version from the project before relying on version-specific behavior.
- Use Bootstrap-native classes and components first.
- Use semantic HTML5 and accessible native controls.
- Use custom CSS only when Bootstrap cannot express the required design or interaction cleanly.
- Do not introduce Tailwind, jQuery, another CSS framework, or a component library unless the project explicitly requires it.
- Do not assume a JavaScript framework. Detect the actual stack and follow its conventions.
- Preserve the project's existing architecture, naming, build pipeline, and dependencies unless a change is requested.
- Do not silently upgrade Bootstrap or rewrite application architecture.

## Operating workflow

### 1. Inspect before implementing

When working in an existing repository, inspect:

- package manager and lockfile;
- Bootstrap version and whether CSS/JS are imported via CDN, npm, Sass, or a bundler;
- framework and rendering model;
- existing design tokens, global styles, icons, fonts, and component conventions;
- current page structure, reusable components, and responsive behavior;
- current bundle/build configuration and any existing accessibility/performance constraints.

Do not replace working project conventions merely to match an example in this skill.

If the user asks for analysis only, report findings and recommendations without editing code. If a key design or technical requirement is ambiguous, ask only the most consequential question.

### 2. Establish the visual direction

Before writing markup, define a compact visual system:

- product personality and audience;
- primary action and content priority;
- color roles: brand, surface, text, border, feedback;
- typography scale and text hierarchy;
- spacing rhythm;
- border-radius and elevation strategy;
- density level: comfortable, balanced, or compact;
- responsive composition;
- interaction and feedback states.

Choose a direction that suits the context: editorial, enterprise, analytical, institutional, commerce, editorial, or utility-first. Do not force every project into the same dashboard aesthetic.

### 3. Structure the information

- Organize content by user tasks, not by the list of available components.
- Give each screen one clear primary purpose.
- Put the most important information and action first.
- Group related controls and data; separate unrelated sections.
- Use progressive disclosure for secondary or advanced content.
- Avoid repeated headings, redundant labels, decorative metadata, and unnecessary cards.
- Prefer a strong page composition over a pile of independent boxes.

### 4. Implement in layers

1. Semantic document structure.
2. Bootstrap container/grid and responsive layout.
3. Typography and spacing hierarchy.
4. Component composition and states.
5. Small scoped custom CSS layer.
6. JavaScript behavior only where needed.
7. Accessibility, responsive, and performance verification.

### 5. Validate before delivery

Check at minimum:

- narrow mobile (~360px), mobile/tablet (~768px), desktop (~1280px), and wide desktop (~1440px);
- horizontal overflow, clipped content, awkward wrapping, and inconsistent alignment;
- keyboard navigation, visible focus, labels, accessible names, and error announcements;
- loading, empty, success, warning, error, disabled, and hover/focus states where relevant;
- console errors, duplicate Bootstrap imports, missing Popper dependencies, and unused assets;
- production build and asset output when repository access permits.

Do not claim that tests or builds passed unless they were actually run.

## Visual design system

### Composition and hierarchy

Use a clear hierarchy with three levels:

- **Primary:** page title, key metric, main action, or focal content.
- **Secondary:** section titles, supporting metrics, filters, and contextual actions.
- **Tertiary:** metadata, timestamps, helper text, captions, and low-priority controls.

Use contrast, size, weight, position, and whitespace deliberately. Do not make every element visually loud.

Prefer:
- consistent alignment edges;
- predictable content widths;
- balanced column proportions;
- clear grouping and separation;
- intentional negative space;
- restrained accent color;
- consistent component heights and control alignment.

Avoid:
- excessive cards inside cards;
- excessive borders and shadows;
- gradients on every surface;
- multiple competing accent colors;
- giant headings that displace useful content;
- arbitrary border radii;
- excessive uppercase labels and letter spacing;
- icon-only controls without accessible names;
- badges used as decoration;
- dense content with no grouping or scan path.

### Design tokens

Create a small, purposeful token layer using CSS custom properties. Prefer Bootstrap's `--bs-*` variables and component variables where available. Scope project-specific tokens under a namespace to avoid collisions.

Example foundation:

```css
:root {
  --app-brand: #3457d5;
  --app-brand-strong: #2946b5;
  --app-canvas: #f6f8fb;
  --app-surface: #fff;
  --app-surface-muted: #f1f4f8;
  --app-text: #182230;
  --app-text-muted: #667085;
  --app-border: #e4e9f0;
  --app-radius-sm: .5rem;
  --app-radius-md: .75rem;
  --app-radius-lg: 1rem;
  --app-shadow-sm: 0 1px 2px rgba(16, 24, 40, .05);
  --app-shadow-md: 0 8px 24px rgba(16, 24, 40, .08);
  --app-content-width: 80rem;
}
```

These are illustrative starting values, not mandatory brand colors. Tune them to the product. Avoid redefining every Bootstrap variable without a reason.

### Color

- Assign semantic roles, not arbitrary colors.
- Use one primary brand color, neutral surfaces, and restrained status colors.
- Use `text-body`, `text-body-secondary`, `bg-body`, `bg-body-tertiary`, and Bootstrap 5.3 subtle/emphasis utilities where appropriate.
- Prefer `.text-bg-*` when a theme background needs a matching foreground.
- Use `*-subtle`, `*-emphasis`, and `*-border-subtle` for nuanced status surfaces.
- Verify text contrast; do not communicate status by color alone.
- Avoid deprecated `.text-muted`; use `.text-body-secondary`.
- Do not use warning yellow as a default decorative highlight or low-contrast text background.

### Typography

- Use a coherent type scale, generally 4–6 useful sizes per screen.
- Keep body copy readable, line lengths controlled, and line-height comfortable.
- Use `h1`–`h6` semantically; use `.h*` classes only when visual styling must differ from semantic heading level.
- Reserve `.display-*` for genuinely prominent hero/editorial moments.
- Use `.fs-*`, `.fw-*`, `.lh-*`, `.text-*`, `.lead`, and responsive alignment utilities purposefully.
- Prefer concise labels and descriptive section headings.
- Use tabular numerals for financial, analytical, and comparative values when available through project CSS (`font-variant-numeric: tabular-nums`).
- Do not reduce all secondary text to tiny gray text.

### Spacing and density

- Use Bootstrap's spacing scale consistently.
- Use `g-*`, `gx-*`, and `gy-*` for grid gutters; use `gap-*` for flex/grid layouts.
- Default to a balanced rhythm; reduce density only when the screen is data-heavy or explicitly compact.
- Use responsive spacing (`p-3 p-lg-4`, `mb-3 mb-lg-4`) rather than fixed spacing that breaks on mobile.
- Avoid large empty paddings that push key information below the fold.
- Do not solve poor hierarchy by adding more margin.

### Surfaces, borders, and elevation

- Use surface contrast and spacing before shadows.
- Use borders for grouping and structure; shadows for meaningful elevation or overlays.
- Keep shadow levels limited and consistent.
- Use radius tokens consistently; avoid mixing many unrelated corner shapes.
- Use `card` only when content forms a meaningful independent unit. A section does not automatically need a card.
- Avoid nesting cards unless the nested unit has a distinct interaction or hierarchy.

### Icons and imagery

- Use one icon family consistently (e.g., Bootstrap Icons if installed).
- Do not assume Bootstrap CSS includes Bootstrap Icons; it is a separate package.
- Pair icons with text when meaning is not universally obvious.
- Use SVG icons with explicit accessible handling; decorative icons should be hidden from assistive technology.
- Use `img-fluid`, appropriate aspect ratios, `object-fit-*`, and meaningful `alt` text.
- Avoid remote images as decorative filler; optimize image dimensions and formats.

## Premium component standards

Use component references in `COMPONENTS.md`. Every component must have a reason to exist and a defined state model.

### Buttons and actions

- Establish one dominant primary action per region.
- Use secondary, outline, link, and danger variants according to action priority and consequence.
- Keep button labels action-oriented and specific.
- Use consistent size and alignment within a toolbar or form.
- Provide disabled and loading states; prevent duplicate submission when applicable.
- Use `<button>` for actions and `<a>` for navigation.

### Cards and metric summaries

- Use cards for discrete, independently scannable information.
- For KPI cards: concise label, prominent value, contextual comparison/period, and optional trend.
- Avoid fake trend arrows or ungrounded performance claims.
- Use consistent card padding, heading position, value alignment, and height.
- Keep decorative icons secondary to the metric.

### Tables and data-dense screens

- Use semantic table markup, `scope` for headers, and `.table-responsive` when horizontal scrolling is needed.
- Use `.table-hover`, `.table-striped`, `.table-sm`, and contextual variants only when they improve scanning.
- Align numeric values consistently; use tabular numerals.
- Keep row actions discoverable and avoid excessive action icons.
- Provide a visible empty state, loading state, and pagination/filter context where applicable.
- Do not force a complex data table into cards on desktop. On small screens, decide whether horizontal scrolling, prioritized columns, or a purpose-built compact list is best.
- Remember responsive table wrappers can clip dropdowns and other overlays because of overflow; render menus outside the clipping context or use another layout.

### Forms

- Use visible labels; placeholders are examples, not labels.
- Group related fields with `fieldset` and `legend` when appropriate.
- Use `.form-text` for concise help and `.invalid-feedback` / `.valid-feedback` for validation.
- Connect errors and help text using `aria-describedby`.
- Mark required fields clearly and consistently.
- Use correct input types, autocomplete, input modes, and native validation where suitable.
- Avoid floating labels for long labels, complex helper content, or interfaces where label visibility is important.
- Do not rely on color alone for validation.
- Preserve entered values after validation failures.

### Navigation and overlays

- Keep navigation structure shallow and labels clear.
- Use navbar collapse for responsive navigation; test keyboard and focus behavior.
- Use tabs only for peer content sections, not as a substitute for navigation or a long workflow.
- Use modal dialogs for focused tasks; use offcanvas for supplementary navigation or contextual panels.
- Keep modal content concise; use scrollable dialogs for long content.
- Initialize tooltips and popovers intentionally; they are opt-in and require Popper.
- Avoid hover-only interactions.

### Feedback and system states

Define clear patterns for:
- loading and skeleton states;
- no results and first-use empty states;
- success confirmation;
- validation errors;
- destructive confirmation;
- warning and system outage;
- permission denied;
- disabled or unavailable actions.

Use alerts for inline page-level messages, toasts for brief transient feedback, and persistent status text for information that must not disappear before users can act.

## Responsive engineering

- Start with the narrowest viewport and progressively enhance.
- Use Bootstrap breakpoints: `sm` 576px, `md` 768px, `lg` 992px, `xl` 1200px, `xxl` 1400px.
- Prefer content-driven breakpoints; do not add breakpoint variants without a real layout change.
- Use `container`, `container-fluid`, or `container-{breakpoint}` according to content width.
- Use `.row`, `.col-*`, `.row-cols-*`, and gutters intentionally.
- Avoid fixed widths and heights for content containers.
- Use `min-vh-100` for full-height page shells when appropriate; avoid `vh-100` on content that can grow or mobile viewports with dynamic browser chrome.
- Prevent long text, URLs, and numbers from breaking the layout.
- Ensure touch targets are comfortably usable and controls do not become cramped.
- Verify sticky/fixed elements do not cover content or keyboard focus.

## Performance and optimization

### CSS strategy

- For a CDN/prototype or simple static page, use the official Bootstrap 5.3 compiled CSS with a pinned patch version and integrity metadata where applicable.
- For a production app with a bundler, prefer npm + the project's existing build pipeline.
- When using Sass, import only the required Bootstrap pieces, while preserving Sass dependency order and dependencies.
- Import configuration foundations before components: functions, variables, variables-dark, maps, mixins, utilities; then only required component Sass; then helpers and `utilities/api` as needed.
- Do not import both the complete `bootstrap.scss` and individual components.
- Do not manually duplicate Bootstrap styles in custom CSS.
- Avoid generating utility classes or variants that the application never uses.
- If using a CSS purge/content scanner, configure it against all templates and runtime-generated class patterns. Do not purge classes built dynamically unless the scanner is explicitly safelisted.
- Prefer CSS variables for runtime theming; use Sass when compile-time customization or selective compilation is needed.

### JavaScript strategy

- Do not include both `bootstrap.js` and `bootstrap.bundle.js`.
- The bundle includes Popper; the non-bundle build does not.
- Include only the plugins actually used when bundling modular imports.
- Dropdowns, tooltips, and popovers depend on Popper.
- Prefer Bootstrap data attributes for simple declarative interactions.
- Initialize tooltips/popovers explicitly and only for relevant elements; avoid repeated initialization on every render.
- Dispose plugin instances when dynamically removing DOM nodes where appropriate.
- Avoid loading Bootstrap JavaScript if the page uses no interactive Bootstrap plugin.
- Avoid attaching multiple plugins to the same trigger element.
- Use one Bootstrap version across CSS and JS.
- Do not add jQuery; Bootstrap 5 does not require it.

### Framework integration

Bootstrap CSS is generally usable with component frameworks, but Bootstrap's imperative JavaScript plugins can conflict with frameworks that own the DOM (React, Vue, Angular). Do not let Bootstrap and the framework both control the same element.

- In React/Next.js, prefer React-owned state and accessible components for interactive behavior, or use a compatible wrapper. Use Bootstrap CSS/utilities without automatically initializing Bootstrap's DOM plugins.
- In Livewire/Alpine, account for DOM morphing and lifecycle; initialize/dispose plugins only when necessary and avoid duplicate listeners.
- In server-rendered HTML, data attributes are often the simplest option.
- Follow the project's existing architecture; do not introduce a framework-specific wrapper without a concrete need.

### Rendering and asset delivery

- Keep images responsive and correctly sized; use modern formats when supported by the asset pipeline.
- Lazy-load below-the-fold images; do not lazy-load the primary above-the-fold/LCP image.
- Set image width and height or aspect ratio to reduce layout shift.
- Avoid unnecessary web fonts and large icon libraries.
- Defer non-critical scripts; use `defer` for classic scripts when appropriate.
- Do not block initial rendering with non-essential JavaScript.
- Avoid expensive visual effects, large blurred shadows, and excessive animated surfaces.
- Optimize for Core Web Vitals: LCP, INP, and CLS; measure rather than guess.

## Bootstrap 5.3-specific guidance

- Use `data-bs-theme="light"` / `"dark"` for scoped or global color modes. Bootstrap does not provide a complete theme toggle UI; implement preference persistence and switching in the application.
- Prefer Bootstrap 5.3 CSS variables for runtime theme changes.
- Use `bg-*-subtle`, `text-*-emphasis`, and `border-*-subtle` for accessible semantic surfaces where appropriate.
- Use `.text-bg-*` for paired foreground/background contextual colors.
- Use `object-fit-*` utilities available in Bootstrap 5.3.
- Avoid deprecated `.text-muted`; use `.text-body-secondary`.
- Respect `prefers-reduced-motion`; do not add essential information that depends on animation.
- Use the Utility API only when Sass customization is justified.

## Accessibility and interaction quality

Target WCAG 2.2 AA as the design baseline.

- Use landmarks (`header`, `nav`, `main`, `aside`, `footer`) and a logical heading hierarchy.
- Associate every form control with a label.
- Ensure keyboard operation and visible focus indicators.
- Provide accessible names for icon-only controls.
- Use `aria-expanded`, `aria-controls`, `aria-current`, and `aria-describedby` where semantically required.
- Use `aria-live` for asynchronous feedback when users need to be notified.
- Maintain adequate contrast and do not convey information by color alone.
- Respect reduced-motion preferences.
- Ensure modal focus is managed and returns to the invoking control.
- Use `alt=""` for decorative images and descriptive alt text for informative images.
- Prefer native HTML semantics over unnecessary ARIA.
- Do not use `role="button"` on a native `<button>`. For links styled as buttons, include a real `href` and appropriate navigation semantics.

## Custom CSS policy

Use custom CSS when it creates a meaningful improvement in brand identity, composition, or component behavior that Bootstrap cannot express cleanly.

- Keep styles scoped and token-driven.
- Prefer component classes such as `.app-shell`, `.metric-card`, `.data-panel` over long chains of element selectors.
- Avoid broad overrides of `.btn`, `.card`, `.form-control`, or `.table` that unintentionally affect the entire application.
- Avoid `!important` except when overriding an unavoidable third-party specificity issue.
- Do not use inline styles for values that belong in reusable tokens or component classes.
- Keep CSS transitions short and subtle; support reduced motion.
- Do not build a parallel utility framework on top of Bootstrap.

## Code quality

- Use descriptive class names for custom components.
- Keep markup readable; do not compress HTML into one line.
- Avoid meaningless wrapper elements.
- Avoid placeholder `href="#"` in production.
- Use unique IDs for labels, modal targets, and collapse controls.
- Use `type="button"` for non-submit buttons inside forms.
- Keep content and layout separate from behavior.
- Avoid hard-coded fake data in production UI.
- Never invent values, trends, statuses, or labels that should come from real application data.

## Delivery expectations

When asked to create or modify an interface:

1. Provide the complete requested artifact, not just design commentary.
2. Keep implementation aligned with the existing stack.
3. Include required imports and dependencies only when needed.
4. Explain non-obvious technical decisions briefly.
5. Mention unverified assumptions and tests not run.
6. If the request is a visual redesign, preserve the underlying data and functionality unless explicitly asked to change them.

## Reference files

- `utilities.md`: Bootstrap 5.3 utility quick reference and corrections.
- `COMPONENTS.md`: component patterns and professional composition recipes.
- Official documentation links are listed in `README.md`.
