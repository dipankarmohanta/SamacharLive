# Technical Plan — Issue: Multi Language Support

## 1. Overview

Make the news portal fully Unicode-compatible, targeting Indian languages — primarily
**Odia**, **Hindi (Devanagari)**, and **Bangla (Bengali)**. The two concrete goals:

1. **Paste-safe Unicode input** — an editor can paste Odia/Hindi/Bangla text (including
   combining matras, virama conjuncts, ZWJ/ZWNJ) into any input and it is stored and
   rendered back **without any change to character position or order** (no
   reordering, no normalization, no transliteration, no double-encoding).
2. **Unicode throughout the stack** — storage, slugs, URLs, search, sitemap, and
   fonts all handle these scripts so the site can be published entirely in Odia,
   Hindi, or Bangla.

No UI translation of the admin/public chrome is in scope (see §13).

## 2. Current State Analysis

The project is already *mostly* Unicode-safe thanks to an all-UTF-8 design. The gaps are
specific and localized.

| Area | Current implementation | Unicode gap |
|------|------------------------|-------------|
| Database storage | All tables `utf8mb4` / `utf8mb4_unicode_ci` (`app/schema.php`); DSN `charset=utf8mb4`; `PDO::ATTR_EMULATE_PREPARES=false` | **OK** — 4-byte safe, Odia/Hindi/Bangla/emoji all store fine |
| HTML output | `<meta charset="UTF-8">`; `Security::e()` = `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` | **OK** — non-ASCII passes through byte-for-byte |
| Form input | HTML5 forms default to UTF-8 | **OK** |
| TinyMCE | `entity_encoding: 'raw'` (stores literal characters, good) | **OK** for the editor pipeline; editor *font* lacks Indic coverage |
| Multibyte config | No `mb_internal_encoding()` set → `mb_*` functions use the PHP default | **Gap** — truncation/`mb_strtolower` behavior becomes config-dependent |
| **Slug generation** | `Security::slugify()` keeps only `[a-z0-9\s-]`, stripping everything else → an Odia title collapses to `item` (+ random hex on collision) | **Major gap** — breaks localized URLs and lookups |
| **URL routing** | `Router.php` uses `parse_url(REQUEST_URI, PHP_URL_PATH)` which stays percent-encoded; the `?route=` fallback arrives pre-decoded | **Gap** — inconsistent decoding for Unicode slugs; no UTF-8 validity guard |
| **URL output** | Canonical / og:url / sitemap / all `<a href>` emit the raw (unencoded) slug | **Gap** — should percent-encode for SEO + link correctness |
| Fonts (public) | `--font: 'Inter', …` only (Latin); Indic falls back to arbitrary system fonts | **Gap** — inconsistent rendering / wrong glyphs for Odia–Bengali |
| Fonts (editor) | `content_style` font stack is system fonts without Indic coverage | **Gap** — editor shows tofu / mispositioned matras |
| Search | `LIKE %q%` + `mb_strlen($q) < 2` guard | **OK** for LIKE; `FULLTEXT ft_search` uses default parser (unused by current query, see §4.5) |
| JSON API | `json_response()` sets `charset=utf-8` | **OK** |

Key files:

- `app/Security.php` — `slugify()` (destroys non-ASCII), `e()`, `truncate()` (already mb-based).
- `app/Router.php` — segment parsing (no decode, no UTF-8 guard).
- `app/helpers.php` — `site_url()`, `render_nav_menu()` active-state match, `meta_desc()` (already `/u`).
- `app/routes/{news,category,tag,sitemap,page,home}.php` — every `href`/canonical URL.
- `views/partials/{news_card,sidebar,footer_*}.php`, `views/header.php` — article/category/tag links.
- `assets/css/style.css` — `--font` / `--font-serif`.
- `admin/includes/editor.php` — TinyMCE `content_style`.
- `admin/news_edit.php`, `reporter/add-news.php` — save path (validation point).

## 3. Requirements (Acceptance Criteria)

