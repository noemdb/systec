# Bootstrap 5.3 Premium Component Patterns

This reference supplements `SKILL.md` with implementation patterns and composition rules. Bootstrap provides primitives; the application supplies the visual system, content hierarchy, and behavior.

## 1. Page shell and content container

Use a stable page shell with a clear content width and consistent gutters.

```html
<div class="app-shell min-vh-100">
  <header class="border-bottom bg-body">
    <!-- global navigation -->
  </header>

  <main class="container-xxl py-4 py-lg-5">
    <div class="d-flex flex-column flex-lg-row align-items-lg-end
                justify-content-between gap-3 mb-4">
      <div>
        <p class="small text-body-secondary mb-1">WORKSPACE / OVERVIEW</p>
        <h1 class="h2 fw-semibold mb-1">Page title</h1>
        <p class="text-body-secondary mb-0">A concise explanation of this view.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary" type="button">Secondary</button>
        <button class="btn btn-primary" type="button">Primary action</button>
      </div>
    </div>

    <!-- page content -->
  </main>
</div>
```

Use a breadcrumb only when it materially helps users understand hierarchy or navigate upward.

## 2. KPI / metric card

A metric card should make the value, meaning, and comparison immediately scannable.

```html
<div class="card h-100">
  <div class="card-body p-3 p-lg-4">
    <div class="d-flex align-items-start justify-content-between gap-3">
      <div>
        <p class="small text-body-secondary mb-2">TOTAL REGISTRATIONS</p>
        <p class="display-6 fw-semibold lh-1 mb-2">1,284</p>
        <p class="small mb-0">
          <span class="text-success fw-semibold">+8.4%</span>
          <span class="text-body-secondary">vs. previous period</span>
        </p>
      </div>
      <span class="rounded-3 bg-primary-subtle text-primary p-2"
            aria-hidden="true">
        <!-- icon -->
      </span>
    </div>
  </div>
</div>
```

Production requirements:
- Populate metrics from real data.
- Define the comparison period.
- Do not imply causality from a simple percentage change.
- Ensure the number remains readable at narrow widths.

## 3. Analytics section

Use one clear title and optional contextual controls. Avoid wrapping every chart in multiple nested cards.

```html
<section class="card">
  <div class="card-header bg-transparent border-0 p-3 p-lg-4 pb-0">
    <div class="d-flex flex-column flex-sm-row justify-content-between
                align-items-sm-center gap-2">
      <div>
        <h2 class="h5 mb-1">Performance over time</h2>
        <p class="small text-body-secondary mb-0">Monthly trend · Current year</p>
      </div>
      <div class="btn-group btn-group-sm" role="group"
           aria-label="Chart time range">
        <button type="button" class="btn btn-outline-secondary active"
                aria-pressed="true">12 months</button>
        <button type="button" class="btn btn-outline-secondary"
                aria-pressed="false">30 days</button>
      </div>
    </div>
  </div>
  <div class="card-body p-3 p-lg-4">
    <!-- chart with an accessible text summary -->
  </div>
</section>
```

Do not invent chart values. Provide an accessible summary or data table for important chart information.

## 4. Data table

```html
<section class="card">
  <div class="card-header bg-transparent p-3 p-lg-4">
    <div class="d-flex flex-column flex-md-row justify-content-between
                align-items-md-center gap-3">
      <div>
        <h2 class="h5 mb-1">Records</h2>
        <p class="small text-body-secondary mb-0">A short explanation of this dataset.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <input class="form-control form-control-sm" type="search"
               aria-label="Search records" placeholder="Search records">
        <button class="btn btn-sm btn-outline-secondary" type="button">Filter</button>
      </div>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col">Name</th>
          <th scope="col">Status</th>
          <th scope="col" class="text-end">Amount</th>
          <th scope="col">Updated</th>
          <th scope="col"><span class="visually-hidden">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <th scope="row" class="fw-medium">Example record</th>
          <td><span class="badge text-bg-success">Active</span></td>
          <td class="text-end font-monospace">1,250.00</td>
          <td class="text-body-secondary">Today</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" type="button">
              View
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-transparent d-flex flex-column flex-sm-row
              justify-content-between align-items-sm-center gap-2">
    <small class="text-body-secondary">Showing 1–10 of 84 records</small>
    <!-- pagination -->
  </div>
</section>
```

Notes:
- Replace example data with actual data.
- Use `scope="col"` and `scope="row"` correctly.
- Use `aria-label` for icon-only action controls.
- Keep dropdown menus outside an overflow-clipped responsive table wrapper when necessary.
- On mobile, consider a prioritized record list if horizontal scrolling is not usable.

## 5. Form section

```html
<form class="card">
  <div class="card-header bg-transparent p-3 p-lg-4">
    <h2 class="h5 mb-1">Contact details</h2>
    <p class="small text-body-secondary mb-0">Fields marked required must be completed.</p>
  </div>
  <div class="card-body p-3 p-lg-4">
    <div class="row g-3">
      <div class="col-12 col-md-6">
        <label for="fullName" class="form-label">Full name</label>
        <input id="fullName" name="fullName" class="form-control"
               autocomplete="name" required>
        <div class="invalid-feedback">Enter a full name.</div>
      </div>
      <div class="col-12 col-md-6">
        <label for="email" class="form-label">Email address</label>
        <input id="email" name="email" type="email" class="form-control"
               autocomplete="email" aria-describedby="emailHelp" required>
        <div id="emailHelp" class="form-text">Use an address you can access.</div>
        <div class="invalid-feedback">Enter a valid email address.</div>
      </div>
    </div>
  </div>
  <div class="card-footer bg-transparent p-3 p-lg-4 d-flex
              flex-column-reverse flex-sm-row justify-content-end gap-2">
    <button type="button" class="btn btn-outline-secondary">Cancel</button>
    <button type="submit" class="btn btn-primary">Save changes</button>
  </div>
</form>
```

