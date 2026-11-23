# YanaYacu Clean website

Static marketing site. PHP is only needed for the claims form (`libro-de-reclamaciones.php`) and the distributor form endpoint (`contacto-distribuidores.php`).

## Structure

```
index.html, privacidad.html, terminos.html, 404.html   hand-written pages
linea-hogar/, linea-industrial/, distribuidores/        GENERATED landing pages (do not edit; see below)
src/layout/                                             shared head, header, footer (single source of nav/footer)
src/pages/                                              landing page sources (front matter + body)
scripts/build-pages.js                                  assembles src/pages/* into <slug>/index.html
libro-de-reclamaciones.php                              claims form (PHP, SMTP)
contacto-distribuidores.php                             first-party endpoint for the home distributor form (PHP, SMTP, hCaptcha)
lib/storage.php, lib/mailer.php                         data-dir resolution/migration and SMTP sender (blocked by .htaccess)
config.smtp.example.php                                 copy to config.smtp.php (gitignored) and fill in
favicon.svg, robots.txt, sitemap.xml, .htaccess         site-level files
lib/fpdf.php + lib/font/                               FPDF 1.86 (PDF receipts for the claims form)
assets/css, assets/js                                   styles and scripts
assets/vendor/                                          self-hosted libs (alpinejs 3.14.1, gsap 3.12.5, swiper 11.1.4, motion = framer-motion vanilla bundle, see build:motion)
assets/fonts/                                           self-hosted Poppins woff2 (latin + latin-ext), @font-face in assets/css/fonts.css
scripts/asset-version.js                                stamps ?v=<hash> on local css/js references
assets/docs/                                            downloadable catalog PDF
assets/img/brand/                                       logos
assets/img/certificaciones/                             ISO 14001 badge
assets/img/productos/                                   product images (kebab-case ASCII names)
assets/img/hero/                                        hero splash background (1100/1920 WebP) and slides/ (product images trimmed to their alpha bounding box)
assets/img/reclamaciones/                               claims book card and INDECOPI notice
assets/img/originales/                                  local-only sources (gitignored)
```

## Hero (home)

- Markup: `section#inicio.yx-hero` in `index.html` (styles scoped as `.yx-*` in `assets/css/custom.css`). Layers: splash photo (`.yx-hero__bg`), white wash, copy + product stage (slideshow of 8 bottles, `object-fit: contain`, common baseline), SVG waves that merge into the navy marquee.
- Scripts: `assets/js/hero-boot.js` (blocking, adds `yx-js` before first paint; a CSS failsafe reveals the copy after 3 s), then `assets/vendor/motion.min.js` and `assets/js/hero.js` (deferred, first in order; no GSAP/Swiper dependency). Entrance, slideshow transitions, idle float, parallax and button springs use Motion; `prefers-reduced-motion` gives a static hero.
- `npm run build:motion` re-bundles only `animate` (motion/mini), `hover` and `press` from the `motion` package into `assets/vendor/motion.min.js` (esbuild, ~16 KB / ~6.6 KB gzip). Commit the output.
- Removed from the hero: tsParticles, `hero-slideshow.js`, the ISO block and the benefits strip (the ISO image and the footer seal remain).

## Run

- Static pages: open `index.html` or serve the folder, e.g. `python -m http.server 8000`.
- Claims form: `php -S localhost:8000` from the project root (needs `config.smtp.php`).
- Production: Apache with `.htaccess` (HTTPS + www redirect, caching, custom 404).

## Conventions

Asset file names: lowercase ASCII kebab-case, no spaces or accents.

## Styles (Tailwind build)

Tailwind is compiled locally; the CDN is no longer used.

```bash
npm install
npm run build       # build:pages + build:css (use this by default)
npm run build:css   # writes assets/css/tailwind.css (minified)
npm run watch:css   # rebuild on change while editing
```

Re-run `npm run build` after adding new Tailwind classes to any `.html`/`.php`/`assets/js`/`src` file.
Config: `tailwind.config.js` (palette: navy, brand, sky). Input: `src/tailwind.input.css`.
`assets/css/tailwind.css` is committed so the server needs no Node.

## Landing pages (shared layout)

`/linea-hogar/`, `/linea-industrial/` and `/distribuidores/` are built from `src/` into plain static files (no runtime includes, no Node on the server). Generated `<slug>/index.html` files are committed.

- Edit the navbar/footer once in `src/layout/header.html` and `src/layout/footer.html`, the document head in `src/layout/head.html`.
- **Navbar** (single source: `src/layout/header.html`): `Productos` (dropdown: Línea hogar, Línea industrial, Catálogo de productos), `Distribuidores`, `Legal` (dropdown: Legal y normativa, Política de privacidad, Términos y condiciones, Libro de reclamaciones), `Contacto` (`/#contacto`, the footer) + WhatsApp CTA. Intranet is footer-only. On mobile the same links render as an accordion. `npm run build` injects the header into the generated pages AND into `index.html` between its `<!-- nav:start -->` / `<!-- nav:end -->` markers (never edit between them). Behavior: `navbar` / `navDropdown` in `assets/js/alpine-components.js`; styles: `.yx-nav*` in the standalone `assets/css/nav.css` (no dependency on `custom.css`, so it can load on the claims form without restyling it). The header is also injected into `404.html`, `privacidad.html`, `terminos.html` and `libro-de-reclamaciones.php` (list `NAV_TARGETS` in `scripts/build-pages.js`; each file needs its own `nav:start`/`nav:end` markers, the build fails if they are missing). Every page that renders the header must load `tailwind.css` + `nav.css` and, deferred and in this order, `alpine-components.js`, `alpine.min.js`, `nav-scroll.js`; the fixed bar is ~96px tall (86px on mobile), so the first block needs `pt-36`/`pt-40`. `/404.html` uses root-relative paths because it is served from any depth.
- Edit page content in `src/pages/<slug>.html`, then run `npm run build`. Never edit the generated `<slug>/index.html`.
- To add a page: create `src/pages/<new-slug>.html`, add its link to the layout and `sitemap.xml`, run `npm run build`.

