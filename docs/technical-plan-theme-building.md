# Technical Plan — Issue: Theme Building

## 1. Overview

Enhance the admin **Theme** section so the site owner can pick a pre-designed "look and feel":

- At least **3 header designs** to choose from.
- At least **3 footer designs** to choose from.
- Upgrade the **Navigation** tab to a **WordPress-style drag-and-drop menu builder** where items are dragged to reorder and to nest into drop-down sub-menus.

No new runtime dependency on external CDNs is allowed; the project self-hosts libraries (TinyMCE is already vendored), so the same convention applies here.

## 2. Current State Analysis

| Area | Current implementation |
|------|------------------------|
| Header design | `header_style` setting with only 2 values (`center` / `left`). Single hardcoded header markup in `views/header.php` (topbar + logo header + navbar + breaking ticker). |
| Footer design | No footer design setting. Single hardcoded markup in `views/footer.php` (4-column grid). |
| Navigation | `menus` table (`label`, `url`, `parent_id`, `sort_order`, `status`). Admin UI is **form-row based** (`admin/settings.php` `tab=menu`): parallel arrays `mid[]/mlabel[]/murl[]/mparent[]/morder[]`. Hierarchy set via a `<select>` parent field, ordering via numeric order fields — **no drag and drop**. |
| Theme CSS | `views/theme.php` outputs CSS custom properties (`--primary`, `--secondary`, `--accent`, `--nav-bg`) from `theme_*` settings. |
| Rendering | `views/header.php`, `views/footer.php` render the site chrome; `app/helpers.php` provides `menu_tree()` (nested tree) and `render_nav_menu()` (recursive dropdown output). |
| Data store | `settings` table is key/value; `Settings::update()` upserts. No schema migration needed for new settings. |

Key files:

- `admin/settings.php` — tabs + POST handlers for general/theme/menu/seo/social.
- `app/Settings.php` — key/value settings store.
- `app/defaults.php` — default settings + seed menus (used by `install.php`).
- `views/header.php`, `views/footer.php`, `views/theme.php`.
- `app/helpers.php` (`menu_tree`, `render_nav_menu`, `setting`, `e`).
- `assets/css/style.css`, `assets/css/admin.css`, `assets/js/main.js`.

## 3. Requirements (Acceptance Criteria)

1. Admin > Settings > **Theme** shows a header-design picker with at least 3 designs, each with a visual thumbnail and name; selecting one changes the public site header.
2. Same for the **footer** — at least 3 footer designs.
3. Admin > Settings > **Navigation** is a WordPress-style two-panel builder:
   - **Left panel**: "Add Menu Items" (Custom Link, Pages, Categories).
   - **Right panel**: "Menu Structure" — items draggable to reorder, draggable horizontally/into a list to nest into sub-menus, removable, with inline edit of label/URL.
   - Saving persists order and nesting exactly as shown.
4. All changes are saved with CSRF protection, server-side validation, and output escaping (project security standards).
5. Existing installs keep working: old `header_style` values map to the new preset system; menus without children still render correctly.

## 4. Design Approach

### 4.1 Header Designs (3 presets)

New setting: `header_style` values become preset keys.

| Key | Name | Layout |
|-----|------|--------|
| `classic` | Classic | Current default: top bar + centered logo header + full-width navbar + breaking ticker. |
| `modern` | Modern | No top bar. Logo left, inline nav links centered/right in the header row, search right. One slim sticky row. Breaking ticker below (optional). |
| `compact` | Compact | Thin top bar + tight header (logo left, actions right) + compact navbar with inline search. Reduced paddings/font sizes. |

**Rendering strategy**

- Extract each header preset into its own partial:
  - `views/partials/header_classic.php`
  - `views/partials/header_modern.php`
  - `views/partials/header_compact.php`
- `views/header.php` keeps all `<head>`/SEO/analytics logic and the opening `<body>` tag (body class becomes `header-style-{preset}`), then includes the selected partial for the header chrome.
- Normalize the setting:
  ```php
  $headerStyle = setting('header_style', 'classic');
  $headerStyle = in_array($headerStyle, ['classic', 'modern', 'compact'], true) ? $headerStyle : 'classic';
  ```
  Backward compatibility: the old value `center`/`left` is no longer valid → falls back to `classic` (which is the current centered default). `views/theme.php` already special-cases `header_style === 'left'`; that rule is removed or folded into `classic`.
