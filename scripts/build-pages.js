#!/usr/bin/env node
/**
 * Static page builder (Node built-ins only).
 *
 * Input : src/pages/<slug>.html   page body + front matter (see README.md, "Landing pages")
 *         src/layout/head.html    <!DOCTYPE> ... <body> opening
 *         src/layout/header.html  navbar
 *         src/layout/footer.html  footer, floating WhatsApp, scripts, </body></html>
 * Output: <slug>/index.html       committed; the server needs no Node.
 *         index.html              ONLY the block between <!-- nav:start --> and <!-- nav:end -->
 *                                 is replaced with the shared header (no active page).
 *
 * Front matter: a block between two `---` lines at the top of the page file.
 *   key: value            single-line field
 *   key:                  followed by indented list items
 *     - item
 * Fields:
 *   title, description, og_title, og_description, schema_type (WebPage | CollectionPage),
 *   schema_name, breadcrumb (last breadcrumb label)  -> required
 *   itemlist:  "- Name"            optional, becomes an ItemList (mainEntity)
 *   faq:       "- Question | Answer" optional, rendered at {{faq}} AND emitted as FAQPage JSON-LD
 * The canonical URL is derived from the file name: /<slug>/.
 *
 * Tokens: {{title}} {{description}} {{canonical}} {{og_title}} {{og_description}} {{jsonld}}
 *         {{aria:<slug>}} -> `aria-current="page"` when building that slug, else empty
 *         {{current:<slug>|<slug>}} -> `aria-current="true"` when building any of those slugs (dropdown parents)
 *         {{ico:<name>}} -> small inline nav icon (see NAV_ICONS)
 *         {{icon:whatsapp}} -> inline WhatsApp SVG
 *         {{faq}} -> FAQ section markup (page body only)
 *         {{gen:products}} {{gen:spec-table}} {{gen:videos}} -> data-driven blocks from src/data/products.json
 *           (scripts/render-products.js). In index.html the same blocks live between
 *           <!-- gen:NAME:start --> / <!-- gen:NAME:end --> markers and are regenerated on every build.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { stamp } = require('./asset-version');
const gen = require('./render-products');

const ROOT = path.resolve(__dirname, '..');
const LAYOUT = path.join(ROOT, 'src', 'layout');
const PAGES = path.join(ROOT, 'src', 'pages');
const ORIGIN = 'https://www.yanayacuclean.com';
const MAX_TITLE = 62;
const DESC_RANGE = [120, 160];

const NAV_START = '<!-- nav:start -->';
const NAV_END = '<!-- nav:end -->';
const svg = (inner, cls) =>
  `<svg class="${cls}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${inner}</svg>`;
const NAV_ICONS = {
  chev: svg('<path d="m6 9 6 6 6-6"/>', 'yx-nav__chev'),
  home: svg('<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/>', 'yx-nav__iico'),
  factory: svg('<path d="M3 21h18"/><path d="M5 21V10l5 3v-3l5 3V4h4v17"/>', 'yx-nav__iico'),
  grid: svg('<rect x="4" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.5"/>', 'yx-nav__iico'),
  shield: svg('<path d="M12 3l7 3v5.5c0 4.2-2.9 7.6-7 9.5-4.1-1.9-7-5.3-7-9.5V6z"/><path d="m9 12 2 2 4-4"/>', 'yx-nav__iico'),
  lock: svg('<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>', 'yx-nav__iico'),
  file: svg('<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/>', 'yx-nav__iico'),
  book: svg('<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h11"/>', 'yx-nav__iico'),
};

/** Resolves the header tokens for one page (slug = null for the home page). */
function renderHeader(header, slug, whatsapp) {
  return header
    .replace(/\{\{aria:([a-z0-9-]+)\}\}/g, (_, s) => (s === slug ? 'aria-current="page"' : ''))
    .replace(/\{\{current:([a-z0-9|-]+)\}\}/g, (_, list) => (list.split('|').includes(slug) ? 'aria-current="true"' : ''))
    .replace(/\{\{ico:([a-z]+)\}\}/g, (_, n) => {
      if (!NAV_ICONS[n]) throw new Error(`unknown nav icon "${n}"`);
      return NAV_ICONS[n];
    })
    .replace(/\{\{icon:whatsapp\}\}/g, whatsapp);
}

/**
 * Standalone pages that are not generated from src/pages: the shared header is injected between
 * their nav:start / nav:end markers. `slug` drives {{aria:..}} / {{current:..}} (null = no active page).
 */
