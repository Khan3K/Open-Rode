<?php
declare(strict_types=1);

function wmkdir(string $dir): void
{
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    @chmod($dir, 0777); // the app may run as the CLI user or the web-server user
}

function data_dir(): string
{
    $dir = rtrim((string)cfg('data_dir'), '/');
    wmkdir($dir);
    return $dir;
}

function projects_dir(): string
{
    $dir = data_dir() . '/projects';
    wmkdir($dir);
    return $dir;
}

function slugify(string $name): string
{
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s !== '' ? $s : 'project';
}

function project_id(string $name): string
{
    return slugify($name) . '-' . substr(md5(uniqid((string)random_int(0, 999999), true)), 0, 6);
}

function project_dir(string $id): string
{
    if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,80}$/', $id)) {
        throw new RuntimeException('Invalid project id');
    }
    $dir = projects_dir() . '/' . $id;
    if (!is_dir($dir)) throw new RuntimeException('Project not found');
    return $dir;
}

function files_dir(string $id): string
{
    $dir = project_dir($id) . '/files';
    wmkdir($dir);
    return $dir;
}

function load_project(string $id): array
{
    $file = project_dir($id) . '/project.json';
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) throw new RuntimeException('Corrupt project meta');
    return $data;
}

function save_project(array $project): void
{
    $file = projects_dir() . '/' . $project['id'] . '/project.json';
    file_put_contents($file, json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    @chmod($file, 0666); // writable regardless of which user (CLI vs. web server) serves the request
}

function list_projects(): array
{
    $out = [];
    foreach (glob(projects_dir() . '/*/project.json') ?: [] as $file) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data) && isset($data['id'])) $out[] = $data;
    }
    usort($out, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
    return $out;
}

function delete_project(string $id): void
{
    $dir = project_dir($id);
    $project = load_project($id);
    if (!empty($project['server']['pid'])) {
        @exec('kill ' . (int)$project['server']['pid'] . ' 2>/dev/null');
    }
    rrmdir($dir);
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        is_dir($path) ? rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

/* ---------------- sessions (multiple chats per project) ---------------- */

function sessions_dir(string $id): string
{
    $dir = project_dir($id) . '/sessions';
    wmkdir($dir);
    return $dir;
}

function session_file(string $id, string $sid): string
{
    if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,60}$/', $sid)) throw new RuntimeException('Invalid session id');
    return sessions_dir($id) . '/' . $sid . '.json';
}

function session_id_new(): string
{
    return 's-' . substr(md5(uniqid((string)random_int(0, 999999), true)), 0, 8);
}

function create_session(string $id, string $title = 'New chat'): array
{
    $session = [
        'id' => session_id_new(),
        'title' => trim($title) !== '' ? trim($title) : 'New chat',
        'created_at' => now(),
        'updated_at' => now(),
        'messages' => [],
    ];
    file_put_contents(sessions_dir($id) . '/' . $session['id'] . '.json',
        json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $session;
}

function load_session(string $id, string $sid): array
{
    $file = session_file($id, $sid);
    if (!is_file($file)) throw new RuntimeException('Session not found');
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data) || !isset($data['id'])) throw new RuntimeException('Corrupt session');
    $data['messages'] = is_array($data['messages'] ?? null) ? $data['messages'] : [];
    return $data;
}

