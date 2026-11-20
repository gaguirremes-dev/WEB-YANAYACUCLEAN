/* Claims book form behaviour (externalized so a strict CSP without inline scripts can be enforced). */
function toggleApoderado() {
    const cb = document.getElementById('menor_edad');
    const f  = document.getElementById('apoderado_fields');
    const n  = document.getElementById('apoderado_nombres');
    const d  = document.getElementById('apoderado_doc_nro');
    if (cb.checked) { f.classList.remove('hidden'); n.required=true; d.required=true; }
    else            { f.classList.add('hidden');    n.required=false; d.required=false; n.value=''; d.value=''; }
}
window.addEventListener('DOMContentLoaded', () => {
    if(document.getElementById('menor_edad')) toggleApoderado();
    // The server flags a failed submission on <body data-form-error>: the captcha token is single use.
    if (document.body.hasAttribute('data-form-error') && typeof hcaptcha !== 'undefined') { hcaptcha.reset(); }
});
function openNoticeModal()  { const m=document.getElementById('notice-modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeNoticeModal() { const m=document.getElementById('notice-modal'); m.classList.add('hidden'); m.classList.remove('flex'); }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeNoticeModal(); });

// Event listeners (no inline handlers, so a strict CSP can be enforced)
(function () {
    const on = (id, ev, fn) => { const el = document.getElementById(id); if (el) el.addEventListener(ev, fn); };
    on('btn-print', 'click', () => window.print());
    on('menor_edad', 'click', toggleApoderado);
    on('btn-open-notice', 'click', openNoticeModal);
    on('btn-close-notice', 'click', closeNoticeModal);
    on('notice-backdrop', 'click', closeNoticeModal);
    // "submit" only fires after native validation passes: then block double submissions.
    const btn = document.getElementById('btn-submit');
    if (btn && btn.form) btn.form.addEventListener('submit', () => { btn.disabled = true; });
})();
