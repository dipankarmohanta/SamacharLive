# Samachar Live - News Portal (PHP + MySQL)

A lightweight, secure, responsive news portal built with PHP 8 + MySQL/MariaDB. No frameworks, no build steps - just fast PHP. Includes a public website, an online e-paper viewer, a full admin panel, and a dedicated reporter panel.

## Features

**Public website**
- Fully responsive, fluid modern layout (mobile-first CSS grid/flexbox)
- Homepage with hero story, breaking-news ticker, category sections, latest grid
- Category, article, search, tag, and static-page views
- Native image lazy-loading (`loading="lazy"`) with width/height hints to prevent layout shift
- Dark-mode toggle, social sharing, most-read / latest widgets, tag cloud

**E-paper viewer** (samajaepaper.in style)
- Issue archive grid with cover thumbnails
- Page-by-page online viewer with zoom controls
- Upload a PDF and pages are auto-rendered to images (via `pdftoppm`)
- Automatic cover image + page count; PDF download + prev/next issue navigation
- Admin on/off toggle to hide the E-paper feature when not required (links hidden, `/epaper` returns 404, excluded from the sitemap)

**Admin panel**
- Dashboard with stats
- News: create / edit / publish / unpublish / delete, featured & breaking flags, tags, image upload
- **Visual editor**: TinyMCE (self-hosted, MIT) for news and page content — formatting, headings, lists, links, images (inline upload), tables, media, full-screen; used in both admin and reporter panels
- Categories: hierarchical (parent/child), reorder, show/hide, delete protection
- Static pages (About, Contact, Privacy...)
- Users: create reporters/editors/admins, enable/disable, reset passwords
- E-paper issues: upload PDF + cover, edit, hide, delete
- **Theme customization**: primary/secondary/accent colors, 3 header designs (Classic / Modern / Compact) and 3 footer designs (Classic / Minimal / Rich) with visual pickers, breaking ticker on/off
- **Navigation customization**: WordPress-style drag-and-drop menu builder — reorder and nest items into unlimited-depth drop-down sub-menus from a two-panel UI (label / URL / indent / outdent / remove)
- **Advertisements**: image/banner ads with validity dates, third-party script integrations (Meta pixel, Google AdSense, analytics), and a master on/off switch with placement selection
- **Custom domains**: manage the domain names the site should serve on from Settings (with subdomain wildcards); the matching domain is used for canonical/SEO URLs

**Reporter panel** (limited to posting news)
- Submit news (with the same TinyMCE visual editor) that goes to `pending` review
- Edit own draft/pending stories
- Cannot publish, change users, or touch settings

## Security

- PDO prepared statements everywhere (SQL injection safe)
- CSRF token on every form
- Password hashing with `password_hash()` / `password_verify()`
- Login brute-force throttling (per IP + per username)
- Output escaping (XSS) + strict `Content-Security-Policy`
- **Allowlist-based server-side HTML sanitization** of news/page content against stored XSS: a `DOMDocument` allowlist (tags, attributes, URL schemes, inline CSS properties) plus a regex scrub, applied both on save and on output
- **Host-header validation**: `HTTP_HOST` is validated and matched against a configurable domain allowlist (`APP_ALLOWED_HOSTS` constant + Settings -> Domains) so canonical/og/sitemap URLs can't be poisoned by crafted Host headers
- `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` headers
- Upload validation: real MIME sniffing + image re-decode, random filenames, scripts blocked in `/uploads`
- Session hardening: HttpOnly, SameSite=Lax, ID regeneration on login, idle timeout
- CLI-only installer (refuses web execution)
- Maintenance-mode switch (bypassable by admins)

## Requirements

