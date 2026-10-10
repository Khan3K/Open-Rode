<?php
declare(strict_types=1);

/**
 * In-process preview runtime.
 *
 * Executes the project's own PHP files inside the app request with full
 * output/error capture. Needs NO shell, NO ports and NO php -S, so it works
 * on locked-down shared hosting (Hostinger) as well as locally.
 */

function sandbox_mimes(): array
{
    return [
        'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8',
        'php' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8', 'mjs' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8', 'xml' => 'application/xml; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8', 'md' => 'text/plain; charset=utf-8',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
        'pdf' => 'application/pdf', 'zip' => 'application/zip', 'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'webmanifest' => 'application/manifest+json',
    ];
}

/** Resolve a preview URL path to a real file inside the project. */
function sandbox_resolve(string $id, string $path): array
{
    $root = realpath(files_dir($id));
    $path = str_replace('\\', '/', (string)$path);
    $path = strtok($path, '?#') ?: '/';
    $rel = trim($path, '/');

    $candidates = [];
    if ($rel === '') {
        $candidates = ['index.php', 'index.html', 'index.htm'];
    } else {
        if (!preg_match('#^(?!/)(?!.*\.\.)([A-Za-z0-9_\-./]+)$#', $rel)) return ['ok' => false, 'rel' => $path];
        $candidates = [$rel];
        if (is_dir($root . '/' . $rel)) $candidates = [$rel . '/index.php', $rel . '/index.html', $rel . '/index.htm'];
    }

    foreach ($candidates as $cand) {
        $full = $root . '/' . $cand;
        if (is_file($full)) {
            $real = realpath($full);
            if ($real && str_starts_with($real, $root)) {
                return ['ok' => true, 'file' => $real, 'rel' => $cand];
            }
        }
    }
    return ['ok' => false, 'rel' => $rel ?: 'index'];
}

/* ------------------------------------------------------------------ */
/* Request handling                                                    */
/* ------------------------------------------------------------------ */

function sandbox_request(string $id, string $path): never
{
    $res = sandbox_resolve($id, $path);

    if (!$res['ok']) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo sandbox_page('404 Not Found', '<h1>404</h1><p>File <code>' . htmlspecialchars($res['rel']) . '</code> not found in this project.</p>');
        exit;
    }

    $file = $res['file'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if ($ext !== 'php') {
        $mime = sandbox_mimes()[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-cache');
        readfile($file);
        exit;
    }

    // --- PHP execution environment -------------------------------------
    chdir(dirname($file));

    $previewBase = base_path() . '/preview/' . $id . '/' . $res['rel'];
    $_SERVER['SCRIPT_NAME'] = $previewBase;
    $_SERVER['PHP_SELF'] = $previewBase;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['DOCUMENT_ROOT'] = dirname($file);
    $_SERVER['REQUEST_URI'] = ($_SERVER['REQUEST_URI'] ?? $previewBase);
    $_SERVER['PATH_INFO'] = null;
    $_SERVER['argv'] = ['php'];
    $_SERVER['argc'] = 1;

    if (!header('Content-Type: text/html; charset=utf-8', true)) { /* project may override later */ }

    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '0');
    date_default_timezone_set(ini_get('date.timezone') ?: 'UTC');

    $GLOBALS['__sandbox'] = ['base' => ob_get_level(), 'capture' => false];
    register_shutdown_function('sandbox_shutdown');
    ob_start();

    try {
        require $file;
    } catch (Throwable $e) {
        echo sandbox_error_html($e->getMessage(), $e->getFile(), $e->getLine(),
            $e::class, (string)$e->getTraceAsString());
    }

    sandbox_shutdown(); // normal completion (require returned without exit)
    exit;
}

/**
 * Capture-mode render used as a fallback for tests when loopback HTTP
 * is unavailable. Prints nothing - returns the result instead.
 */
function sandbox_capture(string $id, string $path): array
{
    $res = sandbox_resolve($id, $path);
    if (!$res['ok']) return ['status' => 404, 'body' => '', 'errors' => ['file not found: ' . $res['rel']]];

    $file = $res['file'];
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
        return ['status' => 200, 'body' => (string)file_get_contents($file), 'errors' => []];
    }

    $prevGet = $_GET; $prevPost = $_POST; $prevServer = $_SERVER;
    $prevCwd = getcwd();

    chdir(dirname($file));
    $previewBase = base_path() . '/preview/' . $id . '/' . $res['rel'];
    $_SERVER['SCRIPT_NAME'] = $previewBase;
    $_SERVER['PHP_SELF'] = $previewBase;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['DOCUMENT_ROOT'] = dirname($file);
    $_SERVER['REQUEST_URI'] = $previewBase;
    $_SERVER['QUERY_STRING'] = '';
    $_GET = []; $_POST = [];

    error_reporting(E_ALL);
    ini_set('display_errors', '1');

    $GLOBALS['__sandbox'] = ['base' => ob_get_level(), 'capture' => true];
    register_shutdown_function('sandbox_shutdown');
    ob_start();

    try {
        require $file;
    } catch (Throwable $e) {
        echo sandbox_error_html($e->getMessage(), $e->getFile(), $e->getLine(), $e::class, '');
    }

    $body = sandbox_collect();
    unset($GLOBALS['__sandbox']);
    $status = http_response_code() ?: 200;

    $_GET = $prevGet; $_POST = $prevPost; $_SERVER = $prevServer;
    if ($prevCwd) @chdir($prevCwd);

    return ['status' => $status, 'body' => $body, 'errors' => sandbox_find_errors($body)];
}

