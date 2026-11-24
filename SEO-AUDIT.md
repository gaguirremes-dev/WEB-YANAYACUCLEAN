# SEO Audit — yanayacuclean.com

Last updated: 2026-11-20 · Target: Peru (es-PE) · Site: static HTML + PHP claims form.
Status legend: ✅ done · ⚠️ improve · ❌ failing · 👤 manual (needs your accounts)

## 1. Summary

The site was crawlable and not blocked (robots.txt and meta robots were correct). Real problems were a missing `og-image.jpg`, no structured data, multi-MB product SVGs in the hero (LCP), Tailwind loaded from a CDN, a soft-404 setup, a sitemap listing `noindex` pages, and no analytics. All code-level items are fixed. Remaining work is account-level (Analytics, Search Console, Business Profile) and city landing pages that need real local content.

## 2. Status by phase

| Phase | Item | Status | Evidence | Action | Priority |
|---|---|---|---|---|---|
| F1 | GA4 tag | ❌ | No `gtag`/GTM in `index.html` | 👤 Provide `G-XXXX`; add tag + key events (WhatsApp click, claims form submit) | P2 |
| F1 | Privacy text mentions analytics | ⚠️ | `privacidad.html` | Update after GA4 is added | P3 |
| F2 | Search Console verification | ✅ | `google-site-verification` meta in `<head>` | — | — |
| F2 | Sitemap submitted | 👤 | Sitemap rewritten 2026-11-20 | Resubmit in Search Console > Sitemaps | P1 |
| F2 | Request indexing of home | 👤 | — | URL Inspection > Request indexing | P2 |
| F2 | Google Business Profile | 👤 | Not verifiable from code | NAP must match site and JSON-LD; add Lima and Chiclayo service model | P1 |
| F3 | robots.txt | ✅ | `Allow: /` + sitemap declared | — | — |
| F3 | Sitemap contents | ✅ | Home + 3 landing pages; `noindex` pages removed; fresh `lastmod` | — | — |
| F3 | Accidental noindex | ✅ | Home is `index, follow`; legal pages and claims form are `noindex, follow` on purpose | — | — |
| F3 | Redirects | ✅/👤 | `.htaccess`: HTTP→HTTPS + non-www→www in one hop | Verify live with `curl -sIL` | P2 |
| F3 | Real 404 | ✅/👤 | `404.html` + `ErrorDocument 404 /404.html` | Verify live status is 404 | P2 |
| F4 | `<title>` | ✅ | "YanaYacu Clean \| Productos de Limpieza y Desinfectantes en Perú" (≈62 chars) | — | — |
| F4 | Meta description | ✅ | ≈150 chars, product + benefit + coverage | — | — |
| F4 | Single H1 + hierarchy | ✅ | One H1, logical H2/H3 | — | — |
| F4 | Open Graph / Twitter | ✅ | Complete; `assets/img/og-image.jpg` 1200×630 created | Test with a share debugger | P2 |
| F4 | Hero copy carries keywords | ✅ | Subtitle now names detergents, softeners, disinfectants | — | — |
| F5 | Keyword research with real data | 👤 | See section 4 (hypotheses only) | Validate with Search Console queries after 4 weeks | P2 |
| F5 | Dedicated landing pages | ✅ | `/linea-hogar/`, `/linea-industrial/`, `/distribuidores/` built from `src/` (shared layout), in sitemap, linked from home | Lima and Chiclayo pages pending (no real per-city content yet) | P2 |
| F6 | Product images | ✅ | Hero/orbital SVGs (3–6 MB) → WebP (13–252 KB); all site images WebP except `og-image.jpg` | — | — |
| F6 | Tailwind | ✅ | CDN removed; compiled `assets/css/tailwind.css` (29 KB) | Re-run `npm run build:css` after adding classes | — |
| F6 | JS loading | ✅ | GSAP, Swiper, tsParticles, Lucide, orbital.js all `defer` | — | — |
| F6 | Image dimensions (CLS) | ✅ | Logo, hero slides, ISO have width/height. Orbital cards use CSS `aspect-ratio` | — | — |
| F6 | Structured data | ✅ | JSON-LD: Organization, LocalBusiness (Chiclayo + Lima), WebSite; valid JSON | Validate in Rich Results Test | P2 |
| F6 | Product / FAQ schema | ⚠️ | Not added on purpose | Product needs offers/reviews to be eligible; FAQ needs real FAQs | P3 |
| F6 | Core Web Vitals | 👤 | Needs live field data | Run PageSpeed Insights on the published URL (target LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1) | P1 |
| F6 | Remaining render-blocking | ⚠️ | Google Fonts stylesheet; large `custom.css` | Self-host Poppins (woff2) if LCP is not green | P3 |
| F7 | AI search (Google) | ✅ | No special markup needed; crawlable, indexable, original content | Keep business data current in the Business Profile | — |

