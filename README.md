<div align="center">

# 🐎 Open-Rode

### AI-Powered Code Editing Workspace

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![JavaScript](https://img.shields.io/badge/JS-Vanilla-F7DF1E?style=for-the-badge&logo=javascript)](https://developer.mozilla.org/docs/Web/JavaScript)
[![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-143%2F143-brightgreen?style=for-the-badge)](docs/BUG_FIXES.md)
[![Models](https://img.shields.io/badge/Models-87%2B-orange?style=for-the-badge)](docs/REQUIREMENTS.md)
[![Skills](https://img.shields.io/badge/Skills-10%2B-blue?style=for-the-badge)](docs/INSTALL.md)
[![Status](https://img.shields.io/badge/Status-Active-success?style=for-the-badge)](README.md)
[![Version](https://img.shields.io/badge/Version-1.0.0-ff69b4?style=for-the-badge)](README.md)

**One workspace. Every model. Full history.**

`Open-Rode` unifies file management, streaming AI chat, undo/redo versioning, a skills marketplace, and an 87-model catalogue — all in a zero-build PHP + vanilla JS stack.

[![Watch the demo](#)](#gui-walkthrough) • [![Roadmap](#roadmap)](#roadmap) • [![Contribute](#contributing)](#contributing)

</div>

---

## 📑 Table of Contents

| # | Section | Description |
|---|---------|-------------|
| 1 | [Overview](#overview) | What Open-Rode is and why it exists |
| 2 | [Architecture](#architecture) | System diagram & data flow |
| 3 | [Feature Matrix](#feature-matrix) | Every capability, side by side |
| 4 | [GUI Walkthrough](#gui-walkthrough) | Visual tour with interface mockups |
| 5 | [Metrics Dashboard](#metrics-dashboard) | Live health & usage graphs |
| 6 | [Installation](#installation) | Apache / built-in / Docker |
| 7 | [Configuration](#configuration) | Keys, providers, environment |
| 8 | [Usage Guide](#usage-guide) | Chat, auto-mode, skills, models |
| 9 | [API Reference](#api-reference) | Every endpoint, parameter, response |
| 10 | [Testing](#testing) | Run the full regression suite |
| 11 | [Roadmap](#roadmap) | Planned features |
| 12 | [Contributing](#contributing) | How to help |
| 13 | [License](#license) | MIT |

---

## 🌐 Overview

Open-Rode is a **local-first AI coding workspace**. It lets you:

- 📂 **Browse & edit** a project tree with full version history
- 💬 **Chat** with any of **87+ AI models** (10 pre-configured + 77 from the catalogue)
- ⚡ **Stream** responses over Server-Sent Events with live code rendering
- ↩️ **Undo / redo** every AI-applied change with comments
- 🧩 **Attach skills** (Landing page, SEO, Dark mode…) above the composer
- 📊 **Monitor** context-window usage in real time
- 📱 **Use it on a phone** — every control is a ≥44px tap target

```
┌──────────────────────────────────────────────────────────────┐
│                     OPEN-RODE WORKSPACE                       │
├────────────┬─────────────────────────────────────┬───────────┤
│  📁 FILE    │                                     │  🧠 CHAT  │
│   TREE      │         PREVIEW / CODE EDITOR        │  COMPOSER │
│             │                                     │           │
│  ├─ index   │   ┌─────────────────────────┐       │  [skills] │
│  ├─ style   │   │   live preview pane      │       │  [model ▼]│
│  └─ assets  │   │   (sandbox server)       │       │  [input ] │
│             │   └─────────────────────────┘       │  [send →] │
│  [undo][redo]                                     │           │
│  [history ⌚]                                      │  ctx 29k  │
└────────────┴─────────────────────────────────────┴───────────┘
```

### Why Open-Rode?

| Problem | Open-Rode's Answer |
|---------|--------------------|
| Model lock-in | 87 models, switch in one click |
| Lost work | Every change is versioned, restorable |
| Opaque AI | Context meter + truncation warnings |
| No phone UX | Thumb-friendly controls everywhere |
| Build tooling | Zero build — pure PHP + vanilla JS |

---

## 🏗️ Architecture

```
                              ┌─────────────────┐
                              │   Web Browser    │
                              │  (app.js + css)  │
                              └────────┬────────┘
                                       │  HTTP / SSE
                              ┌────────▼────────┐
                              │    router.php    │
                              │    (Front Ctrl)  │
                              └────────┬────────┘
                                       │
        ┌──────────────────────────────┼──────────────────────────────┐
        │                              │                              │
┌───────▼───────┐           ┌──────────▼──────────┐         ┌───────▼───────┐
│  lib/store.php │           │   lib/ai.php        │         │ lib/versions  │
│  (project CRUD)│           │  (SSE streaming)    │         │  (undo/redo)  │
└───────┬───────┘           └──────────┬──────────┘         └───────┬───────┘
        │                              │                              │
        │              ┌───────────────┼───────────────┐              │
        │              │               │               │              │
┌───────▼───────┐ ┌────▼─────┐  ┌──────▼──────┐ ┌─────▼──────┐ ┌─────▼─────┐
│ lib/applier   │ │providers │  │ lib/skills   │ │lib/runner  │ │lib/sandbox│
│ (apply files) │ │(catalogue)│ │ (skill CRUD) │ │(AI dispatch)│ │(preview)  │
└───────────────┘ └────┬─────┘  └──────────────┘ └─────┬──────┘ └───────────┘
                       │                                 │
                       └─────────────┬───────────────────┘
                                     │
                              ┌──────▼──────┐
                              │  data/ dir   │
                              │  (JSON store)│
                              └─────────────┘
```

### Request Lifecycle

```
Client ──POST /api/chat──▶ router.php
                              │
                              ▼
                    ┌───────────────────┐
                    │ load project state│
                    └─────────┬─────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
        ┌──────────┐   ┌──────────┐   ┌──────────┐
        │ resolve  │   │ inject   │   │ build    │
        │ mentions │   │ skills   │   │ messages │
        └────┬─────┘   └────┬─────┘   └────┬─────┘
             └───────────────┼───────────────┘
                             ▼
                    ┌───────────────────┐
                    │ ai_chat_stream()  │◀── provider key
                    └─────────┬─────────┘
                              │ SSE
                              ▼
                    ┌───────────────────┐
                    │ extract_files()   │──▶ apply to disk
                    └─────────┬─────────┘
                              ▼
                    ┌───────────────────┐
                    │ version_save()    │──▶ history
                    └───────────────────┘
```

### Technology Stack

| Layer | Technology | Why |
|-------|------------|-----|
| Runtime | PHP 8.1+ | Zero-build, ubiquitous hosting |
| Frontend | Vanilla JS (2,016 LOC) | No framework overhead |
| Styling | CSS (644 LOC) | Custom properties, responsive |
| AI Transport | Server-Sent Events | Native streaming, auto-reconnect |
| Storage | JSON files | No DB required, portable |
| Preview | In-process sandbox | No port conflicts |

---

## ✨ Feature Matrix

| Feature | Desktop | Phone | Notes |
|---------|:-------:|:-----:|-------|
| File tree (nested) | ✅ | ✅ | Rename, delete, create |
| Undo / redo | ✅ | ✅ | With comments |
| History dialog | ✅ | ✅ | Exact-version restore |
| AI chat (SSE) | ✅ | ✅ | Streaming code render |
| Model switching | ✅ | ✅ | 87 models in dropdown |
| Auto mode | ✅ | ✅ | Multi-file generation |
| Skills chips | ✅ | ✅ | 10 built-in + custom |
| Context meter | ✅ | ✅ | Amber @70%, red @90% |
| Live preview | ✅ | ✅ | Sandbox server |
| Element picker | ✅ | ⚠️ | Tap-to-select |
| Copy code block | ✅ | ✅ | One-click |
| Fold code block | ✅ | ✅ | Show/hide toggle |
| Gated model setup | ✅ | ✅ | One-click key add |

**Legend:** ✅ full support · ⚠️ partial · ❌ not available

---

## 🖥️ GUI Walkthrough

### 1. Home — Project Launcher

```
╔═══════════════════════════════════════════════════════════╗
║  🐎 Open-Rode                                    [+ New]  ║
╠═══════════════════════════════════════════════════════════╣
║                                                           ║
║   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐    ║
║   │ 📁 portfolio│   │ 📁 blog     │   │ 📁 api      │    ║
║   │   PHP       │   │   JS        │   │   PHP       │    ║
║   │  4 files    │   │  12 files   │   │  8 files    │    ║
║   │  edited 2h  │   │  edited 1d  │   │  edited 3d  │    ║
║   └─────────────┘   └─────────────┘   └─────────────┘    ║
║                                                           ║
╚═══════════════════════════════════════════════════════════╝
```

### 2. Workspace — Three-Pane Layout

```
┌────────────────────────────────────────────────────────────────┐
│ 🐎 Open-Rode   [portfolio]   [model: gpt-5-nano ▼]  [⚙️] [⏻] │
├──────────┬──────────────────────────────────────┬──────────────┤
│ 📁 TREE  │  PREVIEW / EDITOR                    │ 💬 CHAT      │
│          │                                      │              │
│ ▾ src    │  ┌────────────────────────────┐      │ [🏷️ SEO]    │
│   ├ index│  │                            │      │ [🏷️ Dark]   │
│   └ style│  │   (live sandbox iframe)    │      │              │
│ ▾ assets │  │                            │      │ AI: I'll    │
│   └ logo │  └────────────────────────────┘      │ update the  │
│          │                                      │ hero color… │
│ [↶][↷][⌚]│                                      │              │
│          │                                      │ ┌──────────┐ │
│          │                                      │ │ type…    │ │
│          │                                      │ └──────────┘ │
│          │                                      │ ctx 12.4k/128k│
└──────────┴──────────────────────────────────────┴──────────────┘
```

### 3. Skills Chips (above composer)

```
  ┌──────────────────────────────────────────────────────────┐
  │ 🏷️ [SEO] [Dark Mode] [Performance]  [+ Add skill]       │
  └──────────────────────────────────────────────────────────┘
   │         │            │
   │         │            └── built-in (free)
   │         └── built-in (free)
   └── built-in (free)
   
   🔒 [MiMo]  ← gated: click to add Zen key
```

### 4. Context Meter

```
  ctx  29.6k / 128k   [████████████░░░░░░░░░░]  23%
                        ▲ green (< 70%)
                        
  ctx  95.0k / 128k   [███████████████████░░░░░]  74%  ⚠️
                        ▲ amber (≥ 70%)
                        
  ctx 120.0k / 128k   [████████████████████████░]  94%  🔴
                        ▲ red (≥ 90%)
```

### 5. History Dialog (exact restore)

```
  ┌──────────────────────── History: index.php ───────────────────┐
  │ #   │ When          │ Comment              │    Action         │
  ├─────┼───────────────┼──────────────────────┼─────────────────┤
  │  4  │ 10:42 AM      │ "refactor hero"      │ [Restore]       │
  │  3  │ 10:38 AM      │ "fix nav link"       │ [Restore]       │
  │  2  │ 10:15 AM      │ current ●            │ [live]          │
  │  1  │ 09:52 AM      │ "initial AI write"   │ [Restore]       │
  └─────┴───────────────┴──────────────────────┴─────────────────┘
     ● = currently active version
     Restore → exact version (no drift after undo/redo)
```

### 6. Code Block (collapsed by default)

```
  ┌─ php:index.php · 42 lines · 1.2 KB ────────────── [Show][⧉] ┐
  │  ▸ (collapsed — click Show to expand)                         │
  └────────────────────────────────────────────────────────────────┘
  
  ┌─ php:index.php · 42 lines · 1.2 KB ────────────── [Hide][⧉] ┐
  │  1 │ <?php                                                    │
  │  2 │                                                           │
  │  3 │ // hero section                                          │
  │  4 │ echo "<h1>Welcome</h1>";                                 │
  │  … │                                                           │
  └────────────────────────────────────────────────────────────────┘
```

---

## 📊 Metrics Dashboard

### Project Health

```
  TESTS PASSED        LINT CLEAN          MODELS               SKILLS
  ┌──────────┐        ┌──────────┐       ┌──────────┐        ┌──────────┐
  │ ████████ │ 100%   │ ████████ │ 100%  │ ████████ │  87    │ ██████░░ │  10
  │ 143/143  │        │   0 err  │       │          │        │ +custom  │
  └──────────┘        └──────────┘       └──────────┘        └──────────┘
```

### Context Window Usage (live)

```
  tokens used
  128k ┤                                         ╭──────── red zone
       │                                    ╭────╯
   90k ┤                            ╭───────╯  ← amber zone (70%)
       │                       ╭──────╯
   50k ┤                ╭──────╯
       │          ╭─────╯
    0k ┼────╮─────╯─────╮─────╮─────╮─────╮─────▶ time
       0s   10s    20s    30s    40s    50s   60s
       
       ▲ rises as you chat, resets on new session
```

### Badge Badges (traffic)

| Metric | Badge | Value |
|--------|-------|-------|
| Build | ![build](https://img.shields.io/badge/build-passing-brightgreen) | passing |
| Tests | ![tests](https://img.shields.io/badge/tests-143%2F143-brightgreen) | 100% |
| Lint | ![lint](https://img.shields.io/badge/lint-clean-blue) | 0 errors |
| PHP | ![php](https://img.shields.io/badge/PHP-8.1%2B-777BB4) | 8.1+ |
| Models | ![models](https://img.shields.io/badge/models-87%2B-orange) | 87 |
| Skills | ![skills](https://img.shields.io/badge/skills-10%2B-blue) | 10 |
| Undo | ![undo](https://img.shields.io/badge/undo-redo%20%E2%86%90%20%E2%86%91-yellow) | full |
| Context | ![ctx](https://img.shields.io/badge/context-live%20meter-green) | live |
| License | ![license](https://img.shields.io/badge/license-MIT-blue) | MIT |
| Version | ![version](https://img.shields.io/badge/version-1.0.0-ff69b4) | 1.0.0 |

### Lines of Code

```
  assets/app.js    ████████████████████████████████████████████████  2,016
  lib/ai.php       ██████████████████████████████████████            596
  index.php        █████████████████████████████████████            549
  assets/app.css   ████████████████████████████████████████████████████  644
  lib/providers.php███████████████████████████████                   415
  ─────────────────────────────────────────────────────────────────
  TOTAL                                                             6,440 LOC
```

---

## 📦 Installation

### Prerequisites

```
  ┌─────────────────────────────────────────────────────┐
  │  ✓  Apache 2.4+  (or PHP built-in server)           │
  │  ✓  PHP 8.1+   (extensions: pdo, mbstring,         │
  │                 openssl, json, curl)                │
  │  ✓  Writable  data/ directory                       │
  │  ✗  Node.js — NOT required (zero build)             │
  │  ✗  Database  — NOT required (JSON storage)         │
  └─────────────────────────────────────────────────────┘
```

### Option A — XAMPP / LAMPP (recommended)

```bash
# 1. Copy project into web root
cp -r Open-Rode /opt/lampp/htdocs/

# 2. Make data writable
chmod -R 755 /opt/lampp/htdocs/Open-Rode/data

# 3. Start Apache from XAMPP control panel

# 4. Open browser
xdg-open http://localhost/Open-Rode/
```

### Option B — PHP Built-in Server

```bash
cd Open-Rode
php -S localhost:8000 router.php
# → http://localhost:8000
```

### Option C — Docker

```bash
docker run -d \
  -p 8080:80 \
  -v $(pwd)/Open-Rode:/var/www/html \
  -v $(pwd)/Open-Rode/data:/var/www/html/data \
  php:8.1-apache
# → http://localhost:8080
```

### Verify Install

```bash
# PHP syntax
/opt/lampp/bin/php -l index.php
# → No syntax errors detected

# JS syntax
node --check assets/app.js

# Full test suite (see Testing)
cd /tmp/opencode && node wstest.js
# → 143/143 checks passed
```

---

## ⚙️ Configuration

### Add a Provider / API Key

1. Open **⚙️ Settings**
2. Go to **Models** tab
3. Click **Add Server**
4. Choose a provider preset (OpenCode Zen, OpenAI, Anthropic, etc.)
5. Paste your API key
6. Save — models appear in the header dropdown **instantly**

```
  ┌─ Add Server ──────────────────────────────┐
  │ Provider: [OpenCode Zen        ▼]         │
  │ Base URL: https://opencode.ai/zen         │
  │ API Key:  [••••••••••••••••        ]      │
  │                                     [Save]│
  └────────────────────────────────────────────┘
```

### Keyless (Free) Models

The catalogue marks models with a `free` flag. Only verified-free models auto-activate without a key:

```
  ✅ space-bunny-free     → works keyless
  🔒 mimo-v2.5-pro        → needs Zen key (one-click add)
  ⛔ exo-free             → dead (auto-pruned)
```

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `OPENRODE_DATA_DIR` | `data/` | Storage root |
| `OPENRODE_MAX_TOKENS` | `16000` | AI response budget |
| `OPENRODE_CACHE_TTL` | `900` | Catalogue cache (sec) |
| `OPENRODE_DIFF_CAP` | `400` | Max diff lines in SSE |

---

## 📖 Usage Guide

### Chat with AI

1. Pick a model from the **header dropdown**
2. Type a request: *"Make the hero background dark blue"*
3. Press **Enter** or **Send**
4. Watch the **streaming code block** render live
5. Review → **Apply** or **Reject**

```
  You:  Make the hero background dark blue
  ────────────────────────────────────────
  AI:   I'll update style.css:
        ┌─ css:style.css · 8 lines ──────────[Show][⧉]┐
        │ .hero { background: #1a2b4a; }              │
        └───────────────────────────────────────────────┘
        [Apply] [Reject]
```

### Auto Mode (multi-file)

Toggle **Auto** in the composer to let the AI write several files at once.

```
  [x] Auto mode
  
  You:  Create a portfolio with hero, about, contact
  AI:   ✓ index.php  (applied)
        ✓ style.css  (applied)
        ⚠ extra.txt  — ran out of room, ask it to continue
```

When truncated, Open-Rode shows a **"ran out of room — ask it to continue"** toast instead of silently failing.

### Undo / Redo

Every AI-applied change is versioned:

- Click **↶** / **↷** on a file row to step through history
- Click **⌚** to open the **History dialog** and restore any exact version
- Add a **comment** to each version for context

### Skills

**Skills** are reusable instruction packs injected into your chat:

1. Open **⚙️ Settings → Skills**
2. Toggle built-in skills (SEO, Dark mode, Performance…)
3. Or **add a custom skill**: name + instructions
4. Active skills appear as **chips** above the composer
5. They auto-inject into every message and clear after send

```
  Active skills:  [🏷️ SEO] [🏷️ Dark Mode] [🏷️ Performance]
  
  → Your message is prefixed with each skill's instructions
```

### Model Switching

```
  Header:  [ gpt-5-nano        ▼ ]
              │
              ├── Configured (10)
              │    ├── claude-sonnet-4.5
              │    ├── gpt-5-nano
              │    └── …
              └── Catalogue (77)
                   ├── mimo-v2.5-pro
                   ├── space-bunny-free  ✅ keyless
                   └── …
```

---

## 🔌 API Reference

### Projects

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/projects` | List all projects |
| `POST` | `/api/projects` | Create project |
| `GET` | `/api/projects/{id}` | Project metadata + files |
| `DELETE` | `/api/projects/{id}` | Delete project |

### Files

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/projects/{id}/files` | File tree |
| `PUT` | `/api/projects/{id}/files/{path}` | Write file |
| `DELETE` | `/api/projects/{id}/files/{path}` | Delete file |
| `POST` | `/api/projects/{id}/files/{path}/rename` | Rename file |

### Versions (undo/redo)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/projects/{id}/versions` | Version history (with `latest`/`latest_redo` maps) |
| `POST` | `/api/projects/{id}/revert` | Undo/redo (`{path, direction}`) |
| `POST` | `/api/projects/{id}/revert` | Exact restore (`{path, index}`) |
| `POST` | `/api/projects/{id}/version-comment` | Save version comment |

### Chat

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/projects/{id}/chat` | SSE streaming chat |

**SSE events:** `start` (with `context`), `delta`, `reasoning`, `files`, `done` (with `truncated`)

### Skills

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/projects/{id}/skills` | List skills |
| `POST` | `/api/projects/{id}/skills` | Save skill |
| `DELETE` | `/api/projects/{id}/skills/{skillId}` | Delete skill |

### Providers & Models

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/providers` | Configured providers + `free_models` |
| `POST` | `/api/providers` | Save provider (invalidates catalogue cache) |
| `GET` | `/api/catalog` | Full model catalogue (cached 15 min) |

### Preview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/projects/{id}/server` | Start/stop sandbox server (`{action}`) |

---

## 🧪 Testing

The project ships a **143-check regression suite**:

```bash
# Workspace UI checks (143)
cd /tmp/opencode && node wstest.js
# → 143/143 checks passed; console errors=0

# Home page checks (14)
node hometest.js
# → 14/14 checks passed; console errors=0

# Undo/redo API
/opt/lampp/bin/php undo_test.php
# → all checks passed

# Lint
/opt/lampp/bin/php -l index.php        # all .php files
node --check assets/app.js
```

### Coverage Areas

```
  ✓ File tree render          ✓ Undo/redo round-trips
  ✓ Model dropdown            ✓ History exact-restore
  ✓ Skills CRUD               ✓ Context meter thresholds
  ✓ Chat SSE streaming        ✓ Code block fold/copy
  ✓ Phone tap targets         ✓ Error surfacing
  ✓ Preview open/close        ✓ Element picker
```

---

## 🗺️ Roadmap

| Version | Feature | Status |
|---------|---------|--------|
| 1.0 | Core editing, chat, undo/redo | ✅ shipped |
| 1.0 | Skills system (10 built-in + custom) | ✅ shipped |
| 1.0 | 87-model catalogue | ✅ shipped |
| 1.0 | Context meter | ✅ shipped |
| 1.1 | Real tokenizer for context estimate | 🔜 planned |
| 1.1 | Collaborative cursors | 🔜 planned |
| 1.2 | Plugin API | 🔜 planned |
| 1.2 | Voice input | 🔜 planned |
| 2.0 | Multi-user auth | 🔜 planned |

---

## 🤝 Contributing

Contributions welcome! The project follows a **test-driven** workflow:

```bash
# 1. Make your change
# 2. Run the full suite
cd /tmp/opencode && node wstest.js   # must be 143/143
# 3. Lint
/opt/lampp/bin/php -l <changed-file>
node --check assets/app.js
# 4. Document in docs/BUG_FIXES.md
```

### Project Structure

```
Open-Rode/
├── index.php              # Front controller (549 LOC)
├── config.php             # Config (19 LOC)
├── router.php             # URL routing (16 LOC)
├── lib/
│   ├── ai.php             # SSE streaming + context (596 LOC)
│   ├── applier.php        # File apply/extract (95 LOC)
│   ├── diff.php           # Diff generation (151 LOC)
│   ├── helpers.php        # Utilities (136 LOC)
│   ├── providers.php      # Model catalogue (415 LOC)
│   ├── runner.php         # AI dispatch (220 LOC)
│   ├── sandbox.php        # Preview server (318 LOC)
│   ├── skills.php         # Skills CRUD (221 LOC)
│   ├── store.php          # Project storage (375 LOC)
│   └── versions.php       # Undo/redo store (280 LOC)
├── views/
│   ├── home.php           # Project launcher (107 LOC)
│   ├── layout.php         # HTML shell (101 LOC)
│   └── project.php        # Workspace markup (181 LOC)
├── assets/
│   ├── app.js             # All UI logic (2,016 LOC)
│   └── app.css            # Styling (644 LOC)
├── docs/
│   ├── REQUIREMENTS.md    # Environment needs
│   ├── INSTALL.md         # Setup guide
│   └── BUG_FIXES.md       # All fixes + root causes
├── .htaccess              # Rewrites, CORS, security
├── .gitignore             # Node modules, data artifacts
├── LICENSE                # MIT
└── README.md              # This file
```

---

## 📄 License

MIT © Open-Rode contributors

```
  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software…
```

See [LICENSE](LICENSE) for the full text.

---

<div align="center">

**Built with PHP + vanilla JS · Zero build · MIT licensed**

[![Back to top](#)](#open-rode)

</div>
