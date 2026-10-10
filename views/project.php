<section class="workspace" data-project="<?= e($project['id']) ?>">

  <header class="ws-toolbar">
    <a class="btn ghost icon-btn" href="<?= e(url('')) ?>" title="All projects (Home)">&#8592;</a>
    <label class="proj-pick" title="Switch project">
      <select id="proj-select">
        <?php foreach (($projects ?? []) as $pr): ?>
          <option value="<?= e($pr['id']) ?>" <?= $pr['id'] === $project['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <span class="muted tiny ws-desc hide-md" id="ws-desc" title="<?= e($project['description']) ?>"><?= e($project['description'] ?: '') ?></span>

    <div class="project-actions">
      <button class="btn ghost" id="btn-skills" title="Attach skills that guide how the site is built">&#9889; Skills</button>
      <button class="btn ghost" id="btn-undo-all" title="Undo the last change (most recent file)" disabled>&#8630; Undo</button>
      <button class="btn ghost" id="btn-redo-all" title="Redo the last undone change" disabled>&#8631; Redo</button>
      <button class="btn" id="btn-preview" title="See the live website (Ctrl+Shift+P)">&#9654; Preview</button>
      <a class="btn accent" id="btn-zip" href="<?= e(url('api/projects/' . $project['id'] . '/zip')) ?>" title="Download this project as a zip">&#11015; Download</a>
    </div>
  </header>

  <div class="panes" id="panes">
    <!-- ============ LEFT: files ============ -->
    <aside class="pane side-pane" id="side-pane">
      <div class="pane-head">
        <span>Files</span>
        <span class="head-actions">
          <button class="btn tiny" id="btn-new-file" title="New file">&#43;</button>
          <button class="btn tiny" id="btn-new-dir" title="New folder">&#128193;</button>
          <button class="btn tiny ghost" id="btn-refresh" title="Reload files">&#8635;</button>
        </span>
      </div>
      <div class="file-tree" id="file-tree">
        <div class="tree-empty muted tiny">loading&hellip;</div>
      </div>
    </aside>

    <div class="splitter" data-split="left" title="Drag to resize"></div>

    <!-- ============ RIGHT: chat ============ -->
    <section class="pane chat-pane" id="chat-pane">
      <div class="pane-head">
        <span class="chat-head-title">Chat</span>
        <span class="head-actions">
          <label class="sess-pick" title="Chat session">
            <select id="sess-select"></select>
          </label>
          <button class="btn tiny ghost" id="perm-pill" title="ask = review changes before writing · auto = write silently">ask &#9662;</button>
          <button class="btn tiny ghost" id="btn-pending" title="Recommended changes" hidden>&#9998; <span id="pending-count">0</span></button>
          <button class="btn tiny ghost" id="btn-clear" title="Clear this chat">&#128465;</button>
          <span class="ctx-meter" id="ctx-meter" title="How much of the model's context window this conversation uses"><span class="ctx-bar"><i id="ctx-fill"></i></span><span id="ctx-text">—</span></span>
          <span class="muted tiny" id="agent-status">ready</span>
        </span>
      </div>

      <div class="messages" id="messages">
        <div class="msg system">
          <div class="msg-role">welcome</div>
          <div class="msg-body md">Describe the site you want — <b>"a portfolio with a hero and three projects"</b>. The bot writes the files as easy <b>changes</b> you can approve. <b>Click any file</b> to reference it in this chat, then tell the bot what to fix. Press <b>Preview</b> any time to watch the site live. <kbd>@</kbd> mention a file &middot; <kbd>/</kbd> commands</div>
        </div>
      </div>

      <form class="composer" id="chat-form">
        <div class="skill-chips" id="skill-chips" hidden></div>
        <div class="attach-chips" id="attach-chips" hidden></div>
        <div class="composer-box">
          <textarea id="chat-input" rows="2" placeholder="Tell me what your website should do&hellip;"></textarea>
        </div>
        <div class="composer-row">
          <span class="composer-left">
            <button class="btn ghost attach-btn" id="btn-attach" type="button" title="Attach files or images" aria-label="Attach files">
              <span class="attach-ic" aria-hidden="true">&#128206;</span><span class="attach-label">Add files</span>
            </button>
            <span class="muted tiny kbd-hint hide-md"><kbd>Enter</kbd> send &middot; <kbd>@</kbd> files &middot; <kbd>/</kbd> commands</span>
          </span>
          <span class="composer-actions">
            <button class="btn danger" id="stop-btn" type="button" hidden>&#9632; Stop</button>
            <button class="btn primary" id="send-btn" type="submit">Send &#10148;</button>
          </span>
        </div>
        <input type="file" id="attach-input" multiple hidden>
      </form>

      <div class="popmenu" id="cmd-menu" hidden></div>
      <div class="popmenu" id="mention-menu" hidden></div>
      <div class="drop-overlay" id="drop-overlay" hidden>
        <div class="drop-card">
          <span class="drop-ic" aria-hidden="true">&#128229;</span>
          <b>Drop files to attach</b>
          <span class="muted tiny">Images, PDF or code &mdash; they get added to your project</span>
        </div>
      </div>
<!-- ============ skills ============ -->
  <dialog id="skills-dlg" class="skills-dlg">
    <div class="hist-head">
      <span class="hist-title">Skills</span>
      <span class="muted tiny">Tap to apply to your next message</span>
      <button class="btn tiny ghost" id="skills-close" title="Close">&#10005;</button>
    </div>
    <div class="skills-body">
      <div class="skills-list" id="skills-list"></div>
      <form class="skills-form" id="skills-form">
        <div class="skills-form-head">Add your own skill</div>
        <label>Name <input name="name" maxlength="60" placeholder="My house style" required></label>
        <label>What should the bot always do? <textarea name="body" rows="3" maxlength="8000" placeholder="e.g. Use a warm palette, rounded corners, and never use lorem ipsum." required></textarea></label>
        <div class="skills-form-actions">
          <button class="btn primary" type="submit">Save skill</button>
        </div>
      </form>
    </div>
  </dialog>

<!-- ============ version history ============ -->
  <dialog id="hist-dlg" class="hist-dlg">
    <div class="hist-head">
      <span id="hist-title" class="hist-title">History</span>
      <button class="btn tiny ghost" id="hist-close" title="Close">&#10005;</button>
    </div>
    <div class="hist-body" id="hist-list"></div>
  </dialog>

</section>
  </div>

  <!-- ============ live preview (opt-in overlay) ============ -->
  <div class="preview-ovl" id="preview-ovl" hidden>
    <div class="ovl-head">
      <span class="ovl-title">Live preview</span>
      <span class="pv-state" id="pv-state">stopped</span>
      <span class="ovl-tools">
        <button class="btn tiny ghost" id="btn-ovl-chat" title="Show or hide the chat beside the preview" aria-pressed="true">Chat</button>
        <button class="btn tiny ghost" id="btn-pick" title="Pick a part of the page, then ask the bot to change it" aria-pressed="false">&#9678; Pick</button>
        <span class="seg" id="device-seg" title="Preview width">
          <button class="seg-btn" data-w="375" title="Phone">Phone</button>
          <button class="seg-btn" data-w="768" title="Tablet">Tablet</button>
          <button class="seg-btn active" data-w="0" title="Full width">Full</button>
        </span>
        <button class="btn tiny ghost" id="btn-reload" title="Reload preview (Ctrl+Shift+R)">&#8635;</button>
        <button class="btn tiny ghost" id="btn-open" title="Open in new tab">&#8599;</button>
        <button class="btn tiny ghost" id="btn-preview-close" title="Close (Esc)">&#10005;</button>
        <button class="btn tiny ghost" id="btn-pv-stop" title="Stop the preview server">&#9632; Stop</button>
      </span>
    </div>
    <div class="ovl-body" id="ovl-body">
      <!-- file tree moves here while the preview is open -->
      <div class="ovl-col ovl-left" id="ovl-left"></div>
      <!-- the rendered site -->
      <div class="ovl-col ovl-site" id="ovl-site">
        <div class="preview-stage" id="preview-stage">
          <iframe id="preview-frame" title="Live website preview"></iframe>
          <div class="preview-empty" id="preview-empty">
            <div class="preview-empty-box">
              <div class="empty-ic">&#9654;</div>
              <h3>Your website appears here</h3>
              <p class="muted">Press <b>Start preview</b> and OpenRode runs your project so you can watch it live. Ask for a change and hit <b>Apply</b> &mdash; this refreshes itself.</p>
              <button class="btn primary" id="btn-run-empty">Start preview</button>
            </div>
          </div>
        </div>
      </div>
      <!-- the working chat moves here while the preview is open -->
      <div class="ovl-col ovl-chat" id="ovl-chat"></div>
    </div>
    <button class="chat-restore" id="btn-chat-restore" type="button">Show chat</button>
  </div>

</section>

<!-- ============ new file / folder ============ -->
<dialog id="newpath-dlg">
  <div class="dlg small-dlg">
    <div class="dlg-head"><h3 id="np-title">New file</h3></div>
    <label>Path <span class="muted tiny" id="np-hint">e.g. pages/about.php</span>
      <input id="np-input" placeholder="e.g. about.php" autocomplete="off" spellcheck="false">
    </label>
    <div class="row end">
      <button class="btn ghost" id="np-cancel">Cancel</button>
      <button class="btn primary" id="np-ok">Create</button>
    </div>
  </div>
</dialog>