# OpenRode

**Describe a website in plain words. Watch it get built. Approve every change.**

OpenRode is a self-hosted AI website builder. You type what you want, an AI model
writes the files, and nothing touches your project until you say so. It runs on
plain PHP with no build step, no framework and no database.

```text
"a landing page for a coffee roastery with a hero, three blends and a contact form"
        │
        ▼
   AI writes index.php + style.css
        │
        ▼
   You see a diff → Approve or Reject
        │
        ▼
   Live preview + undo / redo / version history
```

---

## Table of contents

- [Why OpenRode](#why-openrode)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
  - [Option A — Apache](#option-a--apache-recommended)
  - [Option B — PHP built-in server](#option-b--php-built-in-server-trial)
  - [Docker](#option-c--docker)
- [Configuration](#configuration)
- [Connecting an AI provider](#connecting-an-ai-provider)
- [How it works](#how-it-works)
- [Project layout](#project-layout)
- [HTTP API](#http-api)
- [Security notes](#security-notes)
- [Troubleshooting](#troubleshooting)
- [License](#license)

---

## Why OpenRode

Most AI builders take your prompt and hand you a black box. OpenRode is built
around the opposite idea: **the AI proposes, you dispose.**

- **Nothing changes without your say-so.** Every edit arrives as a reviewable
  diff. One click applies it; one click throws it away.
- **You can always get back.** Undo, redo and a per-file version history with
  comments live alongside the file tree. Undoing is exact, not approximate.
- **You can see it work.** A built-in preview server renders the real project.
  Point at any element on the page and ask the bot to change just that part.
- **It is yours to host.** One folder, one PHP binary, no vendor directory, no
  telemetry. Your projects are plain files in `data/projects/`.
- **Bring your own key — or none.** It works keyless with OpenCode Zen's free
  tier, or with OpenRouter, Groq, GitHub Models, Gemini, Ollama, LM Studio, or
  literally any OpenAI-compatible endpoint.

---

## Features

### Building

| | |
|---|---|
| **Chat-to-site** | Describe it in plain language; the model writes the files. |
| **Reviewable changes** | Diff cards with per-file ticks, `Apply` / `Apply all` / `Reject`. |
| **Two modes** | `ask` (review everything, the default) or `auto` (apply immediately). |
| **Skills** | Ten curated skills — landing page, mobile-first, dark mode, SEO, a11y, performance — plus your own. |
| **Element picker** | Click any element in the live preview and ask for just that part to change. |
| **Smart history** | Chat sessions, `@file` mentions, `/` commands, attachments. |
| **Truncation-aware** | If a long answer is cut off, the partial code is kept and you are told. |

### Control & safety

| | |
|---|---|
| **Undo / redo** | Per file or from the toolbar; exact, position-aware restores. |
| **Version history** | Every version with a comment and one-click restore. |
| **File tree** | Create, rename, delete and reference files without leaving the chat. |
| **Truncation guard** | A half-finished answer never silently discards your work. |

### Interface

| | |
|---|---|
| **Live preview** | Built-in PHP server, phone/tablet/desktop widths, reload, open in a new tab. |
| **Split view** | File tree, site and chat side by side. |
| **Context meter** | See how full the model's context window is before it degrades. |
| **Phone-first** | 44px tap targets, no hover-dependent controls, safe-area aware. |
| **Themes** | Dark and light. |

---

## Requirements

### Minimum

| Component | Version | Notes |
|---|---|---|
| **PHP** | **8.1+** (8.2/8.3 recommended) | Needs `json`, `curl` *(or `allow_url_fopen`)*, `mbstring` |
| **Web server** | Apache 2.4+ or PHP's built-in server | Rewrite not required for the built-in server |
| **Disk** | ~5 MB | Plus whatever your projects use |

### How it is verified

OpenRode is developed and tested against:

- **PHP 8.2.4** on Linux
- **Apache 2.4** (`mod_rewrite`, `mod_authz_core`)
- Browsers: current Chrome/Edge/Firefox/Safari, desktop and mobile

### PHP extensions

```bash
php -m | grep -E '^(json|curl|mbstring)$'
```

- `json` — always bundled since PHP 8.
- `curl` — preferred for streaming AI responses. Without it the app falls back
  to stream sockets, which is slower but works if `allow_url_fopen=On`.
- `mbstring` — used for safe text truncation. Strongly recommended.

### Optional

| | |
|---|---|
| **Node.js 18+** | Only for running the test suite. The app itself has no Node dependency. |
| **cURL binary** | Only if PHP's `curl` extension is unavailable. |

---

## Installation

### Option A — Apache (recommended)

**1. Copy the files into your web root**

```bash
sudo git clone <your-repo-url> /var/www/html/openrode
cd /var/www/html/openrode
```

**2. Make `data/` writable by the web server**

```bash
sudo chown -R www-data:www-data data
sudo chmod -R 775 data
```

**3. Enable the rewrite module** (if it is not already on)

```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

**4. Open it**

```
http://localhost/openrode/
```

The bundled `.htaccess` sends every request through `index.php` and denies direct
access to `config.php`, `router.php` and everything under `data/`. Verify:

```bash
curl -I http://localhost/openrode/data/providers.json   # expect 403
```

### Option B — PHP built-in server (trial)

No configuration required.

```bash
cd openrode
php -S 127.0.0.1:8000 router.php
```

Then open <http://127.0.0.1:8000>.

> `router.php` is required. It hides `data/`, `lib/` and `views/` and serves
> static assets normally. Without it you would expose your provider keys.

The preview server needs the same PHP binary, so keep it on your `PATH` or set
`OPENRODE_PHP`.

### Option C — Docker

```bash
docker run -d --name openrode \
  -p 8080:80 \
  -v openrode-data:/var/www/html/openrode/data \
  -e OPENRODE_BASE_URL="https://openrouter.ai/api/v1" \
  -e OPENRODE_API_KEY="sk-or-..." \
  openrode:latest
```

---

## Configuration

Everything is optional. Without any environment variables the app runs on the
keyless OpenCode Zen free tier.

| Variable | Default | Purpose |
|---|---|---|
| `OPENRODE_API_KEY` | *(empty)* | Default provider key. Strongly recommended. |
| `OPENRODE_BASE_URL` | `https://api.openai.com/v1` | Any OpenAI-compatible endpoint. |
| `OPENRODE_MODEL` | `gpt-4o-mini` | Starting model. |
| `OPENRODE_MAX_TOKENS` | `8000` | Response budget. Raise for big pages. |
| `OPENRODE_TEMPERATURE` | `0.3` | Lower = more predictable code. |
| `OPENRODE_PHP` | auto-detected | PHP binary used for previews. |

> **API keys belong in environment variables**, never in `config.php` and never
> in the repository. `data/providers.json` is git-ignored for the same reason.

After first run most settings are managed in the UI (**⚙ Settings**) and stored in
`data/`.

---

## Connecting an AI provider

OpenRode speaks the OpenAI Chat Completions API, so almost anything works.

**Settings → Add server**, then pick a preset or paste a base URL:

<details>
<summary><b>Presets included</b></summary>

| Provider | Base URL | Free tier |
|---|---|---|
| OpenCode Zen | `https://opencode.ai/zen/v1` | Yes — keyless |
| OpenRouter | `https://openrouter.ai/api/v1` | Yes |
| Groq | `https://api.groq.com/openai/v1` | Yes, fast |
| GitHub Models | `https://models.inference.ai.azure.com/v1` | Yes, with a GitHub token |
| Google Gemini | `https://generativelanguage.googleapis.com/v1beta/openai/` | Yes |
| Ollama (local) | `http://localhost:11434/v1` | Free, offline |
| LM Studio (local) | `http://localhost:1234/v1` | Free, offline |
| …and more | | |

</details>

**Custom endpoint example (vLLM, LiteLLM, OpenWebUI, LM Studio):**

```
Base URL:  http://localhost:8000/v1
API key:   anything (e.g. "local")
Models:    your-model-name
```

### A note on the free tier

The keyless OpenCode Zen route is the easiest way to try OpenRode, but Zen serves
most of its free models **only to its own signed-in client**. In practice one
model (`space-bunny-free`) works without a key; the rest need a free Zen key —
click any **🔑 needs key** skill row and OpenRode will pre-fill the setup for you.

---

## How it works

```
Browser ──POST /api/projects/{id}/chat──▶ index.php
                                            │
                                     lib/ai.php  ──SSE──▶ provider
                                            │
                                     lib/applier.php  (parse fenced code)
                                            │
                                     lib/diff.php     (build diffs)
                                            │
                                            ▼
                                 pending.json  ──▶ you approve
                                            │
                                     lib/applier.php → files/
                                     lib/versions.php → .versions.json
```

- Responses stream back as SSE: `start`, `reasoning`, `delta`, `files`, `done`.
- The bot's answer is scanned for fenced blocks (`` ```php:index.php ``).
- Each becomes a file. If the answer was cut off mid-block, the partial file is
  kept and flagged `truncated` instead of being silently dropped.
- In `ask` mode nothing is written until you approve.

### SSE events

| Event | Meaning |
|---|---|
| `start` | Session opened. Includes `model`, `provider` and `context` usage. |
| `reasoning` | The model thinking. Shown live so the UI never looks frozen. |
| `delta` | A chunk of the answer. |
| `files` | Files proposed, with diffs and `truncated` flag. |
| `done` | Finished; lists the files it wrote. |
| `error` | Something went wrong; the message is human-readable. |

---

## Project layout

```
openrode/
├── index.php              # Front controller + JSON/SSE API
├── router.php             # Router for `php -S`
├── config.php             # Defaults (env-overridable)
├── .htaccess              # Rewrites + blocks config.php/router.php
├── .gitignore
├── assets/
│   ├── app.js             # Entire frontend (no framework, no bundler)
│   └── app.css            # Entire stylesheet
├── views/
│   ├── layout.php         # Shell + settings dialog
│   ├── home.php           # Project list
│   └── project.php        # Workspace
├── lib/
│   ├── helpers.php        # Small utilities, config access
│   ├── store.php          # Projects, files, zip
│   ├── providers.php      # Provider config + model catalogue
│   ├── ai.php             # Prompting, streaming, error surfacing
│   ├── applier.php        # Fence extraction, writes, snapshots
│   ├── diff.php           # Line diffs, pending changes
│   ├── versions.php       # Undo/redo + comments
│   ├── skills.php         # Built-in + user skills
│   ├── sandbox.php        # Preview server
│   └── runner.php
└── data/                  # git-ignored
    ├── providers.json
    └── projects/<id>/
        ├── project.json
        ├── files/
        ├── sessions/
        ├── .versions.json
        └── skills.json
```

---

## HTTP API

Everything the UI does is available over JSON. Useful for scripting.

### Projects

```http
GET    /api/projects                     list
POST   /api/projects                     create        {name, template}
GET    /api/projects/{id}
POST   /api/projects/{id}                update        {name, apply_mode}
DELETE /api/projects/{id}
GET    /api/projects/{id}/tree           files + undoable/redoable lists
GET    /api/projects/{id}/zip            download
```

### Files

```http
GET    /api/projects/{id}/file?path=…    read
POST   /api/projects/{id}/file           write        {path, content}
DELETE /api/projects/{id}/file?path=…    delete
```

### Chat

```http
POST   /api/projects/{id}/chat           {message, session_id?, skills?[]}  → SSE
GET    /api/projects/{id}/sessions
GET    /api/projects/{id}/changes
POST   /api/projects/{id}/changes        {action: apply|reject, paths?}
```

### Versions

```http
GET    /api/projects/{id}/versions?path=…
POST   /api/projects/{id}/revert         {path} | {path, direction:"undo"|"redo"} | {path, index}
POST   /api/projects/{id}/version-comment  {path, index, comment}
```

### Skills & providers

```http
GET    /api/projects/{id}/skills
POST   /api/projects/{id}/skills         {name, body}
DELETE /api/projects/{id}/skills/{skillId}

GET    /api/providers
GET    /api/catalog                      model catalogue (cached)
POST   /api/active                       {provider, model}
```

---

## Security notes

OpenRode is designed for a trusted, single-user or small-team host.

- **Never expose it to the open internet without authentication.** There is no
  login screen; anyone who can reach it can read and write your projects.
  Put it behind HTTP basic auth, a VPN, or an SSH tunnel.
- **API keys** live in `data/providers.json`, which is git-ignored and blocked by
  `data/.htaccess`.
- **Path traversal** is prevented centrally in `lib/store.php::safe_file_path()`;
  every write goes through it.
- **The preview server** binds to loopback only and is started on demand.
- **Prompt injection** is possible by design: the model runs with your project
  contents. Do not point it at files you would not share with the provider.

---

## Troubleshooting

**"No model selected"**
Add a provider in Settings → Add server, then pick a model from the header.

**The model replies but no files change**
The answer was cut off before the code block closed. OpenRode now flags this —
ask for the file "one at a time", or switch to a model with a longer output
budget (`OPENRODE_MAX_TOKENS`).

**Preview will not start**
Check the PHP binary: `which php`. Set `OPENRODE_PHP=/full/path/to/php`.
Ports 8100–8399 are used; make sure they are free.

**403 on `data/`**
Expected and intentional. `data/` must never be web-readable.

**Live edits do nothing**
Press **■ Stop** in the preview header, then **▶ Preview** again to restart the
server.

**Everything is slow**
Check the context meter in the chat header. A long conversation fills the model's
context window; start a new session to reset it.

---

## License

MIT — see [LICENSE](LICENSE).

Built for people who want to see what changed, and be able to undo it.