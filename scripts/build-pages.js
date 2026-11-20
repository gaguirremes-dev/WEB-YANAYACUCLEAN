#!/usr/bin/env node
/**
 * Static page builder (Node built-ins only).
 *
 * Input : src/pages/<slug>.html   page body + front matter (see README.md, "Landing pages")
 *         src/layout/head.html    <!DOCTYPE> ... <body> opening
 *         src/layout/header.html  navbar
 *         src/layout/footer.html  footer, floating WhatsApp, scripts, </body></html>
 * Output: <slug>/index.html       committed; the server needs no Node.
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
 *         {{aria:<slug>}} -> ` aria-current="page"` when building that slug, else empty
 *         {{icon:whatsapp}} -> inline WhatsApp SVG
 *         {{faq}} -> FAQ section markup (page body only)
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { stamp } = require('./asset-version');

const ROOT = path.resolve(__dirname, '..');
const LAYOUT = path.join(ROOT, 'src', 'layout');
const PAGES = path.join(ROOT, 'src', 'pages');
const ORIGIN = 'https://www.yanayacuclean.com';
const MAX_TITLE = 62;
const DESC_RANGE = [120, 160];

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
        <details class="group border-b border-gray-200 py-5">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-bold text-navy">
            <h3 class="text-base sm:text-lg font-bold">${esc(q)}</h3>
            <span class="shrink-0 text-brand transition-transform group-open:rotate-45 text-2xl leading-none" aria-hidden="true">+</span>
          </summary>
          <p class="mt-3 text-gray-500 leading-relaxed">${esc(a)}</p>
        </details>`).join('');
  return `<section class="py-20 bg-white" aria-labelledby="faq-title">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
      <h2 id="faq-title" class="text-3xl font-black text-navy mb-8">Preguntas frecuentes</h2>${rows}
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

    const html = [layout.head, layout.header, body.replace('{{faq}}', renderFaq(faq)), layout.footer]
      .join('\n')
      .replace(/\{\{aria:([a-z0-9-]+)\}\}/g, (_, s) => (s === slug ? 'aria-current="page"' : ''))
      .replace(/\{\{icon:whatsapp\}\}/g, whatsapp)
      .replace(/\{\{(title|description|canonical|og_title|og_description|jsonld)\}\}/g, (_, k) => values[k]);

    const leftover = html.match(/\{\{[^}]+\}\}/);
    if (leftover) throw new Error(`${slug}: unresolved token ${leftover[0]}`);

    const outDir = path.join(ROOT, slug);
    fs.mkdirSync(outDir, { recursive: true });
    fs.writeFileSync(path.join(outDir, 'index.html'), stamp(html));
    console.log(`built /${slug}/index.html (title ${data.title.length} chars, description ${data.description.length} chars)`);
  }
}

try {
  main();
} catch (err) {
  console.error(`build-pages: ${err.message}`);
  process.exit(1);
}