1. Pasting Odia / Hindi / Bangla text (including vowel signs like Odia `ୋ`, Devanagari
   `ो`, Bengali `ো`, conjuncts like `ଗ୍ର`, `क्ष`, and ZWJ/ZWNJ) into **title, excerpt,
   tags, slug, and the content editor** preserves character order exactly — visually
   the shaped output matches the source.
2. Full round-trip: paste → save → reload admin form → view on the public article page —
   the text is byte-identical (verified by diffing the saved text with the original).
3. Unicode titles generate readable, localized slugs (e.g. `/news/ଓଡ଼ିଆ-ଖବର`); ASCII
   titles keep exactly today's slug behavior (no regression).
4. Unicode slugs work via both the clean URL (`/news/…`) and the `?route=` fallback, and
   canonical / og:url / sitemap URLs are percent-encoded correctly.
5. Odia, Hindi, and Bangla render with correct shaping/positioning on the public site and
   inside the editor (font stack covers all three scripts).
6. Search works for Odia/Hindi/Bangla queries; Unicode tags link and filter correctly.
7. Existing English content and existing installs keep working unchanged.
8. `php -l` passes on all changed PHP files.

## 4. Design Approach

### 4.1 Paste-safe Unicode input pipeline

Guarantee a single, unbroken UTF-8 chain and **do no transformation** of pasted text:

- **Never normalize or reorder.** No NFC/NFD conversion, no `iconv` transliteration, no
  diacritic-stripping. Odia/Bengali visual ordering (e.g. the `ୋ`/`ো` vowel sign drawn
  to the left of the consonant while typed after it) is the *browser's* shaping job — the
  server must store the exact code-point sequence the user pasted.
- **Configure mb functions once** in `app/bootstrap.php`:
  ```php
  mb_internal_encoding('UTF-8');
  mb_http_output('UTF-8');
  ```
  Makes every `mb_*` call (`truncate`, `mb_substr`, `mb_strtolower`, `mb_strlen`) behave
  deterministically regardless of `php.ini`.
- **Validate on save** — every textual field (title, excerpt, content, tags, slug, SEO
  fields) must pass `mb_check_encoding($value, 'UTF-8')` before insert/update; reject with
  a friendly error otherwise. This guarantees invalid byte sequences never enter the DB.
- **Keep `entity_encoding: 'raw'`** in TinyMCE (already set) so the editor saves literal
  characters, never `&#x…;` entities — entities would survive but "raw" is simpler and
  already in place. No `entities`/`entity_encoding` change required.
- **No client-side string munging.** `assets/js/*` contains no slug/transliteration logic;
  add nothing that rewrites pasted text. (Confirm no `normalize()` / `toLocaleLowerCase()`
  is introduced.)

The DB already stores utf8mb4, so the only server-side hardening is the
`mb_check_encoding` guard + mb config above.

### 4.2 Unicode-safe slugs and URLs

This is the largest functional gap. Two changes: *generate* Unicode slugs, and *encode /
decode* them consistently at every URL boundary.

**4.2.1 Slug generation (`app/Security.php`)**

Rewrite `slugify()` to be Unicode-aware while remaining byte-identical to today for ASCII
input:

```php
public static function slugify(string $text, int $maxLen = 190): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    // Keep Unicode letters/numbers and spaces/hyphens; drop the rest.
    $text = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $text);
    $text = preg_replace('/[\s_]+/u', '-', $text);
    $text = preg_replace('/-+/u', '-', $text);
    $text = trim($text, '-');
    if ($text === '') { return 'item'; }
    return mb_substr($text, 0, $maxLen, 'UTF-8');
}
```

- `\p{L}` / `\p{N}` keep Odia `ଓଡ଼ିଆ`, Devanagari `खबर`, Bengali `সংবাদ`; combining
  marks (`\p{M}`) ride along because they are attached to letters (the class `[^\p{L}\p{N}\s\-]`
  keeps them). Word separators become `-`, exactly like today.
- ASCII path (`mb_strtolower` on ASCII == `strtolower`, the old regex range is a subset of
  `\p{L}\p{N}`) → output identical to the current function. **No regression.**