- All presets reuse `menu_tree()` and `render_nav_menu()` so navigation behavior stays identical; only the wrapper chrome differs.

**CSS strategy**

- Scope each preset under its body class in `assets/css/style.css`, e.g. `.header-style-modern .site-header { ... }`, `.header-style-compact .site-header { ... }`.
- `classic` keeps existing rules (no regression).
- Any preset-specific variable overrides (e.g. `--nav-bg` tint) can be emitted from `views/theme.php` under the matching body-class selector.

### 4.2 Footer Designs (3 presets)

New setting: `footer_style`.

| Key | Name | Layout |
|-----|------|--------|
| `classic` | Classic | Current 4-column grid (About / Quick Links / Categories / Pages). |
| `minimal` | Minimal | Single centered column: brand, tagline, social icons, copyright line. |
| `rich` | Rich | Newsletter signup + 4 columns + social row + bottom copyright bar. |

**Rendering strategy**

- Extract partials: `views/partials/footer_classic.php`, `views/partials/footer_minimal.php`, `views/partials/footer_rich.php`.
- `views/footer.php` keeps the closing `</main>` tag and the site scripts, adds `<footer class="site-footer footer-style-{preset}">`, includes the selected partial.
- Normalize the setting the same way as header (whitelist + fallback to `classic`).

**CSS strategy**

- Scoped rules per `.footer-style-{preset}`; `classic` unchanged.
- `newsletter` styles already exist partially in `style.css` (`.newsletter`) and are reused by the `rich` preset.

### 4.3 Drag-and-Drop Navigation (WordPress style)

**Frontend**

- Vendor **SortableJS** (MIT, no deps) at `assets/js/vendor/Sortable.min.js` — mirrors how TinyMCE is vendored. It provides reliable nested-list drag & drop (reorder + nesting) without hand-rolling HTML5 DnD.
- New `assets/js/menu-builder.js` implements the builder:
  - **Layout**: two-panel grid in `admin/settings.php` (`tab=menu`).
    - Left panel "Add Menu Items": a Custom Link input (label + URL) with an "Add to Menu" button, plus checkbox lists of Pages and Categories (fetched server-side into `data-menu-source` JSON), each with an "Add selected" button.
    - Right panel "Menu Structure": nested `<ol>` list of items. Each item row: drag handle, label text, inline edit controls (label/URL inputs revealed on click), indent/outdent buttons (accessibility fallback), remove button.
  - **Nesting**: every `<li>` may contain an `<ol class="submenu">`. Sortable instances are initialized on the root list and each submenu; `group` config allows moving between nested lists. Max nesting depth = 3 (configurable) to prevent runaway DOM and render issues.
  - **Serialize**: on form submit, walk the DOM tree pre-order and produce JSON:
    ```json
    [
      { "key": "3", "label": "About", "url": "/page/about", "children": [
        { "key": "new1", "label": "History", "url": "/page/history", "children": [] }
      ]},
      { "key": "new0", "label": "World", "url": "/category/world", "children": [] }
    ]
    ```
    - `key` = existing DB id (numeric) or `new:<n>` for unsaved items.
    - `sort_order` is implicit (sibling index).
    - `parent_id` is implicit (nesting depth).
  - The serialized JSON is submitted as a single hidden field `menu_tree`.
  - Server-rendered rows for existing items are generated in `settings.php` from the `menus` table using a pre-order walk (same shape as the JSON above) so the DOM ↔ server model matches 1:1.

**Server side (`admin/settings.php` `tab=menu`)**

- Replace the parallel-array handling with JSON parsing:
  1. `Security::csrfValidate()`.
  2. Read `menu_tree` JSON; `json_decode`; validate it is an array; sanitize every `label` (trim, max 100 chars) and `url` (allow internal paths `/...` or `http(s)://...`, strip `javascript:`; empty URL → `#`).
  3. Enforce max depth = 3 and max total items (e.g. 200) as hard guards.
  4. Reuse the existing **delete-then-reinsert** strategy (already used today) for simplicity and cycle safety:
     - `DELETE FROM menus`.
     - Recursive insert in pre-order carrying the parent's new id and a global order counter. Each node inserts `(label, url, parent_id=newParentId, sort_order=order++)`.
     - Map each node's `key` to its new id so children resolve the correct parent id at insert time. (Because pre-order guarantees parents are inserted before their children, the parent's new id is always available.)
     - Skip empty-label nodes.
  5. `flash_set('success', ...)` + redirect to `settings.php?tab=menu`.