Implement actual validation behavior in the application. The markup alone does not validate asynchronously or persist data.

## 6. Status and feedback

Use semantic variants and text labels. Never rely only on color.

```html
<div class="alert alert-success d-flex align-items-start gap-2" role="status">
  <span class="fw-semibold">Saved.</span>
  <span>Your changes are now available.</span>
</div>

<div class="p-3 rounded-3 border border-warning-subtle bg-warning-subtle">
  <p class="fw-semibold text-warning-emphasis mb-1">Action required</p>
  <p class="small text-body-secondary mb-0">
    Explain what needs attention and how to resolve it.
  </p>
</div>
```

Use `role="alert"` for urgent messages that need immediate announcement. Use `role="status"` for non-urgent status updates.

## 7. Empty state

An empty state should explain why there is no content and provide a useful next step.

```html
<div class="text-center border rounded-4 p-4 p-lg-5">
  <div class="mx-auto mb-3 rounded-circle bg-body-tertiary
              d-flex align-items-center justify-content-center"
       style="width: 3.5rem; height: 3.5rem" aria-hidden="true">
    <!-- icon -->
  </div>
  <h2 class="h5 mb-2">No records yet</h2>
  <p class="text-body-secondary mx-auto mb-3" style="max-width: 28rem">
    A short explanation of what will appear here and how to get started.
  </p>
  <button class="btn btn-primary" type="button">Create record</button>
</div>
```

Avoid empty states that merely say “No data” without guidance.

## 8. Modal

Use a modal for a focused task, not as a generic container for large pages.

```html
<div class="modal fade" id="editModal" tabindex="-1"
     aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="editModalLabel">Edit record</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- focused form or confirmation -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary"
                data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary">Save</button>
      </div>
    </div>
  </div>
</div>
```

Provide a unique ID per modal. Ensure focus returns to the trigger and the dialog has a meaningful accessible name.

## 9. Responsive navigation

```html
<nav class="navbar navbar-expand-lg bg-body border-bottom">
  <div class="container-xxl">
    <a class="navbar-brand fw-semibold" href="/">Product</a>
    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#mainNavigation"
            aria-controls="mainNavigation" aria-expanded="false"
            aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNavigation">
      <ul class="navbar-nav me-auto my-2 my-lg-0">
        <li class="nav-item">
          <a class="nav-link active" href="/overview" aria-current="page">
            Overview
          </a>
        </li>
        <li class="nav-item"><a class="nav-link" href="/records">Records</a></li>
      </ul>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/profile">Profile</a>
      </div>
    </div>
  </div>
</nav>
```

Use real routes. Do not use `href="#"` in production navigation.

## 10. Loading and skeleton states

Use placeholders only when the expected content shape is known. Avoid excessive shimmer animation.

```html
<div class="placeholder-glow" aria-hidden="true">
  <span class="placeholder col-4 mb-2"></span>
  <span class="placeholder col-8"></span>
</div>
<span class="visually-hidden" role="status">Loading records…</span>
```

For an actual loading region, expose an appropriate status and consider `aria-busy="true"` on the region being updated.

## 11. Toast notifications

Toasts are transient. Do not use them for critical information that users must retain.

```html
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="saveToast" class="toast" role="status"
       aria-live="polite" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto">Changes saved</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast"
              aria-label="Close"></button>
    </div>
    <div class="toast-body">Your updates have been saved.</div>
  </div>
</div>
```

Initialize with the Bootstrap Toast API when needed. Avoid announcing the same update twice through both a toast and an assertive live region.

## 12. JavaScript initialization

Prefer data attributes for simple component behavior. Initialize optional plugins deliberately:

```js
const tooltipElements = document.querySelectorAll(
  '[data-bs-toggle="tooltip"]'
);

tooltipElements.forEach((element) => {
  bootstrap.Tooltip.getOrCreateInstance(element);
});
```

For bundled applications, import only what is needed and follow the framework's lifecycle. Do not repeatedly instantiate plugins after every render. Dispose instances when elements are permanently removed if required.

## 13. Premium polish checklist

- [ ] One clear page purpose and primary action.
- [ ] Consistent content width and alignment.
- [ ] Deliberate typography hierarchy.
- [ ] Restrained semantic color palette.
- [ ] Consistent spacing and radii.
- [ ] No unnecessary nested cards.
- [ ] Responsive composition, not merely scaled desktop.
- [ ] Visible keyboard focus and clear control labels.
- [ ] Empty, loading, success, and error states.
- [ ] No invented metrics or decorative fake data.
- [ ] No redundant CSS/JS imports or duplicate plugin initialization.
- [ ] Motion respects reduced-motion preferences.