- Length cap 190 chars keeps Unicode slugs under the `VARCHAR(255)` limit (UTF-8 bytes
  can reach ~4× chars; 190 chars × 4 B = 760 B < 1020 B index limit).
- Collision handling in `admin/news_edit.php` already appends random hex — unchanged.
- `slugify()` is used for **news/category/page** slugs; these become Unicode-friendly
  automatically.

**4.2.2 Tags — stop slugifying**

Tag links currently call `Security::slugify($t)` which (a) mangles Unicode and (b) already
breaks multi-word tags ("sports news" → `sports-news` never matches the stored tag). New
model: tag URLs carry the **raw tag, percent-encoded**; the tag route decodes it back and
`LIKE`s it:

- Link: `href="/tag/<?php echo e(rawurlencode($t)); ?>"` in `news.php`, `sidebar.php`.
- Route (`tag.php`): the router already supplies a decoded segment (see 4.2.3) → query
  unchanged. This fixes Unicode tags and multi-word tags in one move.

**4.2.3 Decoding incoming paths (`app/Router.php`)**

Unified decoding rules:

```php
$segments = $path === '/' ? [] : array_values(array_filter(explode('/', $path)));

// Decode percent-encoding once, only for the REQUEST_URI source.
if (!isset($_GET['route'])) {
    $segments = array_map('rawurldecode', $segments);
}
// Reject malformed UTF-8 instead of storing/searching garbage.
foreach ($segments as $seg) {
    if (!Security::isValidUtf8($seg)) { Security::notFound(); }
}
```

- `REQUEST_URI` (Apache passes it percent-encoded) → decode once with `rawurldecode`
  (`%E0%AC%93…` → `ଓ…`). The `?route=` fallback is already decoded by PHP's query-string
  parser → **never decode it again** (avoids double-decoding a literal `%25`).
- `Security::isValidUtf8()` = `mb_check_encoding($s, 'UTF-8')` (returns false on `null`/
  empty). Route names (`news`, `category`…) are ASCII so decoding before the switch is safe.
- `.htaccess` needs no change — `mod_rewrite` forwards UTF-8 request paths byte-for-byte.

**4.2.4 Encoding outgoing URLs (helper + call sites)**

Add one helper in `app/helpers.php`:

```php
/** Percent-encode a URL path segment (Unicode-safe) and HTML-escape it. */
function url_segment(string $s): string
{
    return rawurlencode($s);
}
```

Apply `url_segment()` to every dynamic slug/tag interpolated into a URL. Full inventory
(already enumerated via grep):

- `app/routes/news.php` — breadcrumb `category/`, canonical `site_url('news/'… )`, tag links.
- `app/routes/category.php` — canonical + pagination `?page=` links.
- `app/routes/sitemap.php` — `<loc>` for categories, pages, news (XML should contain
  percent-encoded IRIs).
- `app/routes/home.php` — hero + section-card links.
- `views/header.php` — breaking-ticker `news/` links.
- `views/partials/news_card.php` — card image/title links + category badge.
- `views/partials/sidebar.php` — popular/latest links + tag links.
- `views/partials/footer_classic.php`, `footer_rich.php` — category/page links.
- `views/partials/footer_minimal.php` — none (no dynamic links).
- `admin/pages.php`, `reporter/index.php` — preview links (cosmetic, same treatment).

Share/OG URLs in `news.php` (`urlencode($canonical)`) already encode — keep.

Also fix `render_nav_menu()` active-state matching in `helpers.php`: compare against the
**decoded** path (it currently uses `$_SERVER['REQUEST_URI']`, which is still encoded when
a menu item label/URL is Unicode):

```php
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
// decode once to mirror Router.php
$isActive = ($url !== '/' && str_starts_with(rawurldecode($reqPath), $url));
```

### 4.3 Font support and rendering

The scripts are all LTR, so no `dir` changes. The work is purely font coverage.