const NAV_TARGETS = [
  { file: 'index.html', slug: null },
  { file: '404.html', slug: null },
  { file: 'privacidad.html', slug: 'privacidad' },
  { file: 'terminos.html', slug: 'terminos' },
  { file: 'libro-de-reclamaciones.php', slug: 'libro-de-reclamaciones' },
];

/** Replaces the nav block of one standalone page (between the markers) with the shared header. */
function injectNav(header, whatsapp, { file: name, slug }) {
  const file = path.join(ROOT, name);
  const html = fs.readFileSync(file, 'utf8');
  const a = html.indexOf(NAV_START);
  const b = html.indexOf(NAV_END);
  if (a < 0 || b < a) throw new Error(`${name}: nav:start / nav:end markers not found`);
  const eol = html.includes('\r\n') ? '\r\n' : '\n'; // keep each file own line endings (idempotent)
  const nav = renderHeader(header, slug, whatsapp).trim().replace(/\r?\n/g, () => eol);
  const leftover = nav.match(/\{\{[^}]+\}\}/);
  if (leftover) throw new Error(`${name} nav: unresolved token ${leftover[0]}`);
  const out = `${html.slice(0, a + NAV_START.length)}${eol}${nav}${eol}${html.slice(b)}`;
  if (out !== html) fs.writeFileSync(file, out);
  console.log(`injected nav into ${name} (${out !== html ? 'updated' : 'unchanged'})`);
}

/** Regenerates the data-driven blocks of index.html between its gen:NAME:start / gen:NAME:end markers. */
function injectGenerated(name) {
  const file = path.join(ROOT, name);
  let html = fs.readFileSync(file, 'utf8');
  const eol = html.includes('\r\n') ? '\r\n' : '\n';
  for (const block of ['products', 'spec-table', 'videos']) {
    const start = `<!-- gen:${block}:start -->`;
    const end = `<!-- gen:${block}:end -->`;
    const a = html.indexOf(start);
    const b = html.indexOf(end);
    if (a < 0 || b < a) throw new Error(`${name}: ${start} / ${end} markers not found`);
    const body = gen[block]().replace(/\r?\n/g, eol);
    html = `${html.slice(0, a + start.length)}${eol}${body}${eol}${html.slice(b)}`;
  }
  fs.writeFileSync(file, html);
  console.log(`generated product blocks into ${name}`);
}

const read = (p) => fs.readFileSync(p, 'utf8');
const esc = (s) =>
  String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

function parseFrontMatter(raw, file) {
  const m = raw.replace(/\r\n/g, '\n').match(/^---\n([\s\S]*?)\n---\n([\s\S]*)$/);
  if (!m) throw new Error(`${file}: missing front matter block`);
  const data = {};
  let listKey = null;
  for (const line of m[1].split('\n')) {
    if (!line.trim()) continue;
    const item = line.match(/^\s+-\s+(.*)$/);
    if (item && listKey) { data[listKey].push(item[1].trim()); continue; }
    const kv = line.match(/^([a-z_]+):\s*(.*)$/);
    if (!kv) throw new Error(`${file}: cannot parse front matter line "${line}"`);
    if (kv[2] === '') { listKey = kv[1]; data[listKey] = []; }
    else { listKey = null; data[kv[1]] = kv[2].trim(); }
  }
  return { data, body: m[2] };
}

function validate(slug, d) {
  for (const k of ['title', 'description', 'og_title', 'og_description', 'schema_type', 'schema_name', 'breadcrumb']) {
    if (!d[k]) throw new Error(`${slug}: missing front matter field "${k}"`);
  }
  if (d.title.length > MAX_TITLE) throw new Error(`${slug}: title is ${d.title.length} chars (max ${MAX_TITLE})`);
  const n = d.description.length;
  if (n < DESC_RANGE[0] || n > DESC_RANGE[1]) {
    throw new Error(`${slug}: description is ${n} chars (expected ${DESC_RANGE.join('-')})`);
  }
  if (!['WebPage', 'CollectionPage'].includes(d.schema_type)) {
    throw new Error(`${slug}: schema_type must be WebPage or CollectionPage`);
  }
}

function parseFaq(slug, items = []) {
  return items.map((line) => {
    const i = line.indexOf(' | ');
    if (i < 0) throw new Error(`${slug}: faq item needs "Question | Answer": ${line}`);
    return { q: line.slice(0, i).trim(), a: line.slice(i + 3).trim() };
  });
}

