<section class="home">
  <div class="home-hero">
    <div class="hh-left">
      <div class="kicker">&#9670; AI website builder</div>
      <h1>Build a website by chatting</h1>
      <p class="muted hero-sub">Describe what you want in plain words. The AI writes the files, you approve the changes, and download it when it looks right.</p>
      <div class="hh-cta">
        <button class="btn primary lg" id="new-project-btn">+ New project</button>
        <span class="muted small hide-sm">or pick a starting point below</span>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true">
      <div class="art-window">
        <div class="art-bar"><span></span><span></span><span></span></div>
        <div class="art-lines"><i style="width:70%"></i><i style="width:45%"></i><i style="width:60%"></i></div>
        <div class="art-block"></div>
        <div class="art-lines"><i style="width:55%"></i><i style="width:35%"></i></div>
      </div>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="card notif-error"><strong><?= e($error) ?></strong> &mdash; this link may point to a deleted project. Create a new one below.</div>
  <?php endif; ?>

  <div class="templates" id="templates">
    <span class="tpl-label muted small">Start with a template</span>
    <button class="tpl" data-tpl="portfolio">
      <span class="tpl-ic">&#127912;</span>
      <span class="tpl-txt"><b>Portfolio</b><span class="tpl-d">Show off your work</span></span>
    </button>
    <button class="tpl" data-tpl="landing">
      <span class="tpl-ic">&#128640;</span>
      <span class="tpl-txt"><b>Landing page</b><span class="tpl-d">Promote a product</span></span>
    </button>
    <button class="tpl" data-tpl="business">
      <span class="tpl-ic">&#127970;</span>
      <span class="tpl-txt"><b>Business site</b><span class="tpl-d">A simple company site</span></span>
    </button>
  </div>

  <form class="card new-project" id="new-project-form" hidden>
    <div class="np-head">
      <h3>New project</h3>
      <button type="button" class="btn tiny ghost" id="cancel-project" title="Close">&#10005;</button>
    </div>
    <div class="row">
      <label>Project name
        <input name="name" required placeholder="e.g. Portfolio site" maxlength="80" autocomplete="off">
      </label>
      <label>Type
        <select name="type">
          <option value="php">PHP site (index.php)</option>
          <option value="static">Static HTML</option>
        </select>
      </label>
    </div>
    <label>What should it be? <span class="muted small">— optional, tell the AI</span>
      <input name="description" placeholder="A landing page with a pricing table and a contact form" maxlength="200" autocomplete="off">
    </label>
    <div class="row end">
      <button type="submit" class="btn primary">Create project &rarr;</button>
    </div>
    <p class="error" id="project-form-error"></p>
  </form>

  <div class="section-head">
    <h2>Your projects</h2>
    <span class="muted small" id="proj-count"><?= count($projects) ?> project<?= count($projects) === 1 ? '' : 's' ?></span>
  </div>

  <div class="grid" id="project-grid">
    <?php foreach ($projects as $p): ?>
      <a class="card project-card" href="<?= e(url('p/' . $p['id'])) ?>">
        <div class="pc-preview type-<?= e($p['type']) ?>">
          <span class="pc-badge"><span class="dot"></span><?= e($p['type'] === 'php' ? 'PHP site' : 'Static site') ?></span>
        </div>
        <div class="pc-body">
          <div class="project-top">
            <h3><?= e($p['name']) ?></h3>
            <button class="btn tiny danger pc-del" data-del="<?= e($p['id']) ?>" title="Delete project">&#10005;</button>
          </div>
          <p class="muted small desc"><?= e($p['description'] ?: 'No description yet') ?></p>
          <div class="project-meta muted tiny">
            <span><?= (int)project_file_count($p['id']) ?> files</span>
            <span>&middot;</span>
            <span><?= e(substr((string)$p['updated_at'], 0, 16)) ?></span>
            <span class="pc-open">Open &rarr;</span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$projects): ?>
    <div class="empty">
      <div class="empty-ic">&#9670;</div>
      <h3>No projects yet</h3>
      <p class="muted">Create your first project, or start from a template above — it takes a few seconds.</p>
      <button class="btn primary" id="empty-new-btn">+ Create your first project</button>
    </div>
  <?php endif; ?>

  <footer class="home-foot muted tiny">
    Runs 100% on PHP &middot; chat to build &middot; download any project as a zip
  </footer>
</section>