- **Public site** — extend the font stacks in `assets/css/style.css:19-20`:
  ```css
  --font: 'Inter', 'Noto Sans Oriya', 'Noto Sans Devanagari', 'Noto Sans Bengali',
          'Nirmala UI', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  --font-serif: Georgia, 'Noto Serif Oriya', 'Noto Serif Devanagari', 'Noto Serif Bengali',
                'Times New Roman', serif;
  ```
  Browsers then shape each script with a font that actually has the glyphs and proper
  OpenType shaping, fixing matra/conjunct positioning.
- **Load the fonts** — the site already loads `Inter` from Google Fonts and CSP already
  allows `https://fonts.googleapis.com` and `https://fonts.gstatic.com` (verified in
  `Security::sendSecurityHeaders()`), so extend the `<link>` in `views/header.php`:
  ```html
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Oriya:wght@400;600;700&family=Noto+Sans+Devanagari:wght@400;600;700&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet">
  ```
  Optional follow-up (see §13): self-host the Noto subsets to honor the project's
  "no external CDN" convention more strictly. This change is additive and safe for
  existing sites that already depend on Google Fonts.
- **Editor** — update `content_style` in `admin/includes/editor.php:34` to the same
  chain (`'Inter', 'Noto Sans Oriya', 'Noto Sans Devanagari', 'Noto Sans Bengali', …`)
  so reporters *see* correctly shaped text while composing Odia/Hindi/Bangla. Keep
  `entity_encoding: 'raw'`.

### 4.4 Unicode-aware string handling (PHP)

Covered mostly by §4.1 config:

- `bootstrap.php`: `mb_internal_encoding('UTF-8')` + `mb_http_output('UTF-8')`.
- `Security::truncate()` already uses `mb_strlen`/`mb_substr`; `meta_desc()` already uses
  `preg_replace('/\s+/u')`; `news.php:106` uses `mb_strtoupper`/`mb_substr` — all correct
  once the mb default is pinned.
- `search.php` guards with `mb_strlen($q) < 2`. Odia words can be short; consider relaxing
  to "reject only empty/whitespace, and let the 2-char rule apply per-word" (optional,
  see §10). No functional blocker.

### 4.5 Search and FULLTEXT

- The active search query uses `LIKE %q%` (case/accent-insensitive under
  `utf8mb4_unicode_ci`), which already works for Indic scripts — **no change required**.
- The `FULLTEXT KEY ft_search` uses the default parser, which tokenizes Indic poorly. It is
  currently unused by any query; leave it as-is. Optional future work: drop and recreate it
  with the `ngram` parser if a `MATCH … AGAINST` search is ever adopted (see §13).

### 4.6 Optional: per-article language tag (stretch, for `inLanguage` correctness)

For SEO, `inLanguage`/`og:locale` currently come from the global `site_lang`. In a
mixed-language site (Odia articles + Hindi articles), per-article language is more correct:

- Add `news.lang VARCHAR(20)` (BCP-47, default `or-IN`/`hi`/`bn` from a dropdown) via
  `migrate.php` + `schema.php`.
- `news.php` sets `inLanguage`/`article:language` from `$item['lang'] ?: setting('site_lang')`.
- Admin form adds a `<select>` in `news_edit.php` / `reporter/add-news.php`.

Marked **optional**; the core requirement is satisfied without it.

## 5. Data Model & Settings Changes

- **Core: no schema change.** Existing utf8mb4 tables + DSN charset already support all
  four-byte Unicode.
- **No new settings keys.**
- Optional (stretch, §4.6): `news.lang VARCHAR(20)` added in `app/schema.php` and
  `migrate.php` (idempotent `ADD COLUMN`), default `NULL` → falls back to `site_lang`.

## 6. Code Changes (file-by-file)