## 3. Changes applied to code

- `index.html`: title, description, OG/Twitter, JSON-LD, hero subtitle, `defer` scripts, preconnect, image attributes, lazy loading, Tailwind link.
- `privacidad.html`, `terminos.html`: canonical + OG, `noindex`, Tailwind link.
- `libro-de-reclamaciones.php`: `noindex`, Tailwind link, WebP assets.
- `sitemap.xml`, `robots.txt` (unchanged, verified), `.htaccess`, `404.html`.
- Assets reorganized to kebab-case ASCII names under `assets/img/{brand,productos,certificaciones,reclamaciones}`; PDF under `assets/docs/`.
- `src/layout/`, `src/pages/`, `scripts/build-pages.js`, generated `linea-hogar/`, `linea-industrial/`, `distribuidores/`, `assets/js/nav-scroll.js`; home and legal pages link to the new pages.
- `tailwind.config.js`, `package.json`, `src/tailwind.input.css`, `README.md`.

## 4. Keyword map (hypotheses — validate with Search Console data)

| Page | Primary keyword | Intent | Status |
|---|---|---|---|
| `/` | productos de limpieza Perú | Commercial | Exists, improved |
| `/` | detergente líquido para bebé | Commercial | Exists (section), no own page |
| `/` | suavizante para ropa de bebé | Commercial | Exists (section), no own page |
| `/` | desinfectante libre de cloro | Commercial | Exists (section), no own page |
| `/` | desinfectante aroma lavanda / floral / manzana | Transactional | Exists (section) |
| `/` | productos de limpieza para mascotas | Informational/Commercial | Exists (section) |
| `/` | distribuidor de productos de limpieza Chiclayo | Transactional | Exists (section) |
| `/linea-industrial/` | productos de limpieza industrial Perú | Commercial | Done |
| `/distribuidores/` | distribuidores de productos de limpieza Perú | Transactional | Done (Peru-wide; Lima page pending) |
| `/linea-hogar/` | productos de limpieza para el hogar Perú | Commercial | Done |
| New | productos de limpieza Chiclayo | Local | Pending: needs real local content |

## 5. Landing pages

Done: `/linea-hogar/`, `/linea-industrial/`, `/distribuidores/`. Each has a unique title and description, canonical, Open Graph/Twitter tags, one H1, breadcrumb (UI + JSON-LD), CollectionPage/WebPage + FAQPage JSON-LD (FAQ text comes only from facts already on the home page), and internal links to the other lines and the home. Navbar and footer live in `src/layout/` (see README, "Landing pages").

Pending: Lima and Chiclayo city pages. They are not created because there is no real per-city content (stock, delivery, local contact) beyond the two addresses already in the footer; thin duplicate pages would hurt more than help. Create them when that content exists.

## 6. Manual checklist (👤)

1. Search Console > Sitemaps > submit `https://www.yanayacuclean.com/sitemap.xml`.
2. URL Inspection > `https://www.yanayacuclean.com/` > Request indexing.
3. GA4: send the `G-XXXX` ID so the tag and events can be added; then Admin > Product links > Search Console links.
4. Business Profile: one profile per location, identical name/address/phone to the site and JSON-LD, correct service model, photos, link to the site.
5. PageSpeed Insights on the live URL; record LCP/INP/CLS.
6. Live checks: `curl -sIL http://yanayacuclean.com` ends in one 301 to `https://www.yanayacuclean.com/`; a missing URL returns 404.
7. Share the URL in WhatsApp to confirm the preview uses `og-image.jpg`.

## 7. What to measure and when

Review Search Console (Pages, Performance, Core Web Vitals) every 2–4 weeks after submission. Indexing takes days to weeks and is not guaranteed; no ranking or timeline is promised.


## 2026 visual refresh

Product photos, posts and videos were replaced (see README, "Product blocks"). `assets/img/og-image.jpg` was rebuilt with the new product photos. New lavavajillas (Limon) added to the product list and the ItemList of `/linea-hogar/`.
