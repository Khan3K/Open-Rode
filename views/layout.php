<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'OpenRode — build a website by chatting') ?></title>
<meta name="base" content="<?= e(base_path()) ?>">
<script>try{var t=localStorage.getItem('oc-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#9670;</text></svg>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= e(url('')) ?>">
    <span class="logo">&#9670;</span>
    <span class="brand-text">Open<span class="accent">Rode</span></span>
  </a>
  <div class="topbar-right">
    <label class="model-pick" title="Active model">
      <span class="mp-dot"></span>
      <select id="model-switch"><option>loading…</option></select>
    </label>
    <button class="btn ghost icon-btn" id="theme-btn" title="Toggle light / dark theme (Ctrl+Shift+T)">&#9680;</button>
    <button class="btn ghost" id="settings-btn" title="AI servers &amp; models">&#9881; Settings</button>
  </div>
</header>

<dialog id="settings-dialog">
  <div class="dlg">
    <div class="dlg-head">
      <h3>AI servers &amp; models</h3>
      <button class="btn tiny ghost" id="dlg-close" type="button">&#10005;</button>
    </div>

    <div class="dlg-tabs" id="dlg-tabs">
      <button class="dtab active" data-dtab="models">Models</button>
      <button class="dtab" data-dtab="servers">Servers</button>
      <button class="dtab" data-dtab="add">Add server</button>
    </div>

    <div class="dpane" data-dpane="models">
      <div class="active-card" id="active-card">…</div>
      <div class="card free-rank-card">
        <div class="fk-title">Free models</div>
        <p class="muted small free-rank-sub"><b>free</b> works with no key. <b>needs key</b> models unlock with a free <a href="https://opencode.ai/zen" target="_blank" rel="noopener">OpenCode Zen</a> key — click one and we'll set it up for you.</p>
        <div class="free-rank" id="free-rank"></div>
      </div>
      <div class="model-groups" id="model-groups"></div>
    </div>

    <div class="dpane" data-dpane="servers" hidden>
      <div id="provider-list" class="provider-list"></div>
    </div>

    <div class="dpane" data-dpane="add" hidden>
      <div class="card freekeys-card">
        <div class="fk-title">Get a free API key (2 min, no credit card)</div>
        <div class="fk-grid">
          <a class="fk-item" href="https://console.groq.com/keys" target="_blank" rel="noopener"><span class="fk-name">Groq</span><span class="fk-desc">Very fast, free tier</span></a>
          <a class="fk-item" href="https://openrouter.ai/keys" target="_blank" rel="noopener"><span class="fk-name">OpenRouter</span><span class="fk-desc">Free models, one key</span></a>
          <a class="fk-item" href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener"><span class="fk-name">Google Gemini</span><span class="fk-desc">Generous free tier</span></a>
          <a class="fk-item" href="https://github.com/settings/tokens" target="_blank" rel="noopener"><span class="fk-name">GitHub Models</span><span class="fk-desc">Free with GitHub account</span></a>
        </div>
      </div>
      <div class="preset-row">
        <span class="muted small">Quick add:</span>
        <div class="preset-chips" id="preset-chips"></div>
      </div>
      <form id="provider-form">
        <label>Name <input name="name" required placeholder="My OpenRouter" maxlength="60"></label>
        <label>Base URL <input name="base_url" required placeholder="https://openrouter.ai/api/v1"></label>
        <label>API key <input name="api_key" type="password" autocomplete="off" placeholder="sk-… (leave empty if none)"> <a id="get-key-link" href="#" target="_blank" rel="noopener" style="display:none;font-size:11px">Get free key</a></label>
        <label>API type
          <select name="kind">
            <option value="openai">OpenAI-compatible (OpenAI, OpenRouter, Groq, Ollama, …)</option>
            <option value="anthropic">Anthropic native (Claude)</option>
          </select>
        </label>
        <label>Models <span class="muted small">(one per line — or use “Fetch models” later)</span>
          <textarea name="models" rows="3" placeholder="gpt-4o-mini&#10;llama-3.3-70b-versatile"></textarea>
        </label>
        <div class="row end">
          <button class="btn primary" type="submit">Add server</button>
        </div>
        <p class="error" id="provider-form-error"></p>
      </form>
    </div>
  </div>
</dialog>

<div id="toasts"></div>

<main class="page">
<?= $content ?>
</main>
<script src="<?= e(asset('assets/app.js')) ?>"></script>
</body>
</html>