| File | Change |
|------|--------|
| `app/bootstrap.php` | `mb_internal_encoding('UTF-8')`, `mb_http_output('UTF-8')` |
| `app/Security.php` | Unicode-aware `slugify()` (`\p{L}\p{N}`, `mb_strtolower`, cap 190); add `isValidUtf8()` |
| `app/Router.php` | Decode REQUEST_URI segments once (`rawurldecode`); skip for `?route=`; UTF-8 validity guard → 404 |
| `app/helpers.php` | Add `url_segment()`; fix `render_nav_menu()` active-match to use decoded path |
| `app/routes/news.php` | Breadcrumb/canonical/related/tag URLs via `url_segment()`; `inLanguage` from optional `lang` |
| `app/routes/category.php` | Canonical + pagination links via `url_segment()` |
| `app/routes/tag.php` | Tag segment already decoded by router; pagination link via `url_segment()` |
| `app/routes/sitemap.php` | `<loc>` for category/page/news via `url_segment()` |
| `app/routes/home.php` | Hero + section links via `url_segment()` |
| `views/header.php` | Google Fonts `<link>` extended; breaking-ticker links encoded |
| `views/partials/news_card.php` | Image/title/category links encoded |
| `views/partials/sidebar.php` | Popular/latest links encoded; tag links use `rawurlencode($t)` (raw tag, not slug) |
| `views/partials/footer_classic.php`, `footer_rich.php` | Category/page links encoded |
| `admin/includes/editor.php` | `content_style` font chain + editor (optionally) loads Noto families |
| `admin/news_edit.php`, `reporter/add-news.php` | `mb_check_encoding()` validation on title/excerpt/content/tags/seo fields; optional `lang` select |
| `admin/categories.php`, `admin/pages.php` | Inherit new `slugify()` automatically; add `mb_check_encoding` guard |
| `assets/css/style.css` | `--font` / `--font-serif` Indic fallback chains |
| `admin/pages.php`, `reporter/index.php` | Preview links encoded (cosmetic) |

## 7. Security & Validation

- **UTF-8 validity guard on every text field** (`mb_check_encoding`) before insert/update —
  rejects malformed bytes that could break rendering or the DB.
- **Router guard** — decoded URL segments must be valid UTF-8, else 404; prevents
  malformed `%` sequences and encoding-confusion tricks.
- **Slug sanitization** — only `\p{L}\p{N}` + space/hyphen survive; no HTML, no CRLF, no
  control chars; length-capped; `rawurlencode`d at output so no metacharacters reach the
  URL.
- All output continues through `e()` (UTF-8 `htmlspecialchars`); URL-encoded segments are
  additionally encoded by `rawurlencode` *before* escaping.
- No new SQL string concatenation — all queries remain PDO prepared statements.
- CSRF unchanged on all admin/reporter forms.
- No transformation of pasted user text anywhere (explicitly avoids NFC/NFD/reordering),
  satisfying the "won't change character position" requirement by construction.

## 8. Backward Compatibility

- New `slugify()` output is identical to the old one for ASCII input; all existing
  English slugs continue to route exactly as before.
- Existing rows with old `item-xxxx` slugs (previously generated from Unicode titles)
  remain reachable; editors may regenerate the slug by editing the article (slug field is
  editable in the admin).
- Tag URLs change from `slugify(tag)` to `rawurlencode(tag)`:
  - Single-word ASCII tags: `rawurlencode('politics') === 'politics'` → unchanged.
  - Multi-word ASCII tags: old behavior was **broken** (never matched); new behavior
    works — a bug fix, not a regression.
  - Unicode tags: previously broken → now work.
- `?route=` fallback continues to work; the "decode only REQUEST_URI" rule prevents
  double-decoding.
- Existing installs: no migration required for the core; `migrate.php` only grows if the
  optional `news.lang` column is adopted.
- `render_nav_menu()` active-state fix only changes how the active class is computed for
  encoded/Unicode URLs; plain ASCII URLs match identically.

## 9. Edge Cases

