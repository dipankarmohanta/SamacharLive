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

**Admin panel**
- Dashboard with stats
- News: create / edit / publish / unpublish / delete, featured & breaking flags, tags, image upload
- **Visual editor**: TinyMCE (self-hosted, MIT) for news and page content — formatting, headings, lists, links, images (inline upload), tables, media, full-screen; used in both admin and reporter panels
- Categories: hierarchical (parent/child), reorder, show/hide, delete protection
- Static pages (About, Contact, Privacy...)
- Users: create reporters/editors/admins, enable/disable, reset passwords
- E-paper issues: upload PDF + cover, edit, hide, delete
- **Theme customization**: primary/secondary/accent colors, header layout, breaking ticker on/off
- **Navigation customization**: WordPress-style menu builder with unlimited parent/child sub-menus (label / URL / parent / order)

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

## Directory Structure

```
├── index.php            Front controller (routes everything)
├── config.php           Database + environment config
├── install.php          CLI installer
├── .htaccess            Rewrites, caching, PHP limits
├── app/                 Core (DB, Security, Auth, Settings, Epaper, Router, routes/)
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
| Manage users          |  yes  |  no    |    no    |
| Theme & nav settings  |  yes  |  no    |    no    |

\* Reporter submissions are saved as `pending` and require an editor/admin to publish.

## SEO

- Clean URLs: `/category/sports`, `/news/example-title`, `/epaper`
- Per-page meta description + canonical URLs
- Open Graph + Twitter Card tags
- JSON-LD `NewsArticle` schema on articles
- Auto-generated XML sitemap at `/sitemap`
- Semantic HTML, alt text, descriptive slugs

## E-Paper Upload

1. Admin -> Epaper -> upload a PDF (+ optional cover image).
2. Pages are rendered automatically into the online viewer.
3. Cover image defaults to page 1. Page count is detected automatically.

## Theme & Navigation

Admin -> Settings:
- **Theme** tab: pick brand colors (live preview), header layout, ticker toggle
- **Navigation** tab: WordPress-style hierarchical menu builder — set a **Parent** item to turn any entry into a drop-down sub-menu (unlimited depth), plus label / URL / order; add or remove items
- Categories are appended to the nav automatically after menu items (top level)
- Sub-menus render as hover dropdowns on desktop and expand inline on mobile

## Deployment Notes

- Enable HTTPS and uncomment the HTTP->HTTPS redirect in `.htaccess`
- Update the `Sitemap` URL in `robots.txt` for your domain
- Set a strong `APP_TIMEZONE` and database password
- For nginx, add a `location` that routes non-existent files to `index.php?route=$uri`
- Store uploads with web-server write permission only; keep the DB user least-privileged

## License

MIT - free to use and modify.