- PHP 8.0+ with `pdo_mysql`, `gd`, `mbstring`, `fileinfo`
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` (or nginx with equivalent rewrite rules)
- `poppler-utils` (`pdftoppm`, `pdfinfo`) for the e-paper PDF renderer
- `ext-fileinfo` (usually bundled)

## Installation

1. Create a database and user:

```sql
CREATE DATABASE newsportal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'newsportal'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON newsportal.* TO 'newsportal'@'localhost';
```

2. Edit database credentials in `config.php`.

3. Run the installer (creates tables, default settings, admin, demo content):

```bash
php install.php              # full install + demo content
php install.php --no-demo    # skip demo content
php install.php --fresh      # drop & recreate tables first
```

4. Point your web root at the project directory (`.htaccess` is included).

5. **Delete `install.php` after installation** (or keep it - it is web-blocked anyway).

Default login: `admin` / `Admin@1234` (change it immediately).

**Upgrading an existing install**: run `php migrate.php` once to add any new columns/tables (e.g. the `ads` and `ad_integrations` tables).

## Directory Structure

```
├── index.php            Front controller (routes everything)
├── config.php           Database + environment config
├── install.php          CLI installer
├── .htaccess            Rewrites, caching, PHP limits
├── app/                 Core (DB, Security, Auth, Settings, Epaper, Ads, Router, routes/)
├── views/               Public templates (header, footer, partials, 404...)
├── assets/              CSS, JS, images
├── admin/               Admin panel
├── reporter/            Reporter panel
└── uploads/             Uploaded images, logos, e-paper PDFs/pages (blocked from execution)
```

## Roles

| Capability            | Admin | Editor | Reporter |
|-----------------------|:-----:|:------:|:--------:|
| Post / edit news      |  yes  |  yes   |    yes*  |
| Publish news          |  yes  |  yes   |    no    |
| Manage categories     |  yes  |  yes   |    no    |
| Manage pages, epaper  |  yes  |  yes   |    no    |
| Manage advertisements |  yes  |  yes   |    no    |
| Manage users          |  yes  |  no    |    no    |
| Theme & nav settings  |  yes  |  no    |    no    |
| Ad settings           |  yes  |  no    |    no    |

\* Reporter submissions are saved as `pending` and require an editor/admin to publish.

## SEO

- Clean URLs: `/category/sports`, `/news/example-title`, `/epaper`
- Per-page meta description + canonical URLs (custom-domain aware)
- Open Graph + Twitter Card tags
- JSON-LD `NewsArticle` schema on articles
- Auto-generated XML sitemap at `/sitemap`
- Semantic HTML, alt text, descriptive slugs

## E-Paper Upload

1. Admin -> Epaper -> upload a PDF (+ optional cover image).
2. Pages are rendered automatically into the online viewer.
3. Cover image defaults to page 1. Page count is detected automatically.
4. To switch the feature off, use Settings -> General -> Feature Settings.

## Advertisements

Admin -> Advertisements:
- **Ad Management**: create image or HTML/JS banner ads with a name, notes, placement and validity dates (valid from / renew-until). Ads only render while active and inside their date window; the list warns when an ad has expired.
- **3rd Party Integration**: add Meta Pixel, Google AdSense, analytics or any custom script with a name and position (head / top of body / bottom of body). Scripts are injected into every public page and toggled per item.
- **Ad Settings**: master switch to enable or disable all advertisements, plus checkboxes choosing where ads are inserted — header, homepage (below hero), category pages, article top/bottom, sidebar top/bottom, and footer.

## Theme, Navigation & Domains

Admin -> Settings:
- **Theme** tab: pick brand colors (live preview), choose from 3 header designs and 3 footer designs (visual picker cards), ticker toggle
- **Navigation** tab: WordPress-style drag-and-drop menu builder — add custom links, pages, or categories from the left panel, then drag items in the right panel to reorder or nest them into drop-down sub-menus (up to 3 levels deep); label / URL editing, indent / outdent, and remove
- **Domains** tab: manage custom domains (see below)
- The nav shows exactly the items you configure — categories (or any pages/links) are added manually from the Categories panel
- Sub-menus render as hover dropdowns on desktop and expand inline on mobile

## Custom Domains

Admin -> Settings -> **Domains** tab:

- Add the domain names where the site should be reachable, one per line (comma-separated also works). Entries are validated on save.
- Subdomain wildcards are supported with a leading dot: `.example.com` matches `example.com` and all of its subdomains.
- The first listed domain is the **primary** domain, used for canonical/og/sitemap URLs whenever a visitor arrives on an unlisted address (this also keeps Host-header poisoning blocked).
- The Status card shows your current host, the effective site URL, and the primary domain.
- Point each domain's DNS at this server to complete the setup — the app accepts and serves the domain, but DNS and HTTPS routing are configured outside the app.
- A hard-coded `APP_ALLOWED_HOSTS` constant in `config.php` is also supported and merges with the Settings list.

## Deployment Notes

- Enable HTTPS and uncomment the HTTP->HTTPS redirect in `.htaccess`
- Update the `Sitemap` URL in `robots.txt` for your domain
- Set a strong `APP_TIMEZONE` and database password
- For nginx, add a `location` that routes non-existent files to `index.php?route=$uri`
- Store uploads with web-server write permission only; keep the DB user least-privileged

## License

MIT - free to use and modify.