Page file format: a front matter block between two `---` lines, then the page body.

```
---
title: Primary keyword first | YanaYacu Clean      (max 62 chars)
description: 120-160 chars
og_title: ...
og_description: ...
schema_type: CollectionPage                          (or WebPage)
schema_name: ...
breadcrumb: Label of the last breadcrumb item
itemlist:                                            (optional, ItemList in JSON-LD)
  - Item name
faq:                                                 (optional)
  - Question | Answer
---
<main> ... {{faq}} ... </main>
```

The canonical URL is `https://www.yanayacuclean.com/<slug>/`. The build generates the JSON-LD (BreadcrumbList, CollectionPage/WebPage and, if `faq` exists, FAQPage), renders the visible FAQ at `{{faq}}` from the same data so both always match, and fails on a missing field, a title over 62 chars, a description outside 120-160 chars or duplicate titles/descriptions. Other tokens: `{{icon:whatsapp}}`, `{{aria:<slug>}}` (navbar `aria-current="page"`), `{{current:<slug>|<slug>}}` (dropdown parent `aria-current="true"`), `{{ico:<name>}}` (navbar icons).

## Cache busting

`.htaccess` caches css/js for 1 month, so local css/js references carry `?v=<8-char content hash>`.
`npm run build` stamps them last (`scripts/asset-version.js`, also used by `build-pages.js`) in `index.html`,
`privacidad.html`, `terminos.html`, `404.html`, `libro-de-reclamaciones.php` and the generated landing pages.
Run `npm run build` after changing any file in `assets/css`, `assets/js` or `assets/vendor`.

## Security headers / CSP

`.htaccess` sends HSTS, Permissions-Policy, COOP and an **enforcing** `Content-Security-Policy`:
`script-src 'self'` + hCaptcha, no `'unsafe-eval'`, no inline scripts, `img-src 'self' data:`, `form-action 'self'`.

- Alpine runs as the CSP build (`@alpinejs/csp` 3.14.1, file `assets/vendor/alpine.min.js`). Markup must not contain
  inline expressions: register components in `assets/js/alpine-components.js` (`Alpine.data(...)`) and reference them by
  name (`x-data="navbar"`, `@click="toggle"`). That file must load before `alpine.min.js`.
- All scripts are files under `assets/`. JSON-LD (`application/ld+json`) is data and is not blocked.
- `style-src` keeps `'unsafe-inline'` on purpose (`style=""` attributes and Swiper/GSAP/tsParticles runtime styles).
- Vendor note: the `swiper-icons` data-URI `@font-face` was removed from `swiper-bundle.min.css` (no navigation arrows are used and `font-src` is `'self'`). Re-apply when upgrading Swiper, or arrows will break.
- Re-check violations (headless Chrome against a server that sends the header) after adding any new external origin.

## Claims book data (outside the web root)

`libro-de-reclamaciones.php` and `contacto-distribuidores.php` store `records.json`, PDFs (random suffix), `install.key`,
rate-limit state and `security.log` in a data directory, resolved by `lib/storage.php`:

1. `RECLAMOS_DATA_DIR` from `config.smtp.php` (absolute path), if set and writable;
2. else `<parent of the web root>/reclamaciones-data` if the parent is writable (outside the web root);
3. else the legacy `<web root>/reclamaciones/` (protected by its own `.htaccess`).

On the first request after the directory changes, existing files in the legacy `reclamaciones/` folder are **copied once**
(never moved or deleted; `install.key` and the counter come along, so IDs and HMACs stay valid). After verifying the new
location in production, the owner can archive/delete the old folder manually. Only IP **hashes** (salted SHA-256) are stored.

## Anti-spam limits

Defaults in code: **3 submissions per IP per 60 minutes** (`RATE_LIMIT_MAX` / `RATE_LIMIT_WINDOW`), 5 per IP per day, site-wide caps.
`config.smtp.php` takes priority over the code defaults: **the owner must update the real `config.smtp.php`** if it still
defines `RATE_LIMIT_MAX=1` / `RATE_LIMIT_WINDOW=1800` (the old template values). The distributor form uses its own
`DIST_RATE_LIMIT_*` / `DIST_SITE_*` constants (default 3 per 60 min per IP) and `DIST_NOTIF_EMAIL` (default `comercial@yanayacuclean.com`).
When the limit is hit the form tells the user to write to `reclamaciones@yanayacuclean.com`.

## Cloudflare IP list (maintenance)

The PHP files embed a static copy of Cloudflare's published ranges (https://www.cloudflare.com/ips-v4 and /ips-v6,
retrieved 2026-11-20) to trust `CF-Connecting-IP` only from Cloudflare. There are no runtime network calls.
**Review every 6 months** and update `RECLAMOS_CF_RANGES` (libro-de-reclamaciones.php) and `DIST_CF_RANGES` (contacto-distribuidores.php).

## Manual steps

- `assets/docs/catalogo-yanayacu-clean.pdf` is about 3 MB (only loaded on click). Lossless optimization gains <1%; lossy image recompression reaches ~2.6 MB at visible quality cost, so it was kept as is. A real reduction needs the original design source exported at lower image resolution.
- Vendor upgrades: `npm pack` the new version and copy the dist file into `assets/vendor/`, then `npm run build`.