/** Flush nested buffers into ours and print (or store) the result. */
function sandbox_shutdown(): void
{
    $state = $GLOBALS['__sandbox'] ?? null;
    if (!$state) return;
    unset($GLOBALS['__sandbox']);

    $body = sandbox_collect();
    $fatal = error_get_last();
    $isFatal = $fatal && in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);

    if ($isFatal && !str_contains($body, $fatal['message'])) {
        $body .= sandbox_error_html($fatal['message'], $fatal['file'], $fatal['line'], 'Fatal Error', '');
    }

    if (!empty($state['capture'])) {
        $GLOBALS['__sandbox_body'] = $body;
        return;
    }

    if (!headers_sent()) {
        if ($isFatal) http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo $body;
}

function sandbox_collect(): string
{
    $state = $GLOBALS['__sandbox'] ?? ['base' => ob_get_level()];
    $base = (int)($state['base'] ?? ob_get_level());
    // any buffers the project opened on top of ours must be flushed into ours first
    while (ob_get_level() > $base + 1) {
        ob_end_flush();
    }
    $out = '';
    if (ob_get_level() === $base + 1) {
        $out = (string)ob_get_clean();
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Loopback HTTP (preferred test transport - project code may exit())  */
/* ------------------------------------------------------------------ */

function self_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . base_path();
}

function sandbox_fetch(string $id, string $path): ?array
{
    if (PHP_SAPI === 'cli-server') return null; // single-threaded: a self-request would deadlock
    if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) return null;

    $url = self_base_url() . '/preview/' . $id . '/' . ltrim($path, '/');
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => 6,
        'ignore_errors' => true,
        'header' => "Accept: text/html,*/*\r\nX-Sandbox-Test: 1\r\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return null;

    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int)$m[1];
    }
    return ['status' => $status, 'body' => $body, 'errors' => sandbox_find_errors($body), 'transport' => 'http'];
}

/** Fetch a page for testing: loopback HTTP first, in-process capture as fallback. */
function sandbox_probe(string $id, string $path, ?string $transport = null): array
{
    if ($transport !== 'in-process') {
        $res = sandbox_fetch($id, $path);
        if ($res !== null) { $res['transport'] = 'http'; return $res; }
    }
    $res = sandbox_capture($id, $path);
    $res['transport'] = 'in-process';
    return $res;
}

/* ------------------------------------------------------------------ */
/* Error discovery / rendering                                         */
/* ------------------------------------------------------------------ */

function sandbox_find_errors(string $body): array
{
    $found = [];
    foreach (['Fatal error', 'Parse error', 'Uncaught Error', 'Uncaught Exception', 'Uncaught ValueError'] as $needle) {
        $idx = strpos($body, $needle);
        if ($idx !== false) $found[] = ['level' => 'error', 'snippet' => trim(preg_replace('/\s+/', ' ', substr($body, max(0, $idx - 80), 320)) ?: '')];
    }
    foreach (['Warning:', 'Deprecated:', 'Notice:'] as $needle) {
        $idx = strpos($body, $needle);
        if ($idx !== false) $found[] = ['level' => 'warning', 'snippet' => trim(preg_replace('/\s+/', ' ', substr($body, max(0, $idx - 40), 260)) ?: '')];
    }
    return $found;
}

function sandbox_error_html(string $message, string $file, int $line, string $class, string $trace): string
{
    $file = str_replace(dirname(__DIR__) . '/', '', $file);
    $src = '';
    if ($file && is_file($file) && $line > 0) {
        $lines = @file($file) ?: [];
        $from = max(0, $line - 4);
        foreach (array_slice($lines, $from, 9) as $i => $l) {
            $n = $from + $i + 1;
            $src .= '<div class="' . ($n === $line ? 'hl' : '') . '"><span class="ln">' . $n . '</span>' . htmlspecialchars(rtrim($l)) . '</div>';
        }
    }
    $q = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<div class="php-error">
  <div class="php-error-head">{$q($class)}: {$q($message)}</div>
  <div class="php-error-file">{$q($file)} on line <b>{$q((string)$line)}</b></div>
  <pre class="php-error-src">{$src}</pre>
  <details><summary>Stack trace</summary><pre>{$q($trace)}</pre></details>
</div>
<style>
.php-error{font-family:ui-monospace,Menlo,Consolas,monospace;background:#1a0f14;border:1px solid #7f1d1d;border-left:4px solid #ef4444;color:#fecaca;margin:0;padding:14px 16px;font-size:13px;line-height:1.5}
.php-error-head{color:#fca5a5;font-weight:700;white-space:pre-wrap}
.php-error-file{color:#94a3b8;margin:4px 0 10px}
.php-error-src{background:#0f0a0c;border:1px solid #3f1d24;border-radius:6px;padding:8px;margin:0 0 8px;overflow:auto}
.php-error-src .ln{display:inline-block;width:34px;color:#64748b;text-align:right;margin-right:10px;user-select:none}
.php-error-src .hl{background:#7f1d1d33;color:#fff}
.php-error details{color:#94a3b8}
.php-error pre{white-space:pre-wrap;max-height:220px;overflow:auto}
</style>
HTML;
}

function sandbox_page(string $title, string $body): string
{
    $q = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$q($title)}</title>
<style>body{margin:0;font-family:ui-monospace,Menlo,Consolas,monospace;background:#0b0d12;color:#e6e9ef;min-height:100vh;display:grid;place-items:center;text-align:center}
h1{font-size:44px;margin:0 0 8px}p{color:#8b95a7}code{background:#141822;padding:2px 6px;border-radius:5px;color:#7aa2ff}</style></head>
<body><div>{$body}</div></body></html>
HTML;
}
