<?php
declare(strict_types=1);

$GLOBALS['__config'] = $GLOBALS['__config'] ?? require __DIR__ . '/../config.php';

function cfg(string $key, mixed $default = null): mixed
{
    return $GLOBALS['__config'][$key] ?? $default;
}

/** config.php defaults, overridden by data/settings.json (set from the UI). */
function settings(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $base = array_intersect_key($GLOBALS['__config'], array_flip(['api_key', 'base_url', 'model', 'temperature', 'max_tokens']));
    $file = data_dir() . '/settings.json';
    if (is_file($file)) {
        $saved = json_decode((string)file_get_contents($file), true);
        if (is_array($saved)) $base = array_merge($base, array_intersect_key($saved, $base));
    }
    return $cache = $base;
}

function save_settings(array $in): array
{
    $current = settings();
    foreach (['api_key', 'base_url', 'model'] as $k) {
        if (isset($in[$k]) && trim((string)$in[$k]) !== '') $current[$k] = trim((string)$in[$k]);
    }
    if (isset($in['temperature'])) $current['temperature'] = (float)$in['temperature'];
    if (isset($in['max_tokens'])) $current['max_tokens'] = (int)$in['max_tokens'];
    file_put_contents(data_dir() . '/settings.json', json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $current;
}

function json_in(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $message, int $status = 400): never
{
    json_out(['ok' => false, 'error' => $message], $status);
}

function view(string $template, array $vars = []): never
{
    extract($vars);
    $base = base_path();
    ob_start();
    include __DIR__ . '/../views/' . $template . '.php';
    $content = ob_get_clean();
    include __DIR__ . '/../views/layout.php';
    exit;
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    if (PHP_SAPI === 'cli-server') {
        // Built-in server: router.php serves everything from the doc root
        return $base = '';
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($base === '/' || $base === '.') $base = '';
    return $base;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** url() with a cache-busting version so edited assets always reload. */
function asset(string $path): string
{
    $file = __DIR__ . '/../' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : time();
    return url($path) . '?v=' . $v;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function request_origin_ok(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if (!$origin) return true;
    $host = parse_url($origin, PHP_URL_HOST);
    return $host === ($_SERVER['HTTP_HOST'] ?? '') || $host === '127.0.0.1' || $host === 'localhost';
}

function free_port(): int
{
    $from = (int)cfg('port_start', 8100);
    $to   = (int)cfg('port_end', 8399);
    for ($i = 0; $i < 400; $i++) {
        $port = random_int($from, $to);
        $sock = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
        if ($sock) { fclose($sock); return $port; }
    }
    throw new RuntimeException('No free port found');
}

function port_in_use(int $port): bool
{
    $sock = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
    if ($sock) { fclose($sock); return false; }
    return true;
}