function save_session(string $id, array $session): void
{
    file_put_contents(session_file($id, $session['id']),
        json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function delete_session(string $id, string $sid): void
{
    $file = session_file($id, $sid);
    if (is_file($file)) unlink($file);
    ensure_default_session($id);
}

function list_sessions(string $id): array
{
    ensure_default_session($id);
    $out = [];
    foreach (glob(sessions_dir($id) . '/*.json') ?: [] as $file) {
        $s = json_decode((string)file_get_contents($file), true);
        if (!is_array($s) || !isset($s['id'])) continue;
        $out[] = [
            'id' => $s['id'],
            'title' => $s['title'] ?? 'Chat',
            'created_at' => $s['created_at'] ?? '',
            'updated_at' => $s['updated_at'] ?? '',
            'messages' => count(is_array($s['messages'] ?? null) ? $s['messages'] : []),
        ];
    }
    usort($out, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
    return $out;
}

/** Returns the most recently used session id, migrating a legacy messages.json if present. */
function ensure_default_session(string $id): string
{
    $files = glob(sessions_dir($id) . '/*.json') ?: [];
    if ($files) {
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $s = json_decode((string)file_get_contents($files[0]), true);
        return is_array($s) && isset($s['id']) ? $s['id'] : create_session($id)['id'];
    }
    // migrate legacy single-chat file
    $legacy = project_dir($id) . '/messages.json';
    if (is_file($legacy)) {
        $msgs = json_decode((string)file_get_contents($legacy), true);
        $session = create_session($id, 'General');
        if (is_array($msgs) && $msgs) {
            $session['messages'] = $msgs;
            save_session($id, $session);
        }
        unlink($legacy);
        return $session['id'];
    }
    return create_session($id, 'General')['id'];
}

function load_messages(string $id, string $sid): array
{
    return load_session($id, $sid)['messages'];
}

function save_messages(string $id, string $sid, array $messages): void
{
    $session = load_session($id, $sid);
    $session['messages'] = $messages;
    $session['updated_at'] = now();
    if (($session['title'] ?? '') === 'New chat' || $session['title'] === '') {
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'user' && trim((string)($m['content'] ?? '')) !== '') {
                $t = trim((string)$m['content']);
                $session['title'] = mb_substr(preg_replace('/\s+/', ' ', $t) ?? $t, 0, 48);
                break;
            }
        }
    }
    save_session($id, $session);
}

function create_project(string $name, string $description, string $type): array
{
    $name = trim($name);
    if ($name === '') throw new RuntimeException('Project name is required');
    if (!in_array($type, ['php', 'static'], true)) $type = 'php';

    $id = project_id($name);
    $dir = projects_dir() . '/' . $id;
    wmkdir($dir);
    wmkdir($dir . '/files');

    $project = [
        'id' => $id,
        'name' => $name,
        'description' => trim($description),
        'type' => $type,
        'created_at' => now(),
        'updated_at' => now(),
        'apply_mode' => 'ask', // 'ask' = show diff & approve, 'auto' = apply silently
        'server' => ['port' => null, 'pid' => null, 'running' => false],
    ];
    save_project($project);
    create_session($id, 'General');

    if ($type === 'php') {
        file_put_contents($dir . '/files/index.php', starter_php($name));
        file_put_contents($dir . '/files/style.css', starter_css());
    } else {
        file_put_contents($dir . '/files/index.html', starter_html($name));
    }
    return $project;
}

function starter_php(string $name): string
{
    return <<<PHP
<?php
\$title = '{$name}';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$name}</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="hero">
    <h1>{$name}</h1>
    <p>Your project is ready. Ask the agent to build something.</p>
  </main>
</body>
</html>
PHP;
}

function starter_css(): string
{
    return "*,*::before,*::after{box-sizing:border-box}\nbody{margin:0;font-family:system-ui,sans-serif;background:#0b0d12;color:#e6e9ef;line-height:1.6}\n.hero{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:.5rem;padding:2rem}\nh1{font-size:clamp(2rem,6vw,3.5rem);margin:0}\np{color:#9aa3b2;margin:0}\n";
}

function starter_html(string $name): string
{
    return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$name}</title>
  <style>
    body{margin:0;font-family:system-ui,sans-serif;background:#0b0d12;color:#e6e9ef;min-height:100vh;display:grid;place-items:center;text-align:center}
  </style>
</head>
<body>
  <div><h1>{$name}</h1><p>Your project is ready.</p></div>
</body>
</html>
HTML;
}

/** Resolve a relative path inside the project files dir, safe against traversal. */
function safe_file_path(string $id, string $relative): string
{
    $relative = str_replace('\\', '/', trim($relative));
    $relative = ltrim($relative, '/');
    if ($relative === '' || str_contains($relative, "\0")) throw new RuntimeException('Invalid path');
    $parts = [];
    foreach (explode('/', $relative) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') throw new RuntimeException('Path traversal not allowed');
        $parts[] = $part;
    }
    if (!$parts) throw new RuntimeException('Invalid path');
    $root = realpath(files_dir($id));
    $target = $root . '/' . implode('/', $parts);
    $dir = dirname($target);
    @wmkdir($dir);
    $realDir = realpath($dir);
    if ($realDir === false || !str_starts_with($realDir, $root)) throw new RuntimeException('Path outside project');
    return $target;
}

function file_tree(string $id): array
{
    $root = realpath(files_dir($id));

    $walk = function (string $dir) use (&$walk, $root): array {
        $nodes = [];
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.DS_Store') continue;
            $path = $dir . '/' . $item;
            $rel = ltrim(substr($path, strlen($root)), '/');
            if (is_dir($path)) {
                $nodes[] = ['path' => $rel, 'type' => 'dir', 'children' => $walk($path)];
            } else {
                $nodes[] = ['path' => $rel, 'type' => 'file', 'size' => filesize($path), 'mtime' => filemtime($path)];
            }
        }
        usort($nodes, fn($a, $b) => [$a['type'] === 'dir' ? 0 : 1, $a['path']] <=> [$b['type'] === 'dir' ? 0 : 1, $b['path']]);
        return $nodes;
    };
    return $walk($root);
}

function flatten_files(array $tree): array
{
    $out = [];
    $it = function (array $nodes) use (&$it, &$out) {
        foreach ($nodes as $node) {
            if ($node['type'] === 'file') $out[] = $node['path'];
            else $it($node['children'] ?? []);
        }
    };
    $it($tree);
    sort($out);
    return $out;
}

function project_file_count(string $id): int
{
    try {
        $root = realpath(files_dir($id));
    } catch (Throwable $e) {
        return 0;
    }
    if (!$root) return 0;
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) $n++;
    return $n;
}
