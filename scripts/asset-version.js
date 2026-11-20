'use strict';
/**
 * Cache busting: appends ?v=<8-char content hash> to local css/js references
 * (assets/css, assets/js, assets/vendor) so the long Expires TTLs set in .htaccess are safe.
 *
 * Library: stamp(html) -> html   (used by build-pages.js for generated pages)
 * CLI:     node scripts/asset-version.js   stamps every page (hand-written + generated) in place.
 *          Runs last in `npm run build` because tailwind.css changes on every CSS build.
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const ROOT = path.resolve(__dirname, '..');
const REF = /((?:href|src)=")(\/?assets\/(?:css|js|vendor)\/[^"?#]+\.(?:css|js))(?:\?v=[0-9a-f]+)?(")/g;

function hashOf(rel) {
  const file = path.join(ROOT, rel.replace(/^\//, ''));
  if (!fs.existsSync(file)) return null;
  return crypto.createHash('md5').update(fs.readFileSync(file)).digest('hex').slice(0, 8);
}

function stamp(html) {
  return html.replace(REF, (m, pre, rel, post) => {
    const h = hashOf(rel);
    return h ? `${pre}${rel}?v=${h}${post}` : m;
  });
}

function pages() {
  const out = fs.readdirSync(ROOT).filter((f) => /\.(html|php)$/.test(f)).map((f) => path.join(ROOT, f));
  for (const d of fs.readdirSync(ROOT, { withFileTypes: true })) {
    if (!d.isDirectory() || ['node_modules', 'src', 'scripts', 'assets', 'lib', 'reclamaciones'].includes(d.name) || d.name.startsWith('.')) continue;
    const f = path.join(ROOT, d.name, 'index.html');
    if (fs.existsSync(f)) out.push(f);
  }
  return out;
}

if (require.main === module) {
  let changed = 0;
  for (const f of pages()) {
    const before = fs.readFileSync(f, 'utf8');
    const after = stamp(before);
    if (after !== before) { fs.writeFileSync(f, after); changed++; }
  }
  console.log(`asset-version: ${changed} page(s) updated`);
}

module.exports = { stamp };