| Case | Handling |
|------|----------|
| Odia `ୋ`/`ୌ`, Bengali `ো`/`ৌ` vowel signs (typed right, drawn left) | Stored verbatim; browser shaping handles visual position — server never reorders |
| Conjuncts & ZWJ/ZWNJ (`ଗ୍ର`, `क्ष`, `বাংলা` + ZWJ) | Preserved code points; Noto fonts provide correct glyphs |
| Emoji / 4-byte chars in content | utf8mb4 already supports; no change |
| Slug of punctuation-only / blank text | Falls back to `item` (existing behavior) |
| Very long Unicode title → slug | Capped at 190 chars; VARCHAR(255) / index-safe |
| Tag containing `%` or `/` | `rawurlencode` escapes; router `rawurldecode` + segment split keeps it safe |
| Malformed UTF-8 in URL (e.g. `%E0%A4`) | 404 via `isValidUtf8()` guard |
| `?route=` value already decoded | Router skips decode for this path (no double-decode) |
| Single-character Indic search ("ଓ") | Blocked by `mb_strlen < 2`; acceptable — optionally relax rule (see §10) |
| Existing English-only site | All URLs/fonts unchanged visually; fonts are additive fallbacks |
| Old `item-xxxx` slugs | Remain valid; regeneration optional via admin |

## 10. Testing Plan

No automated test framework exists; use `php -l` on every changed PHP file plus a manual
checklist. (PHP isn't available in the authoring environment, so linting is a CI/locally
run step.)

1. **Paste round-trip (core acceptance #1, #2)**
   - Paste representative Odia, Hindi, and Bangla paragraphs (including `ୋ`, `ो`, `ো`,
     `ଗ୍ର`, `क्ष`, ZWJ variants) into title, excerpt, tags, and the TinyMCE content.
   - Save → reopen the edit form → copy back → diff against the original (must be identical).
   - Publish → verify the public page renders the identical text with correct shaping.
2. **Slugs & URLs**
   - Create a news item with a Unicode title → confirm slug is readable
     (`/news/ଓଡ଼ିଆ-ଖବର`), not `item-xxxx`.
   - Open the article via clean URL and via `?route=news/<slug>` — both 200.
   - Confirm canonical/`og:url` and the `/sitemap` `<loc>` are percent-encoded.
   - Unicode category slug and Unicode tag link+filter work.
3. **Fonts**
   - Desktop + mobile (Windows Nirmala, macOS, Android): Odia/Hindi/Bangla headings and
     body render with correct glyphs in both light and dark mode and in the editor.
4. **Search**
   - Query an Odia word → article found; Bengali and Hindi queries likewise.
5. **Regression**
   - Full English flow (create/publish/article/category/search/tag) unchanged.
   - `php install.php --fresh --no-demo` completes; `migrate.php` idempotent.
   - Active nav highlighting still correct for ASCII and now for Unicode menu labels.
   - Malformed UTF-8 URL returns 404, not a DB error.

## 11. Implementation Order

1. `app/bootstrap.php` — mb encoding config.
2. `app/Security.php` — Unicode `slugify()` + `isValidUtf8()`.
3. `app/Router.php` — segment decoding + UTF-8 guard.
4. `app/helpers.php` — `url_segment()` + nav active-match fix.
5. Routes (`news`, `category`, `tag`, `sitemap`, `home`) + partials + header/footer links —
   encode every dynamic segment.
6. Fonts: `assets/css/style.css` stacks, `views/header.php` Google Fonts link,
   `admin/includes/editor.php` `content_style`.
7. Save-path hardening: `mb_check_encoding()` in `news_edit.php` / `add-news.php` /
   `categories.php` / `pages.php`.
8. Optional: `news.lang` column + admin select + `inLanguage` wiring.
9. `php -l` pass, manual checklist (§10), README note under Features/SEO.

## 12. Out of Scope

- Full UI translation (i18n) of the admin and public chrome into Odia/Hindi/Bangla.
- Automatic transliteration or machine translation of content.
- RTL scripts (Urdu/Arabic) — would require `dir="rtl"` handling; not requested.
- Rebuilding search on the `ngram` FULLTEXT parser / `MATCH … AGAINST` (current LIKE
  search works).
- Per-category language filtering/front-end language switcher (only possible after §4.6).
- Self-hosting Noto font subsets (recommended follow-up to the current Google-Fonts usage).
