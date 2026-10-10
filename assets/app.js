/* OpenRode — front-end */
(function () {
  'use strict';
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const BASE = (document.querySelector('meta[name=base]') || {}).content || '';
  const apiUrl = (p) => BASE + '/' + String(p).replace(/^\//, '');

  async function api(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign({ 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' }, opts.headers || {});
    const res = await fetch(apiUrl(path), opts);
    const ct = res.headers.get('content-type') || '';
    const data = ct.includes('json') ? await res.json() : await res.text();
    if (!res.ok) throw new Error((data && data.error) || ('HTTP ' + res.status));
    return data;
  }

  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* Surface any uncaught JS error as a visible banner instead of a silently broken page. */
  let fatalShown = false;
  function fatal(msg) {
    if (fatalShown) return;
    fatalShown = true;
    try {
      const bar = document.createElement('div');
      bar.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:9999;background:#7f1d1d;color:#fff;padding:10px 14px;font:13px/1.5 ui-monospace,monospace;white-space:pre-wrap';
      bar.textContent = 'OpenRode hit a script error: ' + msg + '  —  reload the page; if it persists, clear the site cache.';
      document.body.appendChild(bar);
    } catch (e) {}
  }
  window.addEventListener('error', (e) => fatal(e.message || 'unknown error'));
  window.addEventListener('unhandledrejection', (e) => {
    const msg = (e.reason && e.reason.message) || String(e.reason);
    console.error('OpenRode unhandled rejection:', msg);
  });

  function toast(msg, kind) {
    const box = $('#toasts');
    if (!box) return;
    const el = document.createElement('div');
    el.className = 'toast ' + (kind || '');
    el.textContent = msg;
    box.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 320); }, 3400);
  }

  /* ================= theme ================= */
  const themeBtn = $('#theme-btn');
  function applyTheme(next) {
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('oc-theme', next); } catch (e) {}
    toast(next === 'light' ? 'Light theme' : 'Dark theme');
  }
  if (themeBtn) themeBtn.onclick = () =>
    applyTheme(document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light');

  /* ================= markdown ================= */
  /* One code block: collapsed by default (the diff card already shows the
     real change), with a filename header and a copy button. Long blocks stay
     folded so a reply doesn't turn into an endless wall of source. */
  function renderCode(label, code, open) {
    const path = (label || '').includes(':') ? label.slice(label.indexOf(':') + 1).trim() : '';
    const lines = code.split('\n').length;
    const kb = (new Blob([code]).size / 1024).toFixed(1);
    const head = '<span class="chead">' +
      (label ? '<span class="lang">' + esc(label) + '</span>' : '') +
      '<span class="cfile">' + esc(path) + '</span>' +
      '<span class="cmeta">' + lines + ' lines · ' + kb + ' KB</span>' +
      '<button class="ccopy" type="button" title="Copy code">Copy</button>' +
      '<button class="cexpand" type="button">' + (open ? 'Hide' : 'Show') + '</button>' +
      '</span>';
    const body = '<code>' + esc(code) + '</code>';
    return '<pre class="cblock' + (open ? ' open' : '') + '" data-collapsed="1">' + head + body + '</pre>';
  }

  function md(src) {
    if (!src) return '';
    const fences = [];
    let text = String(src);
    // Closed fences first. Short blocks (<25 lines) stay open; long ones fold.
    text = text.replace(/```([A-Za-z0-9_+\-.]*)(:[^\n`]*)?\n([\s\S]*?)```/g, (m, lang, path, code) => {
      const label = (lang || '') + (path ? path : '');
      const body = code.replace(/\n$/, '');
      fences.push(renderCode(label.trim(), body, body.split('\n').length <= 25));
      return '\u0000F' + (fences.length - 1) + '\u0000';
    });
    // A still-streaming (unterminated) fence: render it as an open code block
    // instead of leaking raw ``` backticks into the message.
    text = text.replace(/```([A-Za-z0-9_+\-.]*)(:[^\n`]*)?\n([\s\S]*)$/, (m, lang, path, code) => {
      const label = (lang || '') + (path ? path : '');
      fences.push(renderCode(label.trim(), code, true) + '<span class="caret"></span>');
      return '\u0000F' + (fences.length - 1) + '\u0000';
    });

    text = esc(text);
    const lines = text.split('\n');
    const out = [];
    let para = [], list = null, quote = [];

    const flushPara = () => { if (para.length) { out.push('<p>' + para.join('<br>') + '</p>'); para = []; } };
    const flushList = () => { if (list) { out.push('<' + list.tag + '>' + list.items.join('') + '</' + list.tag + '>'); list = null; } };
    const flushQuote = () => { if (quote.length) { out.push('<blockquote>' + quote.join('<br>') + '</blockquote>'); quote = []; } };
    const flushAll = () => { flushPara(); flushList(); flushQuote(); };

    for (const raw of lines) {
      const line = raw.trimEnd();
      const fence = line.match(/^\u0000F\d+\u0000$/);
      if (fence) { flushAll(); out.push(line); continue; }
      if (/^<pre>/.test(line)) { flushAll(); out.push(line); continue; }
      if (!line.trim()) { flushAll(); continue; }
      let m;
      if ((m = line.match(/^(#{1,4})\s+(.*)$/))) { flushAll(); const t = m[1].length; out.push(`<h${t}>${inline(m[2])}</h${t}>`); continue; }
      if (/^(-{3,}|\*{3,})$/.test(line.trim())) { flushAll(); out.push('<hr>'); continue; }
      if ((m = line.match(/^&gt;\s?(.*)$/))) { flushPara(); flushList(); quote.push(inline(m[1])); continue; }
      if ((m = line.match(/^[-*]\s+(.*)$/))) { flushPara(); flushQuote(); if (!list || list.tag !== 'ul') { flushList(); list = { tag: 'ul', items: [] }; } list.items.push('<li>' + inline(m[1]) + '</li>'); continue; }
      if ((m = line.match(/^\d+[.)]\s+(.*)$/))) { flushPara(); flushQuote(); if (!list || list.tag !== 'ol') { flushList(); list = { tag: 'ol', items: [] }; } list.items.push('<li>' + inline(m[1]) + '</li>'); continue; }
      flushList(); flushQuote(); para.push(inline(line));
    }
    flushAll();
    let html = out.join('');
    html = html.replace(/\u0000F(\d+)\u0000/g, (m, i) => fences[+i] || '');
    return html;
  }

  function inline(s) {
    return s
      .replace(/`([^`]+)`/g, '<code>$1</code>')
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
      .replace(/\[([^\]]+)\]\((https?:[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
  }

  /* ================= settings / providers ================= */
  const sDlg = $('#settings-dialog');
  let PROVIDERS = null;
  let selectedPreset = null;

  async function loadProviders() {
    PROVIDERS = await api('api/providers');
    renderSwitcher();
    // the backend owns the preset list — never hardcode provider URLs here.
    // Stable partition: keyless (no API key needed) first, otherwise the
    // curated backend order is preserved (Zen's no-signup option leads).
    const presets = (PROVIDERS.presets || []).slice().sort((a, b) => (a.keyless ? 0 : 1) - (b.keyless ? 0 : 1));
    renderPresets(presets);
    renderFreeModels();
    wireModelSwitcher();
    warmModelCatalog();
    if (sDlg && sDlg.open) { renderModelsPane(); renderServersPane(); }
    return PROVIDERS;
  }

  /* Picking from the header switches the model, adding it to the provider the
     first time it is used (so catalogue models actually become usable). */
  function wireModelSwitcher() {
    const sel = $('#model-switch');
    if (!sel || sel.dataset.wired === '1') return;
    sel.dataset.wired = '1';
    sel.onchange = async () => {
      const val = sel.value || '';
      const i = val.indexOf('::');
      if (i < 0) return;
      const provider = val.slice(0, i), model = val.slice(i + 2);
      try {
        const p = (PROVIDERS.providers || []).find((x) => x.id === provider);
        if (p && !(p.models || []).includes(model)) {
          await api('api/providers/' + provider, { method: 'POST', body: JSON.stringify({ models: (p.models || []).concat([model]) }) });
        }
        await api('api/active', { method: 'POST', body: JSON.stringify({ provider, model }) });
        await loadProviders();
        toast('Using ' + model, 'ok');
      } catch (e) { toast(e.message, 'err'); await loadProviders(); }
    };
  }

  /* Header dropdown: every configured model, plus the full provider catalogue so
     anything the server offers can be picked without leaving the page. */
  let catalogCache = null;

  function renderSwitcher() {
    const sel = $('#model-switch');
    if (!sel || !PROVIDERS) return;
    const active = PROVIDERS.active || {};
    sel.innerHTML = '';

    // 1. models the user already has configured
    PROVIDERS.providers.forEach((p) => {
      const og = document.createElement('optgroup');
      og.label = p.name;
      (p.models || []).forEach((m) => {
        const o = document.createElement('option');
        o.value = p.id + '::' + m;
        o.textContent = isFreeModel(m) ? m + '  ·  free' : m;
        if (p.id === active.provider && m === active.model) o.selected = true;
        og.appendChild(o);
      });
      if (!(p.models || []).length) {
        const o = document.createElement('option');
        o.disabled = true; o.textContent = '(no models yet — add in Settings)';
        og.appendChild(o);
      }
      sel.appendChild(og);
    });

    // 2. everything else the catalogue knows about, grouped per provider
    const configured = new Set();
    PROVIDERS.providers.forEach((p) => (p.models || []).forEach((m) => configured.add(p.id + '::' + m)));
    (catalogCache || []).forEach((entry) => {
      const prov = PROVIDERS.providers.find((p) => p.id === entry.id);
      const extra = (entry.models || []).filter((m) => !configured.has(entry.id + '::' + m));
      if (!extra.length) return;
      const og = document.createElement('optgroup');
      og.label = entry.name + ' — all models';
      og.dataset.catalog = entry.id;
      extra.forEach((m) => {
        const o = document.createElement('option');
        o.value = entry.id + '::' + m;
        o.textContent = isFreeModel(m) ? m + '  ·  free' : m;
        og.appendChild(o);
      });
      sel.appendChild(og);
    });
  }

  /* Pick a model from the header. The catalogue comes from our own backend (a
     browser cannot call third-party /model endpoints: no CORS, and it would
     leak the user's IP to dozens of vendors on every page load). */
  function warmModelCatalog() {
    api('api/catalog').then((d) => {
      catalogCache = Array.isArray(d.catalog) ? d.catalog : [];
      renderSwitcher();
    }).catch(() => {});
  }

  // A model is offered in the no-signup picker unless Zen has retired it.
// 'ok' works right now; 'gated' works once the user adds a Zen key.
  function isFreeModel(id) {
    const m = (PROVIDERS.free_models || []).find((x) => x.id === id);
    return !!(m && m.free && m.verified !== false && m.probe !== 'dead');
  }

  /* free-model picker (top of the Models tab) */
  function renderFreeModels() {
    const wrap = $('#free-rank');
    if (!wrap || !PROVIDERS) return;
    const active = PROVIDERS.active || {};
    const list = PROVIDERS.free_models || [];
    if (!list.length) { wrap.innerHTML = ''; return; }
    const freeProv = PROVIDERS.providers.find((p) => p.keyless) || null;

    wrap.innerHTML = list.map((m) => {
      const dead = m.probe === 'dead' || m.verified === false;
      const usable = !dead && m.free;
      const gated = !dead && m.probe === 'gated';
      const blocked = !usable;
      const on = active.model === m.id && active.provider === (freeProv ? freeProv.id : '');
      const badge = dead
        ? '<span class="fr-badge dead">unavailable</span>'
        : gated
          ? '<span class="fr-badge paid">&#128273; needs key</span>'
          : '<span class="fr-badge free">&#128274; free</span>';
      return '<button class="fr-row' + (blocked ? ' na' : '') + (on ? ' on' : '') + '"' +
        (blocked ? ' disabled' : '') + ' data-model="' + esc(m.id) + '"' +
        ' title="' + esc(m.note || m.name) + '">' +
        '<span class="fr-name">' + esc(m.name) + (m.note ? '<em>' + esc(m.note) + '</em>' : '') + '</span>' +
        badge +
        '<span class="fr-id">' + esc(m.id) + '</span></button>';
    }).join('');

    wrap.querySelectorAll('.fr-row:not(.na)').forEach((b) => b.onclick = async () => {
      const id = b.dataset.model;
      const row = (PROVIDERS.free_models || []).find((x) => x.id === id);
      const prov = freeProv ? freeProv.id : active.provider;
      if (!prov) return;
      // a gated model can't answer without a key: offer to add one first
      if (row && row.probe === 'gated' && freeProv && !freeProv.api_key_set) {
        askForZenKey(id);
        return;
      }
      try {
        await setActive(prov, id);
        await loadProviders();
        toast('Using ' + id, 'ok');
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  /* one-click Zen key setup for the models Zen only serves to signed-in clients */
  function askForZenKey(model) {
    const dlg = $('#settings-dialog');
    if (dlg) { sBtn && sBtn.click(); }
    const form = $('#provider-form');
    if (!form) { toast('Add a key in Settings -> Add server'); return; }
    const nameEl = form.querySelector('[name=name]'), urlEl = form.querySelector('[name=base_url]'), keyEl = form.querySelector('[name=api_key]');
    if (nameEl) nameEl.value = 'OpenCode Zen';
    if (urlEl) urlEl.value = 'https://opencode.ai/zen/v1';
    if (keyEl) { keyEl.value = ''; keyEl.focus(); }
    $$('.dtab').forEach((t) => t.classList.toggle('active', t.dataset.dtab === 'add'));
    $$('.dpane').forEach((p) => { p.hidden = p.dataset.dpane !== 'add'; });
    toast('Paste your free Zen key to unlock ' + model, 'ok');
  }

  async function setActive(provider, model) {
    await api('api/active', { method: 'POST', body: JSON.stringify({ provider, model }) });
  }

  function renderModelsPane() {
    if (!PROVIDERS) return;
    renderFreeModels();
    const active = PROVIDERS.active || {};
    const card = $('#active-card');
    if (!card) return;
    const cur = PROVIDERS.providers.find((p) => p.id === active.provider);
    card.innerHTML =
      '<div><div class="ac-label">active model</div>' +
      '<div class="ac-model">' + esc(active.model || 'none') + '</div>' +
      '<div class="ac-prov">' + esc(cur ? cur.name : '—') + (cur ? ' · ' + esc(cur.base_url) : '') + '</div></div>';

    const wrap = $('#model-groups');
    wrap.innerHTML = '';
    PROVIDERS.providers.forEach((p) => {
      const g = document.createElement('div');
      g.className = 'mgroup';
      const chips = (p.models || []).map((m) => {
        const fr = isFreeModel(m);
        return `<span class="mchip ${p.id === active.provider && m === active.model ? 'on' : ''}" data-prov="${esc(p.id)}" data-model="${esc(m)}">${esc(m)}${fr ? '<em class="mchip-f">free</em>' : ''}</span>`;
      }).join('');
      g.innerHTML =
        `<div class="mgroup-head"><span>${esc(p.name)}</span><span class="kindtag">${esc(p.kind || 'openai')}</span>` +
        `<span class="url">${esc(p.base_url)}</span><span class="spacer"></span>` +
        `<span class="keytag ${p.api_key_set ? '' : 'no'}">${p.api_key_set ? 'key ✓' : (p.keyless ? 'no key needed' : 'no key')}</span></div>` +
        `<div class="chips">${chips || '<span class="muted tiny">no models yet — add one below</span>'}</div>` +
        `<div class="addmodel"><input placeholder="model id, e.g. llama-3.3-70b-versatile" data-prov="${esc(p.id)}">` +
        `<button class="btn tiny" data-addmodel="${esc(p.id)}">+ Add</button></div>`;
      wrap.appendChild(g);
    });

    $$('.mchip', wrap).forEach((c) => c.onclick = async () => {
      try {
        await api('api/active', { method: 'POST', body: JSON.stringify({ provider: c.dataset.prov, model: c.dataset.model }) });
        await loadProviders();
        renderModelsPane();
        toast('Switched to ' + c.dataset.model, 'ok');
      } catch (e) { toast(e.message, 'err'); }
    });
    $$('[data-addmodel]', wrap).forEach((b) => b.onclick = () => addModel(b.dataset.addmodel));
    $$('.addmodel input', wrap).forEach((i) => i.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); addModel(i.dataset.prov); }
    }));
  }

  async function addModel(provId) {
    const input = $(`.addmodel input[data-prov="${provId}"]`);
    const val = (input.value || '').trim();
    if (!val) return;
    const p = PROVIDERS.providers.find((x) => x.id === provId);
    try {
      await api('api/providers/' + provId, { method: 'POST', body: JSON.stringify({ models: (p.models || []).concat([val]) }) });
      await api('api/active', { method: 'POST', body: JSON.stringify({ provider: provId, model: val }) });
      await loadProviders();
      renderModelsPane();
      toast('Model added & activated', 'ok');
    } catch (e) { toast(e.message, 'err'); }
  }

  function renderServersPane() {
    if (!PROVIDERS) return;
    const wrap = $('#provider-list');
    wrap.innerHTML = '';
    PROVIDERS.providers.forEach((p) => {
      const el = document.createElement('div');
      el.className = 'prov';
      el.dataset.id = p.id;
      el.innerHTML =
        `<div class="prov-top">
           <span class="prov-name">${esc(p.name)}</span>
           <span class="kindtag">${esc(p.kind || 'openai')}</span>
           ${p.keyless ? '<span class="keytag ok">no key needed</span>' : ''}
           <span class="prov-url" title="${esc(p.base_url)}">${esc(p.base_url)}</span>
           <span class="keytag ${p.api_key_set ? '' : 'no'}">${p.api_key_set ? 'key ' + esc(p.api_key_hint || '✓') : 'no key'}</span>
           <span class="prov-actions">
             <button class="btn tiny" data-act="fetch">⟳ Fetch models</button>
             <button class="btn tiny" data-act="edit">✎ Edit</button>
             <button class="btn tiny danger" data-act="del">✕</button>
           </span>
         </div>
         <div class="prov-edit">
           <div class="row">
             <label>Name <input name="name" value="${esc(p.name)}"></label>
             <label>Base URL <input name="base_url" value="${esc(p.base_url)}"></label>
           </div>
           <label>API key <input name="api_key" type="password" placeholder="${p.api_key_set ? '•••• (unchanged)' : 'sk-…'}" autocomplete="off"></label>
           <label>Models <span class="muted tiny">(one per line)</span>
             <textarea name="models" rows="3">${esc((p.models || []).join('\n'))}</textarea>
           </label>
           <div class="row end">
             <button class="btn ghost" data-act="cancel">Cancel</button>
             <button class="btn primary" data-act="save">Save</button>
           </div>
         </div>`;
      wrap.appendChild(el);

      const f = $('.prov-edit', el);
      $('[data-act=edit]', el).onclick = () => { el.classList.toggle('editing'); };
      $('[data-act=cancel]', el).onclick = () => { el.classList.remove('editing'); };
      $('[data-act=fetch]', el).onclick = async (ev) => {
        const b = ev.currentTarget; b.classList.add('busy'); b.textContent = '…';
        try {
          const r = await api('api/providers/' + p.id + '/fetch-models', { method: 'POST', body: '{}' });
          const merged = Array.from(new Set((p.models || []).concat(r.models)));
          await api('api/providers/' + p.id, { method: 'POST', body: JSON.stringify({ models: merged }) });
          await loadProviders(); renderServersPane(); renderModelsPane();
          toast(r.models.length + ' models fetched from ' + p.name, 'ok');
        } catch (e) { toast(e.message, 'err'); }
        b.classList.remove('busy'); b.textContent = '⟳ Fetch models';
      };
      $('[data-act=save]', el).onclick = async () => {
        try {
          const val = (n) => f.querySelector('[name=' + n + ']').value;
          await api('api/providers/' + p.id, { method: 'POST', body: JSON.stringify({
            name: val('name'), base_url: val('base_url'),
            api_key: val('api_key'), models: val('models')
          })});
          await loadProviders(); renderServersPane(); renderModelsPane();
          toast('Server updated', 'ok');
        } catch (e) { toast(e.message, 'err'); }
      };
      $('[data-act=del]', el).onclick = async () => {
        if (!confirm('Remove server "' + p.name + '"?')) return;
        try {
          await api('api/providers/' + p.id, { method: 'DELETE' });
          await loadProviders(); renderServersPane(); renderModelsPane();
          toast('Server removed', 'ok');
        } catch (e) { toast(e.message, 'err'); }
      };
    });
  }

  function renderPresets(presets) {
    const wrap = $('#preset-chips');
    if (!wrap) return;
    wrap.innerHTML = '';
    presets.forEach((pr) => {
      const c = document.createElement('span');
      c.className = 'preset-chip';
      c.textContent = pr.name + (pr.keyless ? ' 🔓' : ' 🔑');
      c.title = pr.hint || '';
      c.onclick = () => {
        selectedPreset = pr;
        const f = $('#provider-form');
        const set = (n, v) => { f.querySelector('[name=' + n + ']').value = v; };
        set('name', pr.name);
        set('base_url', pr.base_url || '');
        set('kind', pr.kind || 'openai');
        set('models', (pr.suggest || []).join('\n'));
        set('api_key', '');
        const keyInput = f.querySelector('[name=api_key]');
        if (pr.keyless) {
          keyInput.placeholder = 'not needed';
          keyInput.disabled = true;
          $('#provider-form-error').textContent = pr.hint || '';
        } else {
          keyInput.placeholder = 'sk-…';
          keyInput.disabled = false;
          $('#provider-form-error').textContent = 'Get a free key below — no credit card needed.';
        }
        toast(pr.name + ' filled in' + (pr.keyless ? ' — no key needed' : ' — add your key'));
      };
      wrap.appendChild(c);
    });
  }

  const sBtn = $('#settings-btn');
  if (sBtn && sDlg) {
    // load providers up front so the model switcher and preset chips are ready
    loadProviders().catch(() => {});
    sBtn.onclick = async () => {
      try { await loadProviders(); } catch (e) { toast(e.message, 'err'); }
      renderModelsPane(); renderServersPane();
      sDlg.showModal();
    };
    $('#dlg-close').onclick = () => sDlg.close();
    $$('.dtab').forEach((t) => t.onclick = () => {
      $$('.dtab').forEach((x) => x.classList.toggle('active', x === t));
      $$('.dpane').forEach((p) => p.hidden = p.dataset.dpane !== t.dataset.dtab);
    });
    $('#provider-form').onsubmit = async (e) => {
      e.preventDefault();
      const f = e.target;
      $('#provider-form-error').textContent = '';
      try {
        const val = (n) => f.querySelector('[name=' + n + ']').value;
        const useP = selectedPreset && selectedPreset.base_url === val('base_url') ? selectedPreset : null;
        const r = await api('api/providers', { method: 'POST', body: JSON.stringify({
          name: val('name'), base_url: val('base_url'), api_key: val('api_key'),
          kind: val('kind'), models: val('models'),
          keyless: useP ? !!useP.keyless : false,
          stream: useP ? useP.stream !== false : true,
          key_url: useP ? (useP.key_url || '') : ''
        })});
        await loadProviders();
        f.reset();
        f.querySelector('[name=api_key]').disabled = false;
        const gk = $('#get-key-link'); if (gk) gk.style.display = 'none';
        selectedPreset = null;
        toast('Added ' + r.provider.name, 'ok');
        $$('.dtab').find((t) => t.dataset.dtab === 'servers').click();
      } catch (err) { $('#provider-form-error').textContent = err.message; }
    };
  }

  /* ================= home ================= */
  const newBtn = $('#new-project-btn');
  if (newBtn) {
    const form = $('#new-project-form');
    const openForm = () => { form.hidden = !form.hidden; if (!form.hidden) form.querySelector('[name=name]').focus(); };
    newBtn.onclick = openForm;
    const emptyBtn = $('#empty-new-btn');
    if (emptyBtn) emptyBtn.onclick = openForm;
    const cancelBtn = $('#cancel-project');
    if (cancelBtn) cancelBtn.onclick = () => { form.hidden = true; };
    form.onsubmit = async (e) => {
      e.preventDefault();
      $('#project-form-error').textContent = '';
      try {
        const data = await api('api/projects', { method: 'POST', body: JSON.stringify({
          name: form.querySelector('[name=name]').value,
          description: form.querySelector('[name=description]').value,
          type: form.querySelector('[name=type]').value
        })});
        location.href = apiUrl('p/' + data.project.id);
      } catch (err) { $('#project-form-error').textContent = err.message; }
    };
    $$('[data-del]').forEach((b) => {
      b.onclick = async (e) => {
        e.preventDefault(); e.stopPropagation();
        if (!confirm('Delete this project and all its files?')) return;
        try { await api('api/projects/' + b.dataset.del, { method: 'DELETE' }); b.closest('.project-card').remove(); toast('Project deleted', 'ok'); }
        catch (err) { toast(err.message, 'err'); }
      };
    });
  }

  /* ---- templates ---- */
  const TEMPLATES = {
    portfolio: {
      name: 'My Portfolio', type: 'php',
      description: 'A personal portfolio with a hero section and three project cards.',
      files: {
        'index.php': '<?php\n$projects = [\n  ["Polaroid App", "A photo-sharing app."],\n  ["Travel Log", "A travel diary website."],\n  ["Study Tracker", "A habit tracker."],\n];\n$name = "Your Name";\n?>\n<!doctype html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title><?= $name ?> — Portfolio</title>\n<link rel="stylesheet" href="style.css">\n</head>\n<body>\n<section class="hero">\n  <h1><?= $name ?></h1>\n  <p>Designer & builder. I make clean, friendly websites.</p>\n</section>\n<section class="work">\n  <h2>Selected work</h2>\n  <div class="cards">\n    <?php foreach ($projects as $p): ?>\n    <article class="card">\n      <h3><?= $p[0] ?></h3>\n      <p><?= $p[1] ?></p>\n    </article>\n    <?php endforeach ?>\n  </div>\n</section>\n<footer>&copy; <?= date("Y") ?> <?= $name ?></footer>\n</body>\n</html>\n',
        'style.css': ':root{--ink:#1c2440;--accent:#5b7cfa;--bg:#f6f8fc}\n*{box-sizing:border-box}\nbody{margin:0;font-family:system-ui,"Segoe UI",sans-serif;color:var(--ink);background:var(--bg);line-height:1.6}\n.hero{background:linear-gradient(135deg,#5b7cfa22,#a78bfa22);padding:64px 24px;text-align:center}\n.hero h1{margin:0 0 8px;font-size:38px}\n.work{max-width:880px;margin:0 auto;padding:40px 24px}\n.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}\n.card{background:#fff;border:1px solid #e3e8f4;border-radius:12px;padding:18px;box-shadow:0 4px 14px #1c244010}\n.card h3{margin:0 0 6px}\nfooter{text-align:center;color:#7a86a8;padding:28px}\n',
      },
    },
    landing: {
      name: 'Landing Page', type: 'php',
      description: 'A modern product landing page with a hero, features and a call to action.',
      files: {
        'index.php': '<?php\n$product = "Nova";\n$features = ["Fast and simple", "Works everywhere", "Privacy first"];\n?>\n<!doctype html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title><?= $product ?> — Landing</title>\n<link rel="stylesheet" href="style.css">\n</head>\n<body>\n<header class="nav"><?= $product ?></header>\n<section class="hero">\n  <h1><?= $product ?> — built for people who ship</h1>\n  <p>Everything you need to launch something great.</p>\n  <a class="cta" href="mailto:you@example.com">Get started</a>\n</section>\n<section class="features">\n  <?php foreach ($features as $f): ?>\n  <div class="feature"><?= $f ?></div>\n  <?php endforeach ?>\n</section>\n<footer>&copy; <?= date("Y") ?> <?= $product ?></footer>\n</body>\n</html>\n',
        'style.css': ':root{--bg:#0b0f1a;--text:#eef1f8;--accent:#4ade80;--panel:#131a2b}\n*{box-sizing:border-box}\nbody{margin:0;font-family:system-ui,"Segoe UI",sans-serif;background:var(--bg);color:var(--text);line-height:1.6}\n.nav{padding:18px 28px;font-weight:700;letter-spacing:.5px}\n.hero{padding:90px 24px 70px;text-align:center;background:radial-gradient(600px 260px at 50% 0%,#4ade8018,transparent)}\n.hero h1{font-size:40px;margin:0 0 14px}\n.hero p{color:#9fb0d0;margin:0 0 26px;font-size:18px}\n.cta{display:inline-block;background:var(--accent);color:#04210f;padding:13px 28px;border-radius:99px;font-weight:700;text-decoration:none}\n.features{max-width:880px;margin:0 auto;padding:34px 24px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}\n.feature{background:var(--panel);border:1px solid #22304d;border-radius:12px;padding:16px;text-align:center}\nfooter{text-align:center;color:#6d7c9e;padding:26px}\n',
      },
    },
    business: {
      name: 'Business Website', type: 'php',
      description: 'A small business site with home, services and about pages.',
      files: {
        'index.php': '<?php $site = "Acme Studio"; ?>\n<!doctype html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title><?= $site ?></title>\n<link rel="stylesheet" href="style.css">\n</head>\n<body>\n<nav class="nav"><a class="brand" href="index.php"><?= $site ?></a><a href="index.php">Home</a><a href="about.php">About</a></nav>\n<header class="hero"><h1>Great work, done simply</h1><p>We help small businesses shine online.</p><a class="cta" href="mailto:hello@example.com">Contact us</a></header>\n<footer>&copy; <?= date("Y") ?> <?= $site ?></footer>\n</body>\n</html>\n',
        'about.php': '<?php $site = "Acme Studio"; ?>\n<!doctype html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>About — <?= $site ?></title>\n<link rel="stylesheet" href="style.css">\n</head>\n<body>\n<nav class="nav"><a class="brand" href="index.php"><?= $site ?></a><a href="index.php">Home</a><a href="about.php">About</a></nav>\n<main class="page-box"><h1>About us</h1><p>We are a friendly team of designers and developers. Tell us what you need and we will take care of the rest.</p></main>\n<footer>&copy; <?= date("Y") ?> <?= $site ?></footer>\n</body>\n</html>\n',
        'style.css': ':root{--ink:#20293c;--accent:#f59e0b;--bg:#fdf7ec}\n*{box-sizing:border-box}\nbody{margin:0;font-family:Georgia,serif;color:var(--ink);background:var(--bg);line-height:1.7}\n.nav{display:flex;gap:22px;align-items:center;padding:16px 28px;background:#fff;border-bottom:1px solid #f0e4cc}\n.brand{font-weight:700;font-size:18px;color:var(--ink)}\n.nav a{color:#5b6478;text-decoration:none}\n.hero{padding:80px 24px;text-align:center}\n.hero h1{font-size:42px;margin:0 0 12px}\n.cta{display:inline-block;background:var(--accent);color:#fff;padding:12px 26px;border-radius:8px;text-decoration:none}\n.page-box{max-width:760px;margin:0 auto;padding:40px 24px}\nfooter{text-align:center;color:#9a917e;padding:26px}\n',
      },
    },
  };

  async function createFromTemplate(key) {
    const t = TEMPLATES[key];
    if (!t) return;
    const btn = $('.tpl[data-tpl=' + key + ']');
    const original = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.textContent = 'creating…'; }
    try {
      const d = await api('api/projects', { method: 'POST', body: JSON.stringify({ name: t.name, description: t.description, type: t.type }) });
      const id = d.project.id;
      for (const [p, c] of Object.entries(t.files)) {
        await api('api/projects/' + id + '/file', { method: 'POST', body: JSON.stringify({ path: p, content: c }) });
      }
      location.href = apiUrl('p/' + id);
    } catch (e) {
      if (btn) { btn.disabled = false; btn.innerHTML = original; }
      toast(e.message, 'err');
    }
  }
  $$('.tpl').forEach((b) => b.onclick = () => createFromTemplate(b.dataset.tpl));

  /* ================================================================== */
  /* ============================ WORKSPACE ========================== */
  /* ================================================================== */
  const ws = $('.workspace');
  if (!ws) return;
  const PID = ws.dataset.project;

  let busy = false;
  let tree = [], allFiles = [], undoableFiles = new Set(), redoableFiles = new Set(), latestUndo = {}, latestRedo = {};
  let SID = null, sessions = [], applyMode = 'ask', pendingChanges = [];
  let atts = [], abortCtrl = null;
  let npKind = 'file';
  let running = false, previewTarget = null, deviceW = 0;

  const status = (t) => { const el = $('#agent-status'); if (el) el.textContent = t; };

  /* ================= live preview (opt-in overlay) ================= */
  const ovl = $('#preview-ovl'), frame = $('#preview-frame'), emptyBox = $('#preview-empty');

  function rootUrl() { return apiUrl('preview/' + PID + '/'); }

  function setRunUI(s) {
    running = !!(s && s.running);
    const state = $('#pv-state');
    if (state) { state.textContent = running ? 'live' : 'stopped'; state.classList.toggle('on', running); }
    if (emptyBox) emptyBox.hidden = running;
    const btn = $('#btn-preview');
    if (btn) {
      // while the server is live the button stops it; otherwise it opens preview
      btn.innerHTML = running ? '&#9632; Stop' : '&#9654; Preview';
      btn.classList.toggle('active', running);
      btn.title = running ? 'Stop the live preview server' : 'See the live website (Ctrl+Shift+P)';
      btn.classList.remove('primary');
    }
    if (running && s && s.url && frame) frame.src = previewTarget || s.url;
    else if (!running && frame) frame.removeAttribute('src');
    applyDevice();
  }

  function applyDevice() {
    const stage = $('#preview-stage');
    if (!stage) return;
    if (!deviceW) {
      stage.style.removeProperty('width');
      stage.style.removeProperty('max-width');
      stage.style.flex = '';
    } else {
      stage.style.width = deviceW + 'px';
      stage.style.maxWidth = '100%';
      stage.style.flex = 'none';
    }
  }

  function reloadPreview() {
    if (!running || !frame) return;
    const base = previewTarget || rootUrl();
    frame.src = base + (base.includes('?') ? '&' : '?') + '_=' + Date.now();
  }

  /* the iframe gets a fresh document on every load — re-arm pick mode */
  if (frame) frame.addEventListener('load', () => {
    if (pickOn) {
      try {
        const doc = frame.contentDocument;
        if (!doc) return;
        doc.documentElement.classList.add('pick-mode');
        injectPickerStyle(doc);
        doc.addEventListener('click', onPickClick, true);
        doc.addEventListener('mouseover', onPickHover, true);
        doc.addEventListener('mouseout', onPickOut, true);
      } catch (e) { /* ignore */ }
    }
  });

  async function toggleRun() {
    const btn = $('#btn-run-empty');
    const pb = $('#btn-preview');
    if (btn) btn.classList.add('busy');
    if (pb) pb.setAttribute('aria-busy', 'true');
    try {
      const d = await api('api/projects/' + PID + '/server', { method: 'POST', body: JSON.stringify({ action: running ? 'stop' : 'start' }) });
      setRunUI(d.server);
      if (d.server.running) {
        const b = d.server.boot;
        toast(b && !b.ok ? 'Preview started with errors' : 'Preview is live', b && !b.ok ? 'err' : 'ok');
      } else toast('Preview stopped', 'ok');
    } catch (e) { toast(e.message, 'err'); }
    finally {
      if (btn) btn.classList.remove('busy');
      if (pb) pb.removeAttribute('aria-busy');
    }
  }

  /* ---- the tree and the chat move into the overlay while it is open ----
     They are moved, not cloned, so every listener, message, scroll
     position and pending diff card survives the round trip. */
  const panesEl = $('#panes'), chatEl = $('#chat-pane');
  const panesHome = panesEl ? panesEl.parentNode : null;
  const panesNext = panesEl ? panesEl.nextSibling : null;
  const chatNext = chatEl ? chatEl.nextSibling : null;
  const ovlLeft = $('#ovl-left'), ovlChat = $('#ovl-chat');
  let savedTrack = null;

  function moveIntoOverlay() {
    if (!panesEl || !ovlLeft || panesEl.parentElement === ovlLeft) return;
    savedTrack = panesEl.style.gridTemplateColumns;
    panesEl.style.gridTemplateColumns = '';
    if (chatEl && chatEl.parentElement === panesEl) chatEl.remove();
    panesEl.classList.add('ovl-mode');
    ovlLeft.appendChild(panesEl);
    if (chatEl) ovlChat.appendChild(chatEl);
  }

  function restoreFromOverlay() {
    if (!panesEl || panesEl.parentElement !== ovlLeft) return;
    if (chatEl && chatEl.parentElement === ovlChat) {
      if (chatNext && chatNext.parentNode === panesEl) panesEl.insertBefore(chatEl, chatNext);
      else panesEl.appendChild(chatEl);
    }
    panesEl.classList.remove('ovl-mode');
    // keep a splitter drag made while the preview was open
    const dragged = panesEl.style.gridTemplateColumns;
    panesEl.style.gridTemplateColumns = dragged || savedTrack || '';
    savedTrack = null;
    if (panesHome) panesHome.insertBefore(panesEl, panesNext && panesNext.parentNode === panesHome ? panesNext : null);
  }

  function openPreview() {
    if (!ovl) return;
    moveIntoOverlay();
    ovl.hidden = false;
    if (running) reloadPreview();
    else toggleRun();
  }
  function closePreview() {
    if (!ovl) return;
    ovl.hidden = true;
    restoreFromOverlay();
  }

  /* show/hide the chat column beside the site */
  const chatToggle = $('#btn-ovl-chat'), ovlBody = $('#ovl-body');
  function setChatShown(shown) {
    if (!chatToggle || !ovlBody) return;
    ovlBody.classList.toggle('no-chat', !shown);
    chatToggle.setAttribute('aria-pressed', shown ? 'true' : 'false');
    chatToggle.textContent = shown ? 'Chat' : 'Show chat';
    if (ovl) ovl.classList.toggle('chat-hidden', !shown);
  }
  if (chatToggle) chatToggle.onclick = () => setChatShown(ovlBody.classList.contains('no-chat'));
  const chatRestore = $('#btn-chat-restore');
  if (chatRestore) chatRestore.onclick = () => setChatShown(true);

  /* ================= pick an element in the preview =================
   Click a part of the rendered page, then the bot gets a precise instruction
   ("change this <selector>") plus the element's current HTML, so edits land
   on exactly that element instead of the whole page. */
  let pickOn = false, pickedEl = null;

  function cssSelector(el) {
    if (!el || el.nodeType !== 1) return '';
    const parts = [];
    let node = el;
    while (node && node.nodeType === 1 && node.tagName && parts.length < 6) {
      let part = node.tagName.toLowerCase();
      if (node.id) { parts.unshift(part + '#' + node.id); break; }
      const cls = (node.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean)[0];
      if (cls) part += '.' + cls;
      const parent = node.parentElement;
      if (parent) {
        const sibs = [...parent.children].filter((c) => c.tagName === node.tagName);
        if (sibs.length > 1) part += ':nth-of-type(' + (sibs.indexOf(node) + 1) + ')';
      }
      parts.unshift(part);
      node = node.parentElement;
      if (node === document.body) break;
    }
    return parts.join(' > ');
  }

  function togglePick() {
    if (!frame) return;
    pickOn = !pickOn;
    const btn = $('#btn-pick');
    if (btn) { btn.classList.toggle('active', pickOn); btn.setAttribute('aria-pressed', pickOn ? 'true' : 'false'); }
    try {
      const doc = frame.contentDocument;
      if (!doc) { pickOn = false; return; }
      if (pickOn) {
        doc.documentElement.classList.add('pick-mode');
        injectPickerStyle(doc);
        // click handler on the iframe document
        doc.addEventListener('click', onPickClick, true);
        // hover outline
        doc.addEventListener('mouseover', onPickHover, true);
        doc.addEventListener('mouseout', onPickOut, true);
        if (pickedEl) markPicked(pickedEl);
        toast('Pick mode on — click any part of the page', 'ok');
      } else {
        doc.documentElement.classList.remove('pick-mode');
        doc.removeEventListener('click', onPickClick, true);
        doc.removeEventListener('mouseover', onPickHover, true);
        doc.removeEventListener('mouseout', onPickOut, true);
        if (pickedEl) unmark(pickedEl);
        const b = $('#btn-pick'); if (b) { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); }
      }
    } catch (e) { /* cross-origin, shouldn't happen (same origin preview) */ }
  }

  function injectPickerStyle(doc) {
    try {
      if (doc.getElementById('__or_pick_style')) return;
      const st = doc.createElement('style');
      st.id = '__or_pick_style';
      st.textContent =
        'html.pick-mode,html.pick-mode *{cursor:crosshair !important}' +
        '.or-hover{outline:2px dashed #7aa2ff !important;outline-offset:2px;background:rgba(122,162,255,.08) !important}' +
        '.or-picked{outline:2px solid #4ade80 !important;outline-offset:2px;background:rgba(74,222,128,.10) !important}';
      (doc.head || doc.documentElement).appendChild(st);
    } catch (e) { /* ignore */ }
  }

  function markPicked(el) { try { el.classList.add('or-picked'); } catch (e) {} }
  function unmark(el) { try { el.classList.remove('or-picked'); } catch (e) {} }

  function onPickHover(e) {
    const t = e.target;
    if (t === e.relatedTarget) return;
    try { if (t && t.nodeType === 1) t.classList.add('or-hover'); } catch (x) {}
  }
  function onPickOut(e) {
    const t = e.target;
    try { if (t && t.nodeType === 1) t.classList.remove('or-hover'); } catch (x) {}
  }

  function onPickClick(e) {
    e.preventDefault(); e.stopPropagation();
    const el = e.target;
    if (!el || el.nodeType !== 1 || el === frame.contentDocument.documentElement) return;
    if (pickedEl) unmark(pickedEl);
    pickedEl = el;
    markPicked(el);
    const selector = cssSelector(el);
    const tag = el.tagName.toLowerCase();
    const html = el.outerHTML.length > 4000 ? el.outerHTML.slice(0, 4000) + '\n…' : el.outerHTML;
    // fill the chat and focus it so the user can describe the change
    const instr = 'Update this part of the page: <' + tag + '>' +
      (selector ? ' (' + selector + ')' : '') +
      ' — change it as follows: ';
    input.value = instr;
    input.focus();
    input.selectionStart = input.selectionEnd = input.value.length;
    autosize();
    addMsg('system', 'Picked <b>' + esc(selector || tag) + '</b>. Describe the change and send it.',
      true);
    // stash the element HTML in a hidden note so the bot sees the exact markup
    pushPickNote(selector, html);
    togglePick(); // turn pick mode off after a selection
  }

  function pushPickNote(selector, html) {
    // mention a scratch file holding the exact markup so the bot edits that element
    input.value += '\n@picked-element.html ';
    autosize();
    addMsg('system', 'Current markup of the selected element will be attached.', true);
    api('api/projects/' + PID + '/pick', {
      method: 'POST',
      body: JSON.stringify({ html })
    }).catch(() => {});
  }

  const previewBtn = $('#btn-preview');
  if (previewBtn) previewBtn.onclick = () => {
    // preview open -> go back to the workspace (server keeps running)
    // preview closed + stopped -> open it (starts the server)
    // preview closed + running -> open it again (instant)
    if (ovl && !ovl.hidden) closePreview();
    else openPreview();
  };
  const previewClose = $('#btn-preview-close');
  if (previewClose) previewClose.onclick = closePreview;
  const pvStop = $('#btn-pv-stop');
  if (pvStop) pvStop.onclick = async () => { closePreview(); if (running) toggleRun(); };
  const runEmpty = $('#btn-run-empty');
  if (runEmpty) runEmpty.onclick = toggleRun;
  const pvState = $('#pv-state');
  if (pvState) {
    pvState.style.cursor = 'pointer';
    pvState.title = 'Start or stop the preview server';
    pvState.onclick = toggleRun;
  }
  const pickBtn = $('#btn-pick');
  if (pickBtn) pickBtn.onclick = () => togglePick();

  const reloadBtn = $('#btn-reload');
  if (reloadBtn) reloadBtn.onclick = () => {
    if (running) reloadPreview();
    else { toast('Start the preview first'); }
  };
  const openBtn = $('#btn-open');
  if (openBtn) openBtn.onclick = () => {
    if (running) window.open(previewTarget || rootUrl(), '_blank');
    else toast('Start the preview first');
  };

  $$('#device-seg .seg-btn').forEach((b) => b.onclick = () => {
    $$('#device-seg .seg-btn').forEach((x) => x.classList.toggle('active', x === b));
    deviceW = parseInt(b.dataset.w, 10) || 0;
    applyDevice();
  });

  /* ---------- resizable panes ---------- */
  (function resizers() {
    const panes = $('#panes');
    if (!panes) return;
    let saved = null;
    try { saved = JSON.parse(localStorage.getItem('oc-panes') || 'null'); } catch (e) {}
    if (saved && saved.side) panes.style.setProperty('--side-w', saved.side + 'px');
    $$('.splitter', panes).forEach((sp) => {
      sp.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        sp.setPointerCapture(e.pointerId);
        sp.classList.add('dragging');
        const rect = panes.getBoundingClientRect();
        const move = (ev) => {
          const w = Math.max(160, Math.min(480, ev.clientX - rect.left));
          panes.style.setProperty('--side-w', w + 'px');
        };
        const up = () => {
          sp.classList.remove('dragging');
          sp.removeEventListener('pointermove', move);
          sp.removeEventListener('pointerup', up);
          const cs = getComputedStyle(panes);
          try { localStorage.setItem('oc-panes', JSON.stringify({ side: parseInt(cs.getPropertyValue('--side-w')) || 232 })); } catch (er) {}
        };
        sp.addEventListener('pointermove', move);
        sp.addEventListener('pointerup', up);
      });
    });
  })();

  /* ================= files ================= */
  function extOf(path) { return ((path || '').split('.').pop() || '').toLowerCase(); }
  function fileIcon(path) {
    const e = extOf(path);
    if (e === 'php') return '🐘';
    if (['php', 'phtml', 'html', 'htm'].includes(e)) return '🌐';
    if (e === 'css') return '🎨';
    if (['js', 'mjs'].includes(e)) return '⚙️';
    if (e === 'json') return '🧾';
    if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'ico'].includes(e)) return '🖼️';
    if (['md', 'txt'].includes(e)) return '📄';
    return '📎';
  }

  function renderTree(nodes, container) {
    nodes.forEach((n) => {
      if (n.type === 'dir') {
        const label = document.createElement('div');
        label.className = 'file-item dir open';
        label.innerHTML = '<span class="ic">&#9662;</span><span class="fname">' + esc(n.path.split('/').pop()) + '</span>';
        const kids = document.createElement('div');
        kids.className = 'file-children';
        renderTree(n.children || [], kids);
        label.onclick = () => {
          kids.hidden = !kids.hidden;
          label.classList.toggle('open', !kids.hidden);
          label.querySelector('.ic').innerHTML = kids.hidden ? '&#9656;' : '&#9662;';
        };
        const wrap = document.createElement('div');
        wrap.append(label, kids);
        container.appendChild(wrap);
} else {
        const el = document.createElement('div');
        el.className = 'file-item';
        el.dataset.path = n.path;
        const canUndo = undoableFiles.has(n.path);
        const canRedo = redoableFiles.has(n.path);
        const hasHistory = canUndo || canRedo;
        el.innerHTML = '<span class="ic">' + fileIcon(n.path) + '</span><span class="fname">' + esc(n.path.split('/').pop()) + '</span>' +
          (hasHistory ? '<span class="hist" title="Version history">&#9203;</span>' : '') +
          (canUndo ? '<span class="undo" title="Undo the last change to this file">&#8630;</span>' : '') +
          (canRedo ? '<span class="redo" title="Redo">&#8631;</span>' : '') +
          '<span class="del" title="delete">&#10005;</span>';
        el.title = 'Click to reference ' + n.path + ' in chat';
        el.onclick = (e) => { 
      try {
        if (e.target.closest('.del,.undo,.redo,.hist')) return; 
        console.log('file click handler START:', n.path, 'input:', input, 'onFileClick:', typeof onFileClick); 
        onFileClick(n.path); 
        console.log('file click handler END'); 
      } catch (err) { 
        console.error('file click error:', err); 
      } 
    };

        const undo = $('.undo', el);
        if (undo) undo.onclick = async (e) => { e.stopPropagation(); await revertFile(n.path, undo, 'undo'); };
        const redo = $('.redo', el);
        if (redo) redo.onclick = async (e) => { e.stopPropagation(); await revertFile(n.path, redo, 'redo'); };
        const hist = $('.hist', el);
        if (hist) hist.onclick = async (e) => { e.stopPropagation(); openHistory(n.path); };

        $('.del', el).onclick = async (e) => {
          e.stopPropagation();
          if (!confirm('Delete ' + n.path + '?')) return;
          try {
            await api('api/projects/' + PID + '/file?path=' + encodeURIComponent(n.path), { method: 'DELETE' });
            undoableFiles.delete(path);
          redoableFiles.delete(path);
            toast('Deleted ' + n.path, 'ok');
          } catch (err) { toast(err.message, 'err'); }
          loadTree();
        };
        container.appendChild(el);
      }
    });
  }

  /* ---------- undo / redo a change ---------- */
  /* Toolbar buttons act on the most recently touched file that has history. */
  async function syncGlobalUndoRedo() {
    const u = $('#btn-undo-all'), r = $('#btn-redo-all');
    if (!u || !r) return;
    // the tree response already carries the newest timestamp per file, so this
    // costs zero extra requests (it used to fan out one call per file)
    const pick = (set, map) => {
      let best = null;
      for (const p of set) {
        const ts = (map || {})[p] || 0;
        if (ts && (!best || ts > best.ts)) best = { path: p, ts };
      }
      return best;
    };
    const uInfo = pick(undoableFiles, latestUndo);
    const rInfo = pick(redoableFiles, latestRedo);
    u.dataset.path = uInfo ? uInfo.path : '';
    r.dataset.path = rInfo ? rInfo.path : '';
    u.disabled = !uInfo; r.disabled = !rInfo;
    u.title = uInfo ? 'Undo the last change to ' + uInfo.path : 'Nothing to undo';
    r.title = rInfo ? 'Redo on ' + rInfo.path : 'Nothing to redo';
  }

  async function revertFile(path, btn, direction) {
    const dir = direction === 'redo' ? 'redo' : 'undo';
    if (btn) btn.classList.add('busy');
    try {
      const r = await api('api/projects/' + PID + '/revert', { method: 'POST', body: JSON.stringify({ path, direction: dir }) });
      const what = r.result === 'deleted'
        ? path + ' removed — it was created by the last change'
        : (dir === 'redo' ? 'Redid ' : 'Reverted ') + path;
      toast(what, 'ok');
      await loadTree();
    } catch (e) {
      toast(e.message, 'err');
    } finally {
      if (btn) btn.classList.remove('busy');
    }
  }

  /* ---------- version history with comments ---------- */
  async function openHistory(path) {
    const dlg = $('#hist-dlg');
    const list = $('#hist-list');
    const title = $('#hist-title');
    if (!dlg || !list) return;
    title.textContent = 'History — ' + path;
    list.innerHTML = '<div class="muted tiny">loading…</div>';
    if (!dlg.open) dlg.showModal();
    try {
      const r = await api('api/projects/' + PID + '/versions?path=' + encodeURIComponent(path));
      const undo = (r.versions && r.versions.undo) || [];
      const redo = (r.versions && r.versions.redo) || [];
      if (!undo.length && !redo.length) { list.innerHTML = '<div class="muted tiny">No versions yet — versions appear after the bot edits this file.</div>'; return; }

      const row = (v, dir, i, live) => {
        const when = new Date(v.ts * 1000);
        const ago = timeAgo(v.ts);
        return '<div class="hrow' + (live ? ' live' : '') + '" data-i="' + i + '" data-dir="' + dir + '">' +
          '<div class="hmeta"><span class="hwhen">' + esc(ago) + '</span>' +
          '<span class="hlabel">' + esc(v.comment || v.label) + (live ? '<b class="hnow">current</b>' : '') + '</span>' +
          '<span class="hbytes">' + (v.was === 'missing' ? 'created this file' : v.bytes + ' B') + '</span></div>' +
          '<div class="hact">' +
          '<input class="hcomment" placeholder="add a note…" value="' + esc(v.comment || '') + '" data-i="' + i + '" data-dir="' + dir + '">' +
          '<button class="btn tiny" data-save="' + i + '" data-dir="' + dir + '" title="Save note">&#10003;</button>' +
          '<button class="btn tiny primary" data-go="' + i + '" data-dir="' + dir + '" title="Go to this version">' +
          (dir === 'undo' ? 'Restore' : 'Redo') + '</button></div></div>';
      };
      list.innerHTML =
        (redo.length ? '<div class="hsec">redo</div>' + redo.map((v, i) => row(v, 'redo', i, i === redo.length - 1)).join('') : '') +
        '<div class="hsec">history</div>' + undo.map((v, i) => row(v, 'undo', i, !redo.length && i === 0)).join('');
      // wire buttons
      $$('#hist-list [data-save]').forEach((b) => b.onclick = async () => {
        const i = b.dataset.save, dir = b.dataset.dir;
        const input = $('.hcomment', b.parentElement);
        try {
          await api('api/projects/' + PID + '/version-comment', { method: 'POST', body: JSON.stringify({ path, index: +i, comment: input.value }) });
          b.textContent = '✓';
          toast('Note saved', 'ok');
        } catch (e) { toast(e.message, 'err'); }
      });
      $$('#hist-list [data-go]').forEach((b) => b.onclick = async () => {
        // restore exactly this entry, whatever undo/redo depth we are at
        const dir = b.dataset.dir, i = +b.dataset.go;
        b.disabled = true;
        try {
          const base = 'api/projects/' + PID + '/revert';
          const r = dir === 'undo'
            ? await api(base, { method: 'POST', body: JSON.stringify({ path, index: i }) })
            : await api(base, { method: 'POST', body: JSON.stringify({ path, direction: 'redo' }) });
          await loadTree();
          if (r.result === 'already-current') toast('Already showing that version', 'ok');
          else toast('Restored ' + path, 'ok');
          await openHistory(path);
          if (typeof syncGlobalUndoRedo === 'function') syncGlobalUndoRedo();
        } catch (e) { toast(e.message, 'err'); b.disabled = false; }
      });
    } catch (e) { list.innerHTML = '<div class="error">' + esc(e.message) + '</div>'; }
  }

  function timeAgo(ts) {
    const s = Math.max(0, Math.floor(Date.now() / 1000) - ts);
    if (s < 60) return 'just now';
    if (s < 3600) return Math.floor(s / 60) + 'm ago';
    if (s < 86400) return Math.floor(s / 3600) + 'h ago';
    return Math.floor(s / 86400) + 'd ago';
  }

  async function loadTree() {
    try {
      const data = await api('api/projects/' + PID + '/tree');
      tree = data.tree;
      allFiles = data.files || [];
      undoableFiles = new Set(data.undoable || []);
      redoableFiles = new Set(data.redoable || []);
      latestUndo = data.latest || {};
      latestRedo = data.latest_redo || {};
      syncGlobalUndoRedo();
      const el = $('#file-tree');
      el.innerHTML = '';
      if (!allFiles.length) el.innerHTML = '<div class="tree-empty muted tiny">empty — ask the chat to create files</div>';
      renderTree(tree, el);
    } catch (e) { toast(e.message, 'err'); }
  }

  /* ---------- click a file: reference it in chat ---------- */
  function onFileClick(path) {
    const pos = input.selectionStart;
    const before = input.value.slice(0, pos);
    const after = input.value.slice(pos);
    input.value = before + '@' + path + ' ' + after;
    input.selectionStart = input.selectionEnd = before.length + path.length + 2;
    input.focus();
    autosize();
    hideMenus();
    toast('Referencing ' + path + ' — now tell the bot what to fix', 'ok');
    // if the live preview is open, point it at this page
    const e = extOf(path);
    if (ovl && !ovl.hidden && ['php', 'phtml', 'html', 'htm'].includes(e)) {
      previewTarget = apiUrl('preview/' + PID + '/' + String(path).split('/').map(encodeURIComponent).join('/'));
      if (running) { reloadPreview(); toast('Previewing ' + path, 'ok'); }
    }
  }

  /* ---------- new file / folder dialog ---------- */
  const histClose = $('#hist-close');
  if (histClose && $('#hist-dlg')) histClose.onclick = () => $('#hist-dlg').close();

  const undoAll = $('#btn-undo-all'), redoAll = $('#btn-redo-all');
  if (undoAll) undoAll.onclick = () => { if (undoAll.dataset.path) revertFile(undoAll.dataset.path, undoAll, 'undo'); };
  if (redoAll) redoAll.onclick = () => { if (redoAll.dataset.path) revertFile(redoAll.dataset.path, redoAll, 'redo'); };

  const npDlg = $('#newpath-dlg'), npInput = $('#np-input'), npOk = $('#np-ok'), npCancel = $('#np-cancel'), npTitle = $('#np-title'), npHint = $('#np-hint');
  function newPath(kind) {
    npKind = kind;
    if (npTitle) npTitle.textContent = kind === 'dir' ? 'New folder' : 'New file';
    if (npHint) npHint.textContent = kind === 'dir' ? 'e.g. pages (folder will appear in the tree)' : 'e.g. about.php or pages/contact.php';
    if (npInput) { npInput.value = ''; npInput.placeholder = kind === 'dir' ? 'pages' : 'about.php'; }
    if (npDlg) npDlg.showModal();
    if (npInput) setTimeout(() => npInput.focus(), 30);
  }
  if (npOk) npOk.onclick = async () => {
    const path = (npInput.value || '').trim();
    if (!path) return;
    const dlg = npDlg;
    try {
      if (npKind === 'dir') {
        await api('api/projects/' + PID + '/file', { method: 'POST', body: JSON.stringify({ path: path.replace(/\/?$/, '/.gitkeep'), content: '' }) });
        toast('Folder created', 'ok');
      } else {
        await api('api/projects/' + PID + '/file', { method: 'POST', body: JSON.stringify({ path, content: '' }) });
        toast('Created ' + path, 'ok');
      }
      loadTree();
      if (dlg) dlg.close();
    } catch (e) { toast(e.message, 'err'); }
  };
  if (npCancel) npCancel.onclick = () => { if (npDlg) npDlg.close(); };
  if (npInput) npInput.onkeydown = (e) => { if (e.key === 'Enter') npOk.click(); };
  const newFileBtn = $('#btn-new-file');
  if (newFileBtn) newFileBtn.onclick = () => newPath('file');
  const newDirBtn = $('#btn-new-dir');
  if (newDirBtn) newDirBtn.onclick = () => newPath('dir');
  const refreshBtn = $('#btn-refresh');
  if (refreshBtn) refreshBtn.onclick = loadTree;

  /* ================= chat ================= */
  const msgs = $('#messages'), input = $('#chat-input'), sendBtn = $('#send-btn'), stopBtn = $('#stop-btn');

  function addMsg(role, html, isHtml, label) {
    const el = document.createElement('div');
    el.className = 'msg ' + role;
    el.innerHTML = '<div class="msg-role">' + esc(label || (role === 'assistant' ? 'bot' : role)) + '</div><div class="msg-body md"></div>';
    const body = $('.msg-body', el);
    if (isHtml) body.innerHTML = html; else body.textContent = html;
    // code block affordances (delegated so re-renders keep working)
    $$('.cexpand', body).forEach((b) => b.onclick = () => {
      const pre = b.closest('.cblock');
      if (!pre) return;
      const open = pre.classList.toggle('open');
      pre.dataset.collapsed = open ? '0' : '1';
      b.textContent = open ? 'Hide' : 'Show';
    });
    $$('.ccopy', body).forEach((b) => b.onclick = async () => {
      const pre = b.closest('.cblock');
      const code = $('code', pre);
      if (!code) return;
      try {
        await navigator.clipboard.writeText(code.textContent);
        b.textContent = 'Copied';
        b.classList.add('done');
        setTimeout(() => { b.textContent = 'Copy'; b.classList.remove('done'); }, 1600);
      } catch (e) { toast('Copy failed — select the code manually', 'err'); }
    });
    msgs.appendChild(el);
    msgs.scrollTop = msgs.scrollHeight;
    return el;
  }

  function chipRow(el, files) {
    let row = $('.files', el);
    if (!row) { row = document.createElement('div'); row.className = 'files'; $('.msg-body', el).after(row); }
    files.forEach((f) => {
      const c = document.createElement('span');
      c.className = 'filechip'; c.textContent = '✎ ' + f;
      c.title = 'Click to reference in chat';
      c.onclick = () => onFileClick(f);
      row.appendChild(c);
    });
  }

  function attRow(el, list) {
    let row = $('.atts', el);
    if (!row) { row = document.createElement('div'); row.className = 'atts'; $('.msg-body', el).after(row); }
    list.forEach((a) => {
      const c = document.createElement('span');
      c.className = 'attchip';
      c.innerHTML = (a.image ? '<span class="att-ic">&#128247;</span>' : '<span class="att-ic">&#128196;</span>') + '<span class="att-name"></span>';
      $('.att-name', c).textContent = a.name || a.path;
      c.title = a.path;
      c.onclick = () => onFileClick(a.path);
      row.appendChild(c);
    });
  }

  async function loadMessages() {
    try {
      const data = await api('api/projects/' + PID + '/messages' + (SID ? '?sid=' + encodeURIComponent(SID) : ''));
      if (data.session_id && !SID) SID = data.session_id;
      data.messages.forEach((m) => {
        const el = m.role === 'assistant' ? addMsg('assistant', md(m.content), true) : addMsg(m.role, m.content, false, m.role);
        if (m.files && m.files.length) chipRow(el, m.files);
        if (m.attachments && m.attachments.length) attRow(el, m.attachments);
      });
      msgs.scrollTop = msgs.scrollHeight;
      renderSessions();
    } catch (e) {}
  }

  function parseSSE(buffer, onEvent) {
    let idx;
    while ((idx = buffer.indexOf('\n\n')) !== -1) {
      const block = buffer.slice(0, idx);
      buffer = buffer.slice(idx + 2);
      let event = 'message', data = '';
      block.split('\n').forEach((line) => {
        if (line.startsWith('event:')) event = line.slice(6).trim();
        else if (line.startsWith('data:')) data += line.slice(5).trim();
      });
      if (data) {
        let d;
        try { d = JSON.parse(data); } catch (e) { continue; }
        onEvent(event, d);
      }
    }
    return buffer;
  }

  function showRateLimitCountdown(el, body, retryAfter, resetAt) {
    const endTime = resetAt * 1000; // convert to ms
    const startTime = Date.now();
    const totalMs = endTime - startTime;

    el.classList.add('rate-limited');
    body.innerHTML = '<div class="rate-limit-banner"></div>';
    const banner = $('.rate-limit-banner', body);

    function formatMs(ms) {
      if (ms <= 0) return '0s';
      const s = Math.ceil(ms / 1000);
      if (s < 60) return s + 's';
      const m = Math.floor(s / 60);
      const rem = s % 60;
      return rem ? m + 'm ' + rem + 's' : m + 'm';
    }

    function render() {
      const now = Date.now();
      const remaining = endTime - now;
      if (remaining <= 0) {
        banner.innerHTML = '<span class="ready">✓ Ready to try again</span>';
        banner.classList.add('ready');
        el.classList.remove('rate-limited');
        status('ready');
        return;
      }
      const pct = Math.max(0, Math.min(100, (remaining / totalMs) * 100));
      banner.innerHTML = '<div class="rl-progress"><i style="width:' + pct + '%"></i></div>' +
        '<div class="rl-text">' +
          '<span class="rl-icon">⏳</span>' +
          '<span class="rl-msg">Rate limited — retry in <strong>' + formatMs(remaining) + '</strong></span>' +
        '</div>';
      msgs.scrollTop = msgs.scrollHeight;
    }

    render();
    const timer = setInterval(() => {
      if (Date.now() >= endTime) {
        clearInterval(timer);
        render();
      } else {
        render();
      }
    }, 1000);

    el.dataset.rlTimer = timer;
  }

  async function sendMessage(text) {
    if (!text || busy) return;
    busy = true;
    abortCtrl = new AbortController();
    sendBtn.hidden = true; stopBtn.hidden = false;
    sendBtn.setAttribute('aria-busy', 'true');
    input.value = '';
    autosize();
    addMsg('user', text, false, 'you');
    const el = addMsg('assistant', '<span class="thinking"><i></i>thinking…</span>', true);
    const body = $('.msg-body', el);
    status('thinking…');

    let acc = '', applied = [], lastRender = 0, pendingNow = false, truncated = false;
    try {
      const res = await fetch(apiUrl('api/projects/' + PID + '/chat'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
        body: JSON.stringify({
          message: text,
          session_id: SID,
          skills: skillIds.slice(),
          attachments: atts.filter((a) => !a.uploading).map((a) => ({ path: a.path, image: a.image })),
        }),
        signal: abortCtrl.signal
      });
      if (!res.ok) {
        let err; try { err = (await res.json()).error; } catch (e) {}
        throw new Error(err || 'HTTP ' + res.status);
      }
      const reader = res.body.getReader();
      const dec = new TextDecoder();
      let buffer = '';
      let thinking = '';
      let thinkTick = 0;
      const handle = (event, data) => {
        if (event === 'start') {
          if (data.model) { status('model: ' + data.model); }
          if (data.session_id) SID = data.session_id;
          if (data.context) renderContext(data.context);
        }
        else if (event === 'reasoning') {
          // the model is thinking out loud before it writes anything — show it
          // so a slow model never looks like a frozen page
          if (acc) return;
          thinking += data.text || '';
          status('thinking…');
          if (++thinkTick % 2 === 0) {
            const tail = thinking.replace(/\s+/g, ' ').trim().slice(-140);
            body.innerHTML = '<span class="thinking"><i></i>' + esc(tail) + '</span>';
            msgs.scrollTop = msgs.scrollHeight;
          }
        }
        else if (event === 'delta') {
          acc += data.text;
          const now = Date.now();
          if (now - lastRender > 70) { body.innerHTML = md(acc) + '<span class="caret"></span>'; lastRender = now; msgs.scrollTop = msgs.scrollHeight; }
          status('writing…');
        }
        else if (event === 'error') {
          if (data.rate_limited && data.retry_after) {
            showRateLimitCountdown(el, body, data.retry_after, data.reset_at);
            return;
          }
          throw new Error(data.error);
        }
        else if (event === 'files') {
          const changes = data.changes || [];
          if (data.truncated) truncated = true;
          if (data.pending && changes.length) {
            pendingNow = true; setPending(changes);
            el.appendChild(changesCard(changes, 'pending'));
          } else if ((data.applied || []).length) {
            applied = (data.applied || []).map((a) => a.path);
            el.appendChild(changesCard(changes, 'applied', applied));
          } else if (changes.length && !data.pending) {
            applied = changes.map((c) => c.path);
            el.appendChild(changesCard(changes, 'applied', applied));
          }
        }
        else if (event === 'done') {
          if (data.truncated) truncated = true;
          if (!applied.length && !pendingNow) applied = data.files || [];
        }
      };
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        buffer = parseSSE(buffer + dec.decode(value, { stream: true }), handle);
      }
      parseSSE(buffer, handle);

      body.innerHTML = md(acc);
      if (applied.length) chipRow(el, applied);
      clearAttachments();
      skillIds = [];
      renderSkillChips();
      loadTree();
      if (running) reloadPreview();
      if (pendingNow) {
        status(pendingChanges.length + ' change(s) to review');
        toast(pendingChanges.length + ' recommended change(s) — review them in chat', 'ok');
      } else if (applied.length) {
        status(applied.length + ' file(s) written');
        toast(applied.length + ' file(s) written', 'ok');
      } else if (truncated) {
        status('cut off');
        toast('The answer ran out of room before the code finished — ask it to continue', 'err');
      } else if (!/\b(wrote|created|added|updated|changed|appended|removed|edited)\b/i.test(acc)) {
        status('ready');
      } else {
        // the bot described changing something but produced no files
        status('no files changed');
        toast('No files were changed — ask it to reply with the full code block', 'err');
      }
      loadSessionsSilently();
    } catch (err) {
      if (err.name === 'AbortError') {
        body.innerHTML = md(acc || '_(stopped)_');
        status('stopped');
      } else {
        el.classList.add('error');
        $('.msg-role', el).textContent = 'error';
        body.textContent = err.message;
        status('error');
        toast(err.message, 'err');
      }
    } finally {
      busy = false; abortCtrl = null;
      sendBtn.hidden = false; sendBtn.removeAttribute('aria-busy'); stopBtn.hidden = true;
      msgs.scrollTop = msgs.scrollHeight;
    }
  }

  $('#chat-form').onsubmit = (e) => { e.preventDefault(); sendMessage(input.value.trim()); };
  stopBtn.onclick = () => { if (abortCtrl) abortCtrl.abort(); };

  function autosize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 220) + 'px';
    syncComposerH();
  }

  /* Keep the @ / / popmenus and the drop overlay anchored just above the composer.
     On phones the composer is taller (bigger buttons), so a fixed offset would overlap. */
  const composerEl = $('.composer');
  function syncComposerH() {
    if (!composerEl) return;
    const h = composerEl.offsetHeight;
    if (!h) return; // not laid out yet — keep the CSS default
    document.documentElement.style.setProperty('--composer-h', h + 'px');
  }
  syncComposerH();
  window.addEventListener('resize', syncComposerH);
  if (window.ResizeObserver) {
    try { new window.ResizeObserver(syncComposerH).observe(composerEl); } catch (e) {}
  }

  input.addEventListener('input', () => { autosize(); onComposerTyping(); });
  input.addEventListener('keydown', (e) => {
    if (onComposerKey(e)) { e.preventDefault(); return; }
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(input.value.trim()); }
  });

  const clearBtn = $('#btn-clear');
  if (clearBtn) clearBtn.onclick = async () => {
    if (!confirm('Clear chat history?')) return;
    try {
      await api('api/projects/' + PID + '/clear', { method: 'POST', body: JSON.stringify({ session_id: SID }) });
      $$('.msg', msgs).forEach((m) => m.remove());
      addMsg('system', 'Chat cleared.', false);
      toast('Chat cleared', 'ok');
    } catch (e) { toast(e.message, 'err'); }
  };

  /* ================= inline diff approval ================= */
  function diffHtml(rows) {
    if (!rows || !rows.length) return '<div class="chg-empty muted tiny">no textual changes</div>';
    let out = '<div class="diff">';
    let shown = 0;
    for (const r of rows) {
      if (shown++ > 500) { out += '<div class="dl ctx"><span class="ln">…</span><span class="sign"> </span>(diff truncated)</div>'; break; }
      const cls = r.t === '+' ? 'add' : r.t === '-' ? 'del' : 'ctx';
      const num = (r.t === '-' ? r.a : r.b) || '';
      out += '<div class="dl ' + cls + '"><span class="ln">' + num + '</span><span class="sign">' + esc(r.t) + '</span><span class="tx">' + esc(r.s) + '</span></div>';
    }
    return out + '</div>';
  }

  function changesCard(changes, mode, appliedPaths) {
    const card = document.createElement('div');
    card.className = 'toolcard ' + mode;
    const totalA = changes.reduce((s, c) => s + (c.added || 0), 0);
    const totalR = changes.reduce((s, c) => s + (c.removed || 0), 0);
    const title = mode === 'pending' ? 'Approve these changes?' : 'Wrote files';
    const many = changes.length > 1;
    const actions = mode === 'pending'
      ? '<span class="tc-actions">' +
          (many ? '<button class="btn tiny ghost" data-act="reject">Reject all</button>' : '') +
          '<button class="btn tiny danger" data-act="reject">Reject</button>' +
          (many ? '<button class="btn tiny primary ghost" data-act="applyall">Apply all</button>' : '') +
          '<button class="btn tiny primary" data-act="apply">Apply</button>' +
        '</span>'
      : '<span class="tc-actions"><span class="tc-ok">&#10003; applied</span></span>';

    card.innerHTML =
      '<div class="tc-head">' +
        '<span class="tc-ico">&#9998;</span>' +
        '<span class="tc-title">' + title + '</span>' +
        '<span class="tc-meta">' + changes.length + ' file' + (changes.length === 1 ? '' : 's') +
          ' <span class="plus">+' + totalA + '</span> <span class="minus">&minus;' + totalR + '</span></span>' +
        actions +
        '<button class="btn tiny ghost tc-toggle" data-act="toggle" title="Collapse / expand">&#9662;</button>' +
      '</div>' +
      '<div class="tc-body">' +
        changes.map((c, i) =>
          '<div class="chg-file" data-i="' + i + '">' +
            '<div class="chg-head">' +
              (mode === 'pending' ? '<input type="checkbox" checked data-p="' + esc(c.path) + '">' : '<span class="chg-check">&#10003;</span>') +
              '<span class="chg-tag ' + esc(c.status) + '">' + esc(c.status) + '</span>' +
              '<span class="chg-path">' + esc(c.path) + '</span>' +
              '<span class="chg-stat"><span class="plus">+' + (c.added || 0) + '</span> <span class="minus">&minus;' + (c.removed || 0) + '</span></span>' +
            '</div>' +
            (c.status === 'unchanged' ? '<div class="chg-empty muted tiny">unchanged</div>' : diffHtml(c.diff)) +
            (c.diff_capped ? '<div class="chg-empty muted tiny">diff shortened for speed — open the file to see it all</div>' : '') +
          '</div>').join('') +
      '</div>';

    const body = $('.tc-body', card);
    $('[data-act=toggle]', card).onclick = () => { body.hidden = !body.hidden; $('[data-act=toggle]', card).classList.toggle('closed', body.hidden); };
    $$('.chg-head', card).forEach((h) => h.onclick = (e) => {
      if (e.target.tagName !== 'INPUT') { const cb = $('input', h); if (cb) cb.checked = !cb.checked; }
    });
    if (mode === 'pending') {
      const apply = $('[data-act=apply]', card);
      const reject = $('[data-act=reject]', card);
      const applyAll = $('[data-act=applyall]', card);
      const rejectAll = $$('[data-act=reject]', card)[1];
      apply.onclick = () => {
        const paths = $$('input[type=checkbox]:checked', card).map((c) => c.dataset.p);
        if (!paths.length) { toast('Tick at least one file to apply', 'err'); return; }
        changesAction('apply', paths, card);
      };
      reject.onclick = () => changesAction('reject', null, card);
      // "Apply all"/"Reject all" ignore the per-file ticks
      if (applyAll) applyAll.onclick = () =>
        changesAction('apply', changes.map((c) => c.path).filter(Boolean), card);
      if (rejectAll) rejectAll.onclick = () => changesAction('reject', null, card);
    }
    return card;
  }

  function setPending(list) {
    pendingChanges = list || [];
    const b = $('#btn-pending');
    if (b) { b.hidden = !pendingChanges.length; const c = $('#pending-count'); if (c) c.textContent = pendingChanges.length; }
  }

  async function loadPending() {
    try {
      const d = await api('api/projects/' + PID + '/changes');
      const list = d.changes || [];
      setPending(list);
      if (list.length) {
        const wrapper = document.createElement('div');
        wrapper.className = 'msg assistant';
        wrapper.innerHTML = '<div class="msg-role">pending changes</div>';
        wrapper.appendChild(changesCard(list, 'pending'));
        msgs.appendChild(wrapper);
        msgs.scrollTop = msgs.scrollHeight;
        status(list.length + ' recommended change(s) to review on reload — look at the chat');
      }
    } catch (e) { setPending([]); }
  }

  function markCard(card, state) {
    card.classList.remove('pending');
    card.classList.add(state);
    const actions = $('.tc-actions', card);
    if (actions) {
      if (state === 'applied') actions.innerHTML = '<span class="tc-ok">&#10003; applied</span>';
      else if (state === 'rejected') actions.innerHTML = '<span class="tc-rej">&#10007; rejected</span>';
    }
  }

  async function changesAction(action, paths, card) {
    const body = { action };
    if (paths && paths.length) body.paths = paths;
    if (action === 'apply' && (!paths || !paths.length) && card) { toast('Nothing selected'); return; }
    try {
      const d = await api('api/projects/' + PID + '/changes', { method: 'POST', body: JSON.stringify(body) });
      if (action === 'apply') {
        const n = (d.applied || []).length;
        toast(n + ' file(s) written', 'ok'); status(n + ' file(s) written');
        if (card) markCard(card, 'applied');
        setPending([]);
        loadTree();
        if (running) reloadPreview();
      } else {
        toast('Changes rejected', 'ok');
        status('ready');
        if (card) markCard(card, 'rejected');
        setPending([]);
      }
    } catch (e) { toast(e.message, 'err'); }
  }

  const pendingBtn = $('#btn-pending');
  if (pendingBtn) pendingBtn.onclick = () => {
    const cards = $$('.toolcard.pending');
    if (cards.length) cards[cards.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  /* ================= sessions ================= */
  function renderSessions() {
    const sel = $('#sess-select');
    if (!sel) return;
    sel.innerHTML = '';
    if (!sessions.length) { const o = document.createElement('option'); o.textContent = 'no chats'; o.disabled = true; sel.appendChild(o); return; }
    sessions.forEach((sess) => {
      const o = document.createElement('option');
      o.value = sess.id;
      o.textContent = (sess.title || 'Chat') + '  (' + (sess.messages || 0) + ')';
      if (sess.id === SID) o.selected = true;
      sel.appendChild(o);
    });
  }

  async function loadSessions(prefer) {
    try {
      const d = await api('api/projects/' + PID + '/sessions');
      sessions = d.sessions || [];
    } catch (e) { sessions = []; return SID; }
    let stored = null;
    try { stored = localStorage.getItem('oc-sess-' + PID); } catch (e) {}
    const pick = (sid) => sessions.find((x) => x.id === sid);
    SID = (prefer && pick(prefer)) ? prefer
      : (stored && pick(stored)) ? stored
      : (sessions[0] && sessions[0].id) || null;
    if (SID) { try { localStorage.setItem('oc-sess-' + PID, SID); } catch (e) {} }
    renderSessions();
    return SID;
  }

  async function loadSessionsSilently() {
    const cur = SID;
    await loadSessions(cur);
    renderSessions();
  }

  async function activateSession(sid) {
    if (!sid || sid === SID) return;
    SID = sid;
    try { localStorage.setItem('oc-sess-' + PID, sid); } catch (e) {}
    renderSessions();
    $$('.msg', msgs).forEach((m) => m.remove());
    await loadMessages();
  }

  async function newSession() {
    try {
      const d = await api('api/projects/' + PID + '/sessions', { method: 'POST', body: JSON.stringify({}) });
      await loadSessions(d.session.id);
      $$('.msg', msgs).forEach((m) => m.remove());
      addMsg('system', 'New conversation started — earlier chats stay in the list above.', false);
      renderSessions();
      input.focus();
    } catch (e) { toast(e.message, 'err'); }
  }

  const sessSel = $('#sess-select');
  if (sessSel) sessSel.onchange = () => activateSession(sessSel.value);

  /* ================= apply mode ================= */
  function renderPerm() {
    const b = $('#perm-pill');
    if (!b) return;
    b.innerHTML = applyMode === 'auto' ? 'auto &#9662;' : 'ask &#9662;';
    b.classList.toggle('auto', applyMode === 'auto');
    b.title = applyMode === 'auto'
      ? 'File changes apply automatically — click to review changes first'
      : 'You review changes before they are written — click to apply automatically';
  }
  async function loadProjectMeta() {
    try {
      const d = await api('api/projects/' + PID);
      applyMode = d.project.apply_mode === 'auto' ? 'auto' : 'ask';
    } catch (e) {}
    renderPerm();
  }
  const permBtn = $('#perm-pill');
  if (permBtn) permBtn.onclick = async () => {
    applyMode = applyMode === 'auto' ? 'ask' : 'auto';
    try {
      await api('api/projects/' + PID, { method: 'POST', body: JSON.stringify({ apply_mode: applyMode }) });
      renderPerm();
      toast(applyMode === 'auto' ? 'Changes apply automatically' : 'You review changes first');
    } catch (e) { toast(e.message, 'err'); }
  };

  /* ================= attachments ================= */
  const attInput = $('#attach-input');
  const attBtn = $('#btn-attach');
  if (attBtn) attBtn.onclick = () => attInput.click();
  if (attInput) attInput.onchange = async (e) => {
    if (e.target.files && e.target.files.length) await addFiles(e.target.files);
    e.target.value = '';
  };

  /* ================= context window meter ================= */
  function renderContext(c) {
    const fill = $('#ctx-fill'), txt = $('#ctx-text'), wrap = $('#ctx-meter');
    if (!fill || !txt) return;
    const k = (n) => n >= 1000 ? (n / 1000).toFixed(n >= 100000 ? 0 : 1) + 'k' : String(n);
    fill.style.width = Math.min(100, c.pct || 0) + '%';
    txt.textContent = 'ctx ' + k(c.est_tokens) + '/' + k(c.limit);
    if (wrap) {
      wrap.title = 'Context used: about ' + c.est_tokens.toLocaleString() + ' of ' +
        c.limit.toLocaleString() + ' tokens (' + (c.pct || 0) + '%). Long chats fill this up.';
      wrap.classList.toggle('warn', (c.pct || 0) >= 70);
      wrap.classList.toggle('full', (c.pct || 0) >= 90);
    }
  }

  /* ================= skills ================= */
  let SKILLS = [], skillIds = [];

  async function loadSkills() {
    try {
      const d = await api('api/projects/' + PID + '/skills');
      SKILLS = d.skills || [];
      renderSkillsPanel();
      renderSkillChips();
    } catch (e) { /* skills are optional; never block the chat */ }
  }

  function renderSkillChips() {
    const wrap = $('#skill-chips');
    if (!wrap) return;
    wrap.hidden = !skillIds.length;
    wrap.innerHTML = skillIds.map((id) => {
      const s = SKILLS.find((x) => x.id === id);
      const label = s ? s.icon + ' ' + s.name : id;
      return '<span class="skchip" data-id="' + esc(id) + '" title="Tap to remove">' +
        esc(label) + '<b>&#10005;</b></span>';
    }).join('');
    $$('.skchip', wrap).forEach((c) => c.onclick = () => {
      skillIds = skillIds.filter((x) => x !== c.dataset.id);
      renderSkillChips(); renderSkillsPanel();
    });
  }

  function renderSkillsPanel() {
    const wrap = $('#skills-list');
    if (!wrap) return;
    if (!SKILLS.length) { wrap.innerHTML = '<div class="muted tiny">No skills yet — add one below.</div>'; return; }
    wrap.innerHTML = SKILLS.map((s) => {
      const on = skillIds.includes(s.id);
      return '<div class="skrow' + (on ? ' on' : '') + '" data-id="' + esc(s.id) + '">' +
        '<button class="skmain" type="button">' +
          '<span class="skic">' + esc(s.icon) + '</span>' +
          '<span class="sktext"><b>' + esc(s.name) + '</b>' +
          (s.builtin ? '<em>built-in</em>' : '<em class="mine">yours</em>') +
          '<span class="skprev">' + esc(s.preview) + '…</span></span>' +
          '<span class="skact">' + (on ? 'Applied' : 'Apply') + '</span>' +
        '</button>' +
        (s.builtin ? '' : '<button class="skdel" type="button" title="Delete skill">&#10005;</button>') +
      '</div>';
    }).join('');

    $$('.skmain', wrap).forEach((b) => b.onclick = () => {
      const id = b.closest('.skrow').dataset.id;
      const s = SKILLS.find((x) => x.id === id);
      const had = skillIds.includes(id);
      skillIds = had ? skillIds.filter((x) => x !== id) : skillIds.concat([id]).slice(-8);
      renderSkillChips(); renderSkillsPanel();
      if (had && s) toast('Removed ' + s.name, 'ok');
      else if (s) toast(s.name + ' applied', 'ok');
    });
    $$('.skdel', wrap).forEach((b) => b.onclick = async (e) => {
      e.stopPropagation();
      const id = b.closest('.skrow').dataset.id;
      const s = SKILLS.find((x) => x.id === id);
      if (!confirm('Delete the skill "' + (s ? s.name : id) + '"?')) return;
      try {
        const d = await api('api/projects/' + PID + '/skills/' + encodeURIComponent(id), { method: 'DELETE' });
        SKILLS = d.skills || [];
        skillIds = skillIds.filter((x) => x !== id);
        renderSkillChips(); renderSkillsPanel();
        toast('Skill deleted', 'ok');
      } catch (err) { toast(err.message, 'err'); }
    });
  }

  function openSkills() {
    const dlg = $('#skills-dlg');
    if (!dlg) return;
    if (!dlg.open) dlg.showModal();
    loadSkills();
  }

  const skillsBtn = $('#btn-skills');
  if (skillsBtn) skillsBtn.onclick = openSkills;
  const skillsClose = $('#skills-close');
  if (skillsClose && $('#skills-dlg')) skillsClose.onclick = () => $('#skills-dlg').close();
  const skillsForm = $('#skills-form');
  if (skillsForm) skillsForm.onsubmit = async (e) => {
    e.preventDefault();
    const name = skillsForm.querySelector('[name=name]').value.trim();
    const body = skillsForm.querySelector('[name=body]').value.trim();
    if (!name || !body) return;
    const btn = skillsForm.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
      const d = await api('api/projects/' + PID + '/skills', { method: 'POST', body: JSON.stringify({ name, body }) });
      SKILLS = d.skills || [];
      skillsForm.reset();
      renderSkillsPanel();
      toast('Skill saved — ' + name, 'ok');
    } catch (err) { toast(err.message, 'err'); }
    finally { btn.disabled = false; }
  };

  function clearAttachments() {
    atts.forEach((a) => { if (a.url) URL.revokeObjectURL(a.url); });
    atts = [];
    renderAtts();
  }

  function renderAtts() {
    const box = $('#attach-chips');
    if (!box) return;
    box.hidden = !atts.length;
    box.innerHTML = '';
    atts.forEach((a, i) => {
      const c = document.createElement('span');
      c.className = 'attchip';
      c.innerHTML = (a.image && a.url) ? '<img alt="">' : '<span class="att-ic">&#128196;</span>';
      if (a.image && a.url) $('img', c).src = a.url;
      const nm = document.createElement('span');
      nm.className = 'att-name';
      nm.textContent = a.name;
      const x = document.createElement('span');
      x.className = 'att-x';
      x.innerHTML = '&#10005;';
      x.onclick = () => { atts.splice(i, 1); renderAtts(); };
      c.append(nm, x);
      box.appendChild(c);
    });
  }

  async function addFiles(fileList) {
    for (const f of fileList) {
      if (f.size > 8 * 1024 * 1024) { toast(f.name + ': larger than 8 MB', 'err'); continue; }
      const fd = new FormData();
      fd.append('files[]', f, f.name);
      try {
        const res = await fetch(apiUrl('api/projects/' + PID + '/upload'), {
          method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd,
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'HTTP ' + res.status);
        const first = (data.files || [])[0];
        if (!first) continue;
        if (first.error) { toast(first.error, 'err'); continue; }
        atts.push({ path: first.path, name: f.name, image: !!first.image, url: first.image ? URL.createObjectURL(f) : null });
        renderAtts();
        loadTree();
      } catch (e) { toast(e.message, 'err'); }
    }
  }

  const chatPane = $('#chat-pane');
  if (chatPane) {
    let depth = 0;
    chatPane.addEventListener('dragenter', (e) => {
      if (!Array.from(e.dataTransfer.types || []).includes('Files')) return;
      e.preventDefault(); depth++; $('#drop-overlay').hidden = false;
    });
    chatPane.addEventListener('dragover', (e) => { if (Array.from(e.dataTransfer.types || []).includes('Files')) e.preventDefault(); });
    chatPane.addEventListener('dragleave', () => { if (--depth <= 0) { depth = 0; $('#drop-overlay').hidden = true; } });
    chatPane.addEventListener('drop', async (e) => {
      e.preventDefault(); depth = 0; $('#drop-overlay').hidden = true;
      if (e.dataTransfer.files && e.dataTransfer.files.length) await addFiles(e.dataTransfer.files);
    });
  }

  /* ================= slash commands / mentions ================= */
  const COMMANDS = [
    { cmd: '/help', desc: 'List all commands', run: () => addMsg('system', 'Commands: ' + COMMANDS.map((c) => c.cmd).join('  ·  '), false) },
    { cmd: '/new', desc: 'Start a new conversation', run: newSession },
    { cmd: '/clear', desc: 'Clear this chat', run: () => $('#btn-clear').click() },
    { cmd: '/zip', desc: 'Download the project', run: () => { location.href = apiUrl('api/projects/' + PID + '/zip'); } },
    { cmd: '/model', desc: 'Open models & servers', run: () => $('#settings-btn').click() },
    { cmd: '/theme', desc: 'Toggle light / dark theme', run: () => { if (themeBtn) themeBtn.click(); } },
  ];
  const cmdMenu = $('#cmd-menu');
  const mentionMenu = $('#mention-menu');
  document.addEventListener('click', (e) => {
    if (!menuKind) return;
    const box = menuKind === 'cmd' ? cmdMenu : mentionMenu;
    if (box && !box.contains(e.target) && e.target !== input) hideMenus();
  });
  let menuSel = 0, menuItems = [], menuKind = null;

  function showMenu(kind, items) {
    menuKind = kind; menuItems = items; menuSel = 0;
    const box = kind === 'cmd' ? cmdMenu : mentionMenu;
    const other = kind === 'cmd' ? mentionMenu : cmdMenu;
    other.hidden = true;
    box.innerHTML = items.map((it, i) =>
      '<div class="pm-item' + (i === 0 ? ' sel' : '') + '" data-i="' + i + '">' +
      '<span class="pm-cmd">' + esc(it.label) + '</span>' +
      '<span class="pm-desc">' + esc(it.desc || '') + '</span>' +
      (it.hint ? '<span class="pm-hint">' + esc(it.hint) + '</span>' : '') + '</div>').join('');
    box.hidden = false;
    $$('.pm-item', box).forEach((el) => { el.onmousedown = (e) => { e.preventDefault(); pickMenu(+el.dataset.i); }; });
  }

  function hideMenus() {
    if (cmdMenu) cmdMenu.hidden = true;
    if (mentionMenu) mentionMenu.hidden = true;
    menuKind = null;
  }

  function moveMenu(delta) {
    if (!menuKind) return false;
    const box = menuKind === 'cmd' ? cmdMenu : mentionMenu;
    const items = $$('.pm-item', box);
    if (!items.length) return false;
    menuSel = (menuSel + delta + items.length) % items.length;
    items.forEach((el, i) => el.classList.toggle('sel', i === menuSel));
    items[menuSel].scrollIntoView({ block: 'nearest' });
    return true;
  }

  function pickMenu(i) {
    if (!menuKind) return;
    const it = menuItems[i !== undefined ? i : menuSel];
    if (!it) return;
    hideMenus();
    it.run();
  }

  function onComposerTyping() {
    const val = input.value;
    const before = val.slice(0, input.selectionStart);
    const slash = before.match(/(?:^)\/(\w*)$/);
    if (slash) {
      const q = slash[1].toLowerCase();
      const items = COMMANDS.filter((c) => c.cmd.slice(1).startsWith(q)).map((c) => ({ label: c.cmd, desc: c.desc, run: c.run }));
      if (items.length) { showMenu('cmd', items); return; }
    }
    const mention = before.match(/@([\w.\-\/]*)$/);
    if (mention && allFiles.length) {
      const q = mention[1].toLowerCase();
      const items = allFiles.filter((f) => f.toLowerCase().includes(q)).slice(0, 8)
        .map((f) => ({ label: f, desc: 'reference file', run: () => insertMention(f) }));
      if (items.length) { showMenu('mention', items); return; }
    }
    hideMenus();
  }

  function insertMention(path) {
    const pos = input.selectionStart;
    const before = input.value.slice(0, pos);
    const after = input.value.slice(pos);
    const replaced = before.replace(/@([\w.\-\/]*)$/, '@' + path + ' ');
    input.value = replaced + after;
    input.selectionStart = input.selectionEnd = replaced.length;
    input.focus();
    autosize();
  }

  function onComposerKey(e) {
    if (menuKind) {
      if (e.key === 'ArrowDown') return moveMenu(1);
      if (e.key === 'ArrowUp') return moveMenu(-1);
      if (e.key === 'Enter' || e.key === 'Tab') { pickMenu(); return true; }
      if (e.key === 'Escape') { hideMenus(); return true; }
    }
    return false;
  }

  /* ================= global shortcuts ================= */
  document.addEventListener('keydown', (e) => {
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.shiftKey && (e.key === 'P' || e.key === 'p')) {
      e.preventDefault();
      if (ovl && ovl.hidden) openPreview();
      else closePreview();
    } else if (mod && e.shiftKey && (e.key === 'R' || e.key === 'r')) {
      e.preventDefault();
      if (running) reloadPreview();
    } else if (e.key === 'Escape') {
      if (menuKind) { hideMenus(); return; }
      // don't yank the preview away while someone is typing in the chat
      const t = e.target;
      const typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);
      if (ovl && !ovl.hidden && !typing) closePreview();
    }
  });

  /* ================= proj switcher ================= */
  const projSel = $('#proj-select');
  if (projSel) projSel.onchange = () => { location.href = apiUrl('p/' + projSel.value); };

  /* ================= init ================= */
  loadTree(); // first, so the file list never ends up empty
  loadProjectMeta();
  loadSessions().then(() => loadMessages()).catch(() => loadMessages());
  loadPending();
  loadSkills();
  // reflect whether a preview server is already running for this project
  api('api/projects/' + PID + '/server', { method: 'POST', body: JSON.stringify({ action: 'status' }) })
    .then((d) => setRunUI(d.server)).catch(() => {});
  api('api/providers').then((d) => {
    const hasKey = (d.providers || []).some((p) => p.api_key_set);
    const hasKeyless = (d.providers || []).some((p) => p.keyless);
    if (!hasKey && !hasKeyless) {
      const banner = document.createElement('div');
      banner.className = 'card nokey-banner';
      banner.innerHTML = '<strong>No AI provider configured.</strong> Open <a href="#" id="nokey-settings">Settings &rarr; Add server</a> and pick a free option (no credit card needed).';
      const pane = document.querySelector('.chat-pane');
      if (pane) pane.insertBefore(banner, pane.querySelector('.messages'));
      const link = $('#nokey-settings');
      if (link) link.onclick = (ev) => { ev.preventDefault(); const b = $('#settings-btn'); if (b) b.click(); };
    }
  }).catch(() => {});
})();