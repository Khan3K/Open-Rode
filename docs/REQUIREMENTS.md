# Requirements

Complete, verifiable dependency list for OpenRode, split into **required**,
**recommended** and **optional**.

Last verified: PHP 8.2.4 on Linux, Apache 2.4, Chrome/Edge/Firefox/Safari
(current), Node 18+ for the test suite.

---

## 1. Required

### 1.1 PHP

| | |
|---|---|
| **Minimum** | 8.1 |
| **Recommended** | 8.2 or 8.3 |
| **Verified on** | 8.2.4 |

Why 8.1: the codebase uses `str_contains`, `str_starts_with`, enums-free
`match`-free typed signatures, `?->`, and `array_is_list`. All landed in 8.0–8.1.

Check your version:

```bash
php -v
```

### 1.2 Required PHP extensions

| Extension | Required | Fallback if missing |
|---|---|---|
| `json` | **Yes** | None — everything is JSON. Bundled since PHP 8. |
| `mbstring` | **Yes** | None. Bundled and enabled by default on most builds. |
| `curl` | Recommended | Falls back to stream sockets (slower). |

Verify:

```bash
php -m | grep -E '^(json|curl|mbstring)$'
```

Install if missing (Debian/Ubuntu):

```bash
sudo apt install php-cli php-curl php-mbstring
```

**`curl` fallback.** `lib/ai.php::ai_sse_request()` uses `curl_exec` when
available. Without it, `ai_sse_request_stream()` reads the response through a
socket. It works, but streaming is slower and timeouts are coarser. If you run
without `curl`, make sure `allow_url_fopen=On`.

### 1.3 Web server

| | |
|---|---|
| **Apache 2.4** | Recommended. Uses the bundled `.htaccess`. |
| **PHP built-in server** | Supported via `router.php`. Fine for trials. |
| **nginx** | Supported — see [§5](#5-nginx). |

Required Apache modules:

```bash
sudo a2enmod rewrite          # clean URLs
sudo a2enmod authz_core       # the 403 rules in .htaccess
```

If `mod_authz_core` is unavailable, `data/.htaccess` falls back to the legacy
`Order/Deny` syntax.

### 1.4 Filesystem permissions

The web-server user needs write access to `data/` only.

```bash
sudo chown -R www-data:www-data data
sudo chmod -R 775 data
```

Read access is enough for everything else. OpenRode writes to:

| Path | What |
|---|---|
| `data/providers.json` | Provider keys and model lists |
| `data/settings.json` | UI settings |
| `data/.catalog-cache.json` | Cached model catalogue (15 min TTL) |
| `data/projects/<id>/project.json` | Project metadata |
| `data/projects/<id>/files/` | The actual site |
| `data/projects/<id>/sessions/` | Chat history |
| `data/projects/<id>/.versions.json` | Undo/redo history |
| `data/projects/<id>/skills.json` | Your custom skills |

> If the web server and the CLI run as different users, the JSON writers call
> `chmod(0666)` so the other side can still read/write. Setting `775` on `data/`
> is still worth doing — it avoids the fallback entirely.

---

## 2. Recommended

### 2.1 PHP settings

| Setting | Recommended | Notes |
|---|---|---|
| `memory_limit` | `256M` | Large projects + long answers. |
| `max_execution_time` | `0` or `300` | Streaming responses can be slow. The app calls `set_time_limit(0)` itself. |
| `output_buffering` | `Off` | SSE must not be buffered. The app disables it per-request. |
| `zlib.output_compression` | `Off` | The app disables it per-request. |
| `expose_php` | `Off` | Cosmetic. |

Confirm streaming is not buffered:

```bash
curl -N http://localhost/openrode/api/projects/<id>/chat \
  -H 'Content-Type: application/json' \
  -d '{"message":"hi"}'
```

You should see `event:` lines arrive progressively, not all at once at the end.

### 2.2 HTTPS

The app talks to providers over HTTPS and holds API keys. Terminate TLS at the
web server (or a reverse proxy). Do not send it over plain HTTP outside localhost.

---

## 3. Optional

### 3.1 Node.js — tests only

| | |
|---|---|
| **Version** | 18+ |
| **Needed for** | Running `npm test` / the jsdom suite. **Not** needed to run OpenRode. |

```bash
node -v
npm install jsdom@30
```

### 3.2 Local model servers

If you want to run models on your own machine:

| Server | Default URL | Notes |
|---|---|---|
| **Ollama** | `http://localhost:11434/v1` | `ollama serve`, then `ollama pull llama3.2` |
| **LM Studio** | `http://localhost:1234/v1` | Start the local server in the app |
| **vLLM** | `http://localhost:8000/v1` | `--api-key anything` is fine |
| **llama.cpp** | `http://localhost:8080/v1` | Via the llama-cpp server build |

All are keyless in practice — OpenRode marks them `keyless`, so no API key is
required.

### 3.3 Reverse proxy

For nginx, Caddy, Traefik or Cloudflare Tunnel, route to Apache/PHP and set:

- `proxy_buffering off;` — SSE must stream.
- `proxy_read_timeout 600s;` — long AI responses.

---

## 4. Ports

| Range | Purpose |
|---|---|
| Your web server | The app itself |
| **8100 – 8399** | Preview servers (allocated per project, one at a time) |

Change the range in `config.php`:

```php
'port_start' => 8100,
'port_end'   => 8399,
```

Preview servers bind to `127.0.0.1` only.

---

## 5. nginx

There is no `.htaccess` support, so translate the rules yourself:

```nginx
server {
    listen 80;
    server_name openrode.example.com;
    root /var/www/html/openrode;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # never serve runtime data or internals
    location ~ ^/(data|lib|views)/ { deny all; return 404; }
    location ~ ^/(config|router)\.php$ { deny all; return 404; }

    # streaming must not buffer
    location ~ ^/api/.*/chat$ {
        proxy_buffering off;
        proxy_read_timeout 600s;
    }
}
```

---

## 6. Not required

Worth stating explicitly, since "AI website builder" usually implies them:

| | |
|---|---|
| **No database** | Projects are JSON files and plain directories. |
| **No Node.js at runtime** | The frontend is one plain `.js` file. |
| **No build step** | No bundler, no transpiler, no `npm install` to run. |
| **No Composer** | No third-party PHP dependencies. |
| **No Docker** | Optional. |
| **No framework** | Plain PHP with `strict_types`. |
| **No JavaScript framework** | No React/Vue. ~1,500 lines of vanilla JS. |

---

## 7. Verification checklist

Run through this after installing:

```bash
# 1. PHP version
php -v                                   # >= 8.1

# 2. Extensions
php -m | grep -E '^(json|curl|mbstring)$'

# 3. App responds
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/openrode/   # 200

# 4. Runtime data is not web-readable
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/openrode/data/providers.json  # 403
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/openrode/config.php          # 403

# 5. Config is not web-readable
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/openrode/router.php         # 403

# 6. Preview server can start (needs a project id)
curl -s -X POST http://localhost/openrode/api/projects/<id>/server \
     -H 'Content-Type: application/json' -d '{"action":"start"}'
```

All of the above should pass before you build anything real.