function renderFaq(faq) {
  if (!faq.length) return '';
  const rows = faq.map(({ q, a }) => `
        <details>
          <summary>
            <h3>${esc(q)}</h3>
            <span aria-hidden="true">+</span>
          </summary>
          <p>${esc(a)}</p>
        </details>`).join('');
  return `<section class="yxs yxs-faq" aria-labelledby="faq-title">
    <div class="yxs__in yxs__in--narrow">
      <div class="yxs-head">
        <span class="yxs-eyebrow">Ayuda</span>
        <h2 class="yxs-h2 yxs-h2--sm" id="faq-title">Preguntas <span class="yxs-accent">frecuentes</span></h2>
      </div>${rows}
    </div>
  </section>`;
}

function buildJsonLd(slug, d, faq) {
  const url = `${ORIGIN}/${slug}/`;
  const graph = [
    {
      '@type': 'BreadcrumbList',
      '@id': `${url}#breadcrumb`,
      itemListElement: [
        { '@type': 'ListItem', position: 1, name: 'Inicio', item: `${ORIGIN}/` },
        { '@type': 'ListItem', position: 2, name: d.breadcrumb, item: url },
      ],
    },
    {
      '@type': d.schema_type,
      '@id': `${url}#webpage`,
      url,
      name: d.schema_name,
      description: d.description,
      inLanguage: 'es-PE',
      isPartOf: { '@id': `${ORIGIN}/#website` },
      about: { '@id': `${ORIGIN}/#organization` },
      publisher: { '@id': `${ORIGIN}/#organization` },
      breadcrumb: { '@id': `${url}#breadcrumb` },
    },
  ];
  if (d.itemlist && d.itemlist.length) {
    graph[1].mainEntity = {
      '@type': 'ItemList',
      itemListElement: d.itemlist.map((name, i) => ({ '@type': 'ListItem', position: i + 1, name })),
    };
  }
  if (faq.length) {
    graph.push({
      '@type': 'FAQPage',
      '@id': `${url}#faq`,
      mainEntity: faq.map(({ q, a }) => ({
        '@type': 'Question',
        name: q,
        acceptedAnswer: { '@type': 'Answer', text: a },
      })),
    });
  }
  // Escape "<" so the JSON can never close the <script> element.
  return JSON.stringify({ '@context': 'https://schema.org', '@graph': graph }, null, 2).replace(/</g, '\\u003c');
}

function main() {
  const layout = {
    head: read(path.join(LAYOUT, 'head.html')),
    header: read(path.join(LAYOUT, 'header.html')),
    footer: read(path.join(LAYOUT, 'footer.html')),
  };
  const whatsapp = read(path.join(LAYOUT, 'whatsapp-icon.svg')).trim();
  const files = fs.readdirSync(PAGES).filter((f) => f.endsWith('.html')).sort();
  if (!files.length) throw new Error('No pages found in src/pages');

  const titles = new Set();
  const descriptions = new Set();

  for (const file of files) {
    const slug = path.basename(file, '.html');
    const { data, body } = parseFrontMatter(read(path.join(PAGES, file)), file);
    validate(slug, data);
    if (titles.has(data.title)) throw new Error(`${slug}: duplicate title`);
    if (descriptions.has(data.description)) throw new Error(`${slug}: duplicate description`);
    titles.add(data.title);
    descriptions.add(data.description);

    const faq = parseFaq(slug, data.faq);
    const values = {
      title: esc(data.title),
      description: esc(data.description),
      canonical: `${ORIGIN}/${slug}/`,
      og_title: esc(data.og_title),
      og_description: esc(data.og_description),
      jsonld: buildJsonLd(slug, data, faq),
    };

    const html = [layout.head, renderHeader(layout.header, slug, whatsapp), body.replace('{{faq}}', renderFaq(faq)), layout.footer]
      .join('\n')
      .replace(/\{\{icon:whatsapp\}\}/g, whatsapp)
      .replace(/\{\{gen:([a-z-]+)\}\}/g, (_, n) => { if (!gen[n]) throw new Error(`${slug}: unknown block {{gen:${n}}}`); return gen[n](); })
      .replace(/\{\{(title|description|canonical|og_title|og_description|jsonld)\}\}/g, (_, k) => values[k]);

    const leftover = html.match(/\{\{[^}]+\}\}/);
    if (leftover) throw new Error(`${slug}: unresolved token ${leftover[0]}`);

    const outDir = path.join(ROOT, slug);
    fs.mkdirSync(outDir, { recursive: true });
    fs.writeFileSync(path.join(outDir, 'index.html'), stamp(html));
    console.log(`built /${slug}/index.html (title ${data.title.length} chars, description ${data.description.length} chars)`);
  }
  for (const target of NAV_TARGETS) injectNav(layout.header, whatsapp, target);
  injectGenerated('index.html');
}

try {
  main();
} catch (err) {
  console.error(`build-pages: ${err.message}`);
  process.exit(1);
}