- Keep the `menus` table schema unchanged (`parent_id`, `sort_order`, `status` still apply). `menu_tree()` and `render_nav_menu()` require no changes, so the public site and the Category auto-append behavior are untouched.

**Accessibility / UX**

- Drag handles have `aria-label`; indent/outdent buttons provide a keyboard path for nesting (the same "parent is the previous ancestor" semantics as WordPress).
- Visual depth guides (left padding / indent lines) make nesting obvious.
- A live mini-preview of the menu structure (rendered as the public dropdown) can be added cheaply by reusing the serialized JSON on the client; mark as optional enhancement.

## 5. Data Model & Settings Changes

No schema change. New/changed keys in `app/defaults.php`:

```php
'header_style' => 'classic',   // was 'center'
'footer_style' => 'classic',   // new
```

- Fresh installs get these via the existing `install.php` seed loop (reads `defaults.php` automatically).
- Existing installs: `setting('header_style')` may return `'center'`/`'left'` → whitelist fallback maps to `classic`; `footer_style` returns `null` → default `'classic'`. **No `migrate.php` change required.**
- Update `migrate.php` only if we later want to normalize old `header_style` values in the DB; this is optional and can be a no-op because of the runtime fallback.

## 6. Admin UI Changes (`admin/settings.php` + `assets/css/admin.css` + new JS)

### 6.1 Theme tab

- Replace the `Header Layout` `<select>` with a **preset picker**: a row of radio "cards", each showing a CSS-drawn wireframe thumbnail (pure HTML/CSS/SVG, no image assets), a name, and a one-line description. Hidden radio + label styling gives the selected state.
- Add an identical **Footer Design** preset picker for `footer_style`.
- Keep color pickers + breaking-ticker toggle as-is.
- POST handler (`$tab === 'theme'`) adds:
  ```php
  $themeValues['header_style'] = in_array($_POST['header_style'] ?? '', ['classic','modern','compact'], true)
      ? $_POST['header_style'] : 'classic';
  $themeValues['footer_style'] = in_array($_POST['footer_style'] ?? '', ['classic','minimal','rich'], true)
      ? $_POST['footer_style'] : 'classic';
  ```
- Optional enhancement: a live-preview iframe (the real public page) using `srcdoc` so the owner sees the chosen design without leaving the admin. Kept optional to control scope.

### 6.2 Navigation tab

- Two-panel layout described in §4.3.
- Existing items rendered by a PHP pre-order walk of the `menus` table; new items added via JS.
- Load `Sortable.min.js` + `menu-builder.js` + `admin.css` additions (`.nav-builder` panels, item rows, handles, depth guides).
- Remove the old `#menu-rows` / parallel-array markup and `addMenuRow()`.

## 7. Asset Changes

| File | Change |
|------|--------|
| `assets/js/vendor/Sortable.min.js` | New, vendored MIT library. |
| `assets/js/menu-builder.js` | New: two-panel builder, nesting, serialization. |
| `assets/css/style.css` | Scoped rules for `.header-style-{preset}` and `.footer-style-{preset}`. |
| `assets/css/admin.css` | Preset-card picker styles + nav builder panel styles. |
| `views/partials/header_{classic,modern,compact}.php` | New partials. |
| `views/partials/footer_{classic,minimal,rich}.php` | New partials. |
| `views/header.php` | Include selected header partial; body class update. |
| `views/footer.php` | Include selected footer partial; footer class update. |
| `views/theme.php` | Remove `header_style === 'left'` special case; optional per-preset variable overrides. |
| `app/defaults.php` | `header_style` → `classic`, add `footer_style` → `classic`. |
| `admin/settings.php` | New POST logic (theme presets + JSON menu save) + new UI markup. |
| `README.md` | Update "Theme & Navigation" section (optional but recommended). |

## 8. Security & Validation

