'use strict';
/**
 * Renders the data-driven blocks shared by the home page and the landing pages from
 * src/data/products.json: product explorer (photo selectors), technical table, video strip.
 * Output is plain HTML with root-relative asset paths. Behavior lives in assets/js/products.js.
 */
const fs = require('fs');
const path = require('path');

const DATA = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'src', 'data', 'products.json'), 'utf8'));
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const WA = 'https://wa.me/51974667498?text=';
const waLink = (name) => `${WA}${encodeURIComponent(`Hola, quiero información sobre ${name} YanaYacu Clean`)}`;
const badge = (text, tone) => `<span class="yxs-badge is-${tone}">${esc(text)}</span>`;
const thumbW = (v) => Math.round((v.w * 460) / v.h);

function tab(p, i) {
  const v = p.variants[0];
  const inner = p.photoOnly
    ? `<span class="yxp__thumb yxp__thumb--photo is-${p.tone}"><img src="/assets/img/posts/${p.post}-thumb.webp" alt="" width="360" height="640" loading="lazy" decoding="async" style="object-position:50% 62%" /></span>`
    : `<span class="yxp__thumb is-${p.tone}"><img src="/assets/img/productos/${v.img}-thumb.webp" alt="" width="${thumbW(v)}" height="460" loading="lazy" decoding="async" /></span>`;
  return `<button type="button" class="yxp__tab" data-target="${p.id}" aria-controls="px-${p.id}" aria-pressed="${i === 0}">${inner}<span class="yxp__tabname">${esc(p.short)}</span></button>`;
}

function panel(p, i, wa) {
  const multi = p.variants.length > 1;
  const bottles = p.photoOnly ? '' : `<div class="yxp__bottle">${p.variants.map((v, k) =>
    `<img src="/assets/img/productos/${v.img}.webp" width="${v.w}" height="${v.h}" alt="${esc(`${p.name} ${v.label} YanaYacu Clean`)}" data-size="${v.key}" data-variant-show="${v.key}" loading="lazy" decoding="async"${k ? ' hidden' : ''} />`).join('')}</div>`;
  const seg = multi ? `<div><span class="yxp__label" id="seg-${p.id}">Presentación</span><div class="yxp__seg" role="group" aria-labelledby="seg-${p.id}">${p.variants.map((v, k) =>
    `<button type="button" data-variant="${v.key}" aria-pressed="${k === 0}">${esc(v.label)}</button>`).join('')}</div></div>` : '';
  const dd = (field) => p.variants.map((v, k) => `<dd data-variant-show="${v.key}"${k ? ' hidden' : ''}>${esc(v[field])}</dd>`).join('');
  return `<div class="yxp__panel is-${p.tone}" id="px-${p.id}" data-panel="${p.id}" role="group" aria-label="${esc(p.name)}"${i ? ' hidden' : ''}>
      <div class="yxp__media${p.photoOnly ? ' yxp__media--solo' : ''}">
        <figure class="yxp__post"><img src="/assets/img/posts/${p.post}.webp" srcset="/assets/img/posts/${p.post}-thumb.webp 360w, /assets/img/posts/${p.post}.webp 720w" sizes="(min-width: 900px) 300px, 50vw" width="720" height="1280" alt="${esc(`Publicación ${p.name} YanaYacu Clean`)}" loading="lazy" decoding="async" /></figure>
        ${bottles}
      </div>
      <div class="yxp__info">
        <div class="yxp__badges">${badge(`Línea ${p.line}`, p.line === 'Bebé' ? 'bebe' : 'hogar')}${badge(`Aroma ${p.aroma}`, p.tone)}</div>
        <h3 class="yxp__name">${esc(p.name)}</h3>
        <p class="yxp__tagline">“${esc(p.tagline)}”</p>
        <p class="yxp__desc">${esc(p.desc)}</p>
        ${seg}
        <dl class="yxp__dl">
          <div><dt>Línea</dt><dd>${esc(p.line)}</dd></div>
          <div><dt>Aroma</dt><dd>${esc(p.aroma)}</dd></div>
          <div><dt>Contenido neto</dt>${dd('net')}</div>
          <div><dt>Presentación</dt>${dd('pres')}</div>
        </dl>
        <div class="yxs-cta-row">
          <a class="yxs-btn yxs-btn--primary" href="${waLink(p.name)}" target="_blank" rel="noopener">Consultar por WhatsApp</a>
          <a class="yxs-link" href="/assets/docs/catalogo-yanayacu-clean.pdf" download>Descargar catálogo</a>
        </div>
      </div>
    </div>`;
}

function renderProducts() {
  const ps = DATA.products;
  return `<div class="yxp" data-explorer>
    <div class="yxp__tabs" role="group" aria-label="Elige un producto">${ps.map(tab).join('')}</div>
    <div class="yxp__panels">
    ${ps.map((p, i) => panel(p, i)).join('\n    ')}
    </div>
  </div>`;
}

function renderSpecTable() {
  const rows = [];
  for (const p of DATA.products) {
    for (const v of p.variants) {
      rows.push(`<tr><th scope="row">${esc(p.name)}</th><td data-label="Línea">${badge(p.line, p.line === 'Bebé' ? 'bebe' : 'hogar')}</td><td data-label="Aroma">${badge(p.aroma, p.tone)}</td><td data-label="Presentación">${esc(v.pres)}</td><td data-label="Contenido neto"><strong>${esc(v.net)}</strong></td></tr>`);
    }
  }
  return `<div class="yxt-wrap" tabindex="0" role="region" aria-label="Tabla de presentaciones y contenido neto">
    <table class="yxt">
      <caption class="sr-only">Presentaciones de la línea YanaYacu Clean</caption>
      <thead><tr><th scope="col">Producto</th><th scope="col">Línea</th><th scope="col">Aroma</th><th scope="col">Presentación</th><th scope="col">Contenido neto</th></tr></thead>
      <tbody>
      ${rows.join('\n      ')}
      </tbody>
    </table>
  </div>`;
}

function renderVideos() {
  return `<div class="yxv">${DATA.videos.map((v) => `
    <figure class="yxv__item">
      <div class="yxv__frame" data-video>
        <video muted playsinline loop preload="metadata" poster="/assets/video/${v.id}-poster.webp" width="540" height="960" aria-label="${esc(`Video ${v.title} YanaYacu Clean`)}" tabindex="-1"><source src="/assets/video/${v.id}.mp4" type="video/mp4" /></video>
      </div>
      <figcaption class="yxv__cap">${esc(v.title)} ${badge(v.aroma, v.tone)}</figcaption>
    </figure>`).join('')}
  </div>`;
}

module.exports = { products: renderProducts, 'spec-table': renderSpecTable, videos: renderVideos, DATA };
