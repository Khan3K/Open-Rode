# Installation guide

Step-by-step, from a clean machine to a running OpenRode.

- [1. Choose a setup](#1-choose-a-setup)
- [2. Install PHP](#2-install-php)
- [3. Get the code](#3-get-the-code)
- [4. Apache](#4-apache)
- [5. Built-in server](#5-built-in-server)
- [6. Verify](#6-verify)
- [7. Connect an AI provider](#7-connect-an-ai-provider)
- [8. Build your first site](#8-build-your-first-site)
- [9. Updates](#9-updates)
- [10. Uninstall](#10-uninstall)
- [Troubleshooting](#troubleshooting)

---

## 1. Choose a setup

| | Apache | Built-in server | Docker |
|---|---|---|---|
| Effort | Low | Lowest | Lowest |
| Production ready | Yes | No | Yes |
| Rewrites needed | `mod_rewrite` | No | No |
| Use it for | Real hosting | Trying it out | Containers |

Requirements are detailed in [REQUIREMENTS.md](REQUIREMENTS.md).

---

## 2. Install PHP

### Debian / Ubuntu

```bash
sudo apt update
sudo apt install -y php-cli php-curl php-mbstring apache2 libapache2-mod-php
sudo a2enmod rewrite
```

### macOS (Homebrew)

```bash
brew install php
brew services start php
```

### Windows (XAMPP / Laragon / Scoop)

Install PHP 8.1+ and make sure `curl` and `mbstring` are enabled in `php.ini`.
With XAMPP or Laragon, Apache is included.

### Verify

```bash
php -v
php -m | grep -E '^(json|curl|mbstring)$'
```

You need all three lines. `mbstring` is almost always present already.

---

## 3. Get the code

```bash
git clone <repo-url> openrode
cd openrode
```

Or download the ZIP and extract it.

You should have:

```text
openrode/
├── index.php
├── router.php
├── config.php
├── assets/
├── lib/
├── views/
└── data/
```

---

## 4. Apache

**1. Move it into your web root**

```bash
sudo cp -r openrode /var/www/html/
cd /var/www/html/openrode
```

**2. Create `data/` if it is missing, and set ownership**

```bash
mkdir -p data
sudo chown -R www-data:www-data data
sudo chmod -R 775 data
```

> On macOS the Apache user is `_www`; on Windows, grant access via the folder
> properties instead.

**3. Make sure the rewrite rules are active**

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**4. Open it**

```
http://localhost/openrode/
```

You should see the project list and a green **New project** button.

---

## 5. Built-in server

No setup beyond PHP:

```bash
cd openrode
mkdir -p data && chmod 775 data
php -S 127.0.0.1:8000 router.php
```

Open <http://127.0.0.1:8000>.

> **Use `router.php`.** Running `php -S 127.0.0.1:8000` without it serves
> `data/providers.json` to anyone who asks — that file holds your API keys.

To have the preview server use the same PHP binary:

```bash
OPENRODE_PHP="$(which php)" php -S 127.0.0.1:8000 router.php
```

---

## 6. Verify

```bash
curl -s -o /dev/null -w 'app:      %{http_code}\n' http://localhost/openrode/
curl -s -o /dev/null -w 'data:     %{http_code}\n' http://localhost/openrode/data/providers.json
curl -s -o /dev/null -w 'config:   %{http_code}\n' http://localhost/openrode/config.php
curl -s -o /dev/null -w 'router:   %{http_code}\n' http://localhost/openrode/router.php
```

Expected:

```text
app:      200
data:     403
config:   403
router:   403
```

**If `data/` returns 200, stop and fix it.** On Apache:

```bash
a2enmod authz_core && systemctl reload apache2
```

On the built-in server, you skipped `router.php`.

---

## 7. Connect an AI provider

Open the app and go to **⚙ Settings**.

### Easiest: no key at all

Pick the **OpenCode Zen - Free (no key)** preset. You can build immediately.

> Reality check: Zen serves most of its free models only to its own signed-in
> client. `space-bunny-free` works keyless; the others are marked 🔑 and need a
> key. The app labels this honestly rather than pretending otherwise.

### Best free experience: a real key

1. Get a key from [OpenRouter](https://openrouter.ai/keys) or
   [Groq](https://console.groq.com/keys).
2. **Settings → Add server → Quick add → OpenRouter**.
3. Paste the key, click **Add server**.
4. Pick a model from the header dropdown.

### Your own machine

**Ollama**

```bash
ollama serve
ollama pull llama3.2
```

Then **Settings → Add server → Quick add → Ollama (local / free)**.

### Via environment variables

```bash
export OPENRODE_BASE_URL="https://openrouter.ai/api/v1"
export OPENRODE_API_KEY="sk-or-..."
export OPENRODE_MODEL="deepseek/deepseek-chat"
php -S 127.0.0.1:8000 router.php
```

---

## 8. Build your first site

1. **New project** → name it → **Create**.
2. In the chat, type something concrete:

   > Make a landing page for a coffee roastery: a hero with the shop name, three
   > blends with prices, and a contact form.

3. The bot replies with the files it wrote. Code blocks are **collapsed** — click
   **Show** to read them, **Copy** to grab them.
4. You get an **"Approve these changes?"** card showing a line-by-line diff.
   Tick the files you want and click **Apply**.
5. Click **▶ Preview** to see it live. Use **◎ Pick**, click any element, and ask
   for just that part to change.
6. Happy? **⤓ Download** the project as a zip.

### Modes

The **ask / auto** switch sits in the chat header:

| Mode | Behaviour |
|---|---|
| **ask** *(default)* | Nothing is written until you approve. |
| **auto** | Changes are applied as soon as they arrive. |

Use **ask** until you trust the output.

### If it goes wrong

Every write is snapshotted. **↶ Undo** / **↱ Redo** per file, or **⌚ History** for
the full list with comments. There is no way to permanently lose a file.

---

## 9. Updates

```bash
cd /var/www/html/openrode
git pull
```

Your projects live in `data/`, which is not tracked by git, so pulling never
touches them. Still worth a backup:

```bash
sudo tar czf /var/backups/openrode-$(date +%F).tar.gz -C /var/www/html openrode/data
```

After updating, no build step is needed.

---

## 10. Uninstall

```bash
sudo systemctl stop apache2        # if applicable
sudo rm -rf /var/www/html/openrode
```

Everything — projects, keys, history — lives under `data/`, which is removed with
the folder. Delete `/var/backups/openrode-*.tar.gz` too if you made any.

---

## Troubleshooting

### Blank page

```bash
tail -f /var/log/apache2/error.log
php -l index.php
```

Usually a PHP version or extension problem. See [REQUIREMENTS.md](REQUIREMENTS.md).

### "No model selected"

Settings → Add server, then choose a model in the header dropdown.

### "OpenCode's free tier can only be used from within OpenCode"

Zen is refusing keyless access to that model. Use `space-bunny-free`, or add a
free Zen key — the app will pre-fill it for you.

### The bot replied but nothing changed

The answer was cut off before the code block closed. OpenRode keeps the partial
file and warns you. Ask for **one file at a time**.

### Preview will not start

```bash
which php                       # set OPENRODE_PHP if empty
ss -tlnp | grep -E '81[0-9][0-9]'   # ports 8100-8399 in use?
```

### Page loads but the UI is stale

Hard refresh: `Ctrl+Shift+R` (`Cmd+Shift+R` on macOS). `app.js` is versioned by
query string, so a normal refresh can serve an old copy.

### Streaming feels buffered (nginx)

```nginx
proxy_buffering off;
proxy_read_timeout 600s;
```

### "AI returned an empty response"

Older builds discarded the provider's real error. If you still see this, upgrade —
current versions report the provider's actual message.