- Every form keeps `Security::csrfField()` + `Security::csrfValidate()`.
- `header_style` / `footer_style` are whitelist-validated server-side (never trusted from the client).
- Menu labels: trim, length cap, output-escaped with `e()` everywhere on render.
- Menu URLs: allow `/relative` or `http(s)://...` only; reject `javascript:`, `data:`, etc.; empty → `#`.
- JSON payload: `json_decode` with strict shape check; hard caps on depth (3) and item count; numbers cast.
- No new SQL string concatenation; continue using PDO prepared statements (`DB::run` / prepared inserts).

## 9. Backward Compatibility

- Old `header_style` `center`/`left` silently map to `classic` (visually identical to the previous `center` default).
- Existing `menus` rows with `parent_id`/`sort_order` render identically in the new builder and the public nav.
- `menu_tree()` / `render_nav_menu()` untouched → category auto-append and mobile dropdown behavior preserved.
- Setting the footer style defaults to `classic` for existing installs → no visual change until the owner picks a new design.

## 10. Edge Cases

| Case | Handling |
|------|----------|
| Empty menu | Builder shows an empty structure list with a placeholder hint; save produces zero rows. |
| Single-item menu | Renders as a single top-level link; no dropdown CSS triggered. |
| Cycle (item becomes own ancestor) | Impossible via DOM nesting (an `<ol>` cannot contain itself); server depth guard as belt-and-braces. |
| Duplicate/blank labels | Blank labels skipped server-side; duplicate labels allowed (WP allows). |
| URL field empty | Defaults to `#`. |
| Very deep nesting | Client depth limit (3) + server max-depth guard; deeper items are truncated. |
| Old browsers without Drag & Drop | Indent/outdent + order buttons remain as a keyboard/mouse fallback; SortableJS also has keyboard support. |
| `header_breaking` toggled off | Breaking ticker block already conditionally rendered; preserved in all header presets. |
| Mobile | Presets keep the existing responsive navbar (`.nav-menu` mobile dropdown); verify each preset at ≤900px. |

## 11. Testing Plan

No automated test framework exists in the repo; use `php -l` linting on changed PHP files plus a manual checklist:

1. **Theme**
   - Select each of the 3 header presets → save → open public homepage → verify layout, colors, logo/tagline, search, breaking ticker all present and correct at desktop + mobile.
   - Select each of the 3 footer presets → save → verify columns/content/copyright/social as defined.
   - Change primary/secondary/accent colors after picking a preset → theme.php variables still apply to the chosen design.
2. **Navigation**
   - Add items from Categories and Pages via the left panel.
   - Add custom links (valid URL, relative path, and an attempted `javascript:` URL → rejected).
   - Drag to reorder top-level items; save; verify order on the public nav.
   - Drag an item onto another to nest → save → verify drop-down sub-menu appears on hover (desktop) and inline (mobile); verify active-state highlighting.
   - Nest to depth 3; verify depth cap blocks deeper nesting.
   - Remove items; empty menu edge case.
   - Re-edit an existing menu that predates the feature → loads correctly into the builder, save round-trips.
3. **Regression**
   - CSRF present on theme and menu saves; old parallel-array POSTs no longer accepted.
   - `php install.php` seeds `header_style=classic`, `footer_style=classic`, and default menus.
   - Existing non-admin roles cannot access settings (403 guard already in `settings.php`).
   - `php -l` on all changed PHP files.

## 12. Implementation Order

1. `app/defaults.php` — new settings keys.
2. `views/partials/header_*.php` + `views/header.php` + `assets/css/style.css` header rules + `views/theme.php` cleanup.
3. `views/partials/footer_*.php` + `views/footer.php` + footer CSS rules.
4. Admin Theme tab preset pickers (markup + CSS + POST validation).
5. Vendor `Sortable.min.js`; `assets/js/menu-builder.js`; admin Navigation tab two-panel UI.
6. Server-side JSON menu save in `admin/settings.php`.
7. Manual test checklist + `php -l` pass + README update.

## 13. Out of Scope (for a follow-up)

- Full "theme pack" system (upload/install pre-built themes with their own templates).
- WYSIWYG live-drag canvas (resize/position widgets).
- Multiple navigation locations (e.g. footer menu vs primary menu) — current single `menus` table is kept.
