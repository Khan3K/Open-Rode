<?php
declare(strict_types=1);

require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/store.php';
require __DIR__ . '/lib/providers.php';
require __DIR__ . '/lib/ai.php';
require __DIR__ . '/lib/applier.php';
require __DIR__ . '/lib/diff.php';
require __DIR__ . '/lib/versions.php';
require __DIR__ . '/lib/skills.php';
require __DIR__ . '/lib/sandbox.php';
require __DIR__ . '/lib/runner.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && str_starts_with($uri, $base)) $uri = substr($uri, strlen($base));
$uri = '/' . ltrim($uri, '/');
if (isset($_GET['r'])) $uri = '/' . ltrim((string)$_GET['r'], '/');

if (!request_origin_ok()) json_err('Origin not allowed', 403);

/* ---------------- Views ---------------- */

if ($method === 'GET' && ($uri === '/' || $uri === '/index.php')) {
    view('home', ['projects' => list_projects()]);
}

if ($method === 'GET' && preg_match('#^/p/([a-z0-9\-]+)$#', $uri, $m)) {
    try {
        $project = load_project($m[1]);
        view('project', ['project' => $project, 'projects' => list_projects()]);
    } catch (Throwable $e) {
        http_response_code(404);
        view('home', ['projects' => list_projects(), 'error' => 'Project not found']);
    }
}

/* ---------------- API: providers / models ---------------- */

if ($uri === '/api/catalog' && $method === 'GET') {
    try { json_out(['ok' => true, 'catalog' => provider_catalog()]); }
    catch (Throwable $e) { json_out(['ok' => true, 'catalog' => []]); }
}

if ($uri === '/api/providers' && $method === 'GET') {
    $out = [];
    foreach (providers_all() as $p) {
        $out[] = [
            'id' => $p['id'], 'name' => $p['name'], 'base_url' => $p['base_url'],
            'kind' => $p['kind'] ?? 'openai', 'models' => $p['models'] ?? [],
            'api_key_set' => !empty($p['api_key']),
            'api_key_hint' => strlen((string)($p['api_key'] ?? '')) > 8 ? '••••' . substr($p['api_key'], -4) : '',
            'keyless' => (bool)($p['keyless'] ?? false),
            'stream' => (bool)($p['stream'] ?? true),
            'key_url' => (string)($p['key_url'] ?? ''),
        ];
    }
    json_out(['ok' => true, 'providers' => $out, 'active' => providers_load()['active'],
        'presets' => provider_presets(), 'free_models' => provider_free_models()]);
}

if ($uri === '/api/providers' && $method === 'POST') {
    try { json_out(['ok' => true, 'provider' => provider_add(json_in())]); }
    catch (Throwable $e) { json_err($e->getMessage()); }
}

if (preg_match('#^/api/providers/([a-z0-9\-]+)$#', $uri, $m)) {
    $id = $m[1];
    try {
        if ($method === 'POST') json_out(['ok' => true, 'provider' => provider_update($id, json_in())]);
        if ($method === 'DELETE') { provider_delete($id); json_out(['ok' => true]); }
    } catch (Throwable $e) { json_err($e->getMessage(), 404); }
}

if (preg_match('#^/api/providers/([a-z0-9\-]+)/fetch-models$#', $uri, $m) && $method === 'POST') {
    try { json_out(['ok' => true, 'models' => provider_fetch_models($m[1])]); }
    catch (Throwable $e) { json_err($e->getMessage()); }
}

if ($uri === '/api/active' && $method === 'POST') {
    try {
        $in = json_in();
        active_set((string)($in['provider'] ?? ''), (string)($in['model'] ?? ''));
        json_out(['ok' => true, 'active' => providers_load()['active']]);
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

if ($uri === '/api/active' && $method === 'GET') {
    try { json_out(['ok' => true, 'model' => active_model(), 'active' => providers_load()['active']]); }
    catch (Throwable $e) { json_err($e->getMessage()); }
}

/* ---------------- API: projects ---------------- */

if ($uri === '/api/projects' && $method === 'GET') {
    json_out(['ok' => true, 'projects' => list_projects()]);
}

if ($uri === '/api/projects' && $method === 'POST') {
    $in = json_in();
    try {
        $project = create_project((string)($in['name'] ?? ''), (string)($in['description'] ?? ''), (string)($in['type'] ?? 'php'));
        json_out(['ok' => true, 'project' => $project]);
    } catch (Throwable $e) {
        json_err($e->getMessage());
    }
}

if (preg_match('#^/api/projects/([a-z0-9\-]+)$#', $uri, $m)) {
    $id = $m[1];
    if ($method === 'GET') {
        try { json_out(['ok' => true, 'project' => load_project($id)]); }
        catch (Throwable $e) { json_err($e->getMessage(), 404); }
    }
    if ($method === 'POST') {
        try {
            $project = load_project($id);
            $in = json_in();
            if (isset($in['apply_mode'])) $project['apply_mode'] = $in['apply_mode'] === 'auto' ? 'auto' : 'ask';
            if (isset($in['description'])) $project['description'] = trim((string)$in['description']);
            $project['updated_at'] = now();
            save_project($project);
            json_out(['ok' => true, 'project' => $project]);
        } catch (Throwable $e) { json_err($e->getMessage()); }
    }
    if ($method === 'DELETE') {
        try { delete_project($id); json_out(['ok' => true]); }
        catch (Throwable $e) { json_err($e->getMessage(), 404); }
    }
}

/* ---------------- API: sessions (multiple chats per project) ---------------- */

if (preg_match('#^/api/projects/([a-z0-9\-]+)/sessions$#', $uri, $m)) {
    $id = $m[1];
    try {
        if ($method === 'GET') json_out(['ok' => true, 'sessions' => list_sessions($id)]);
        if ($method === 'POST') {
            $in = json_in();
            json_out(['ok' => true, 'session' => create_session($id, (string)($in['title'] ?? 'New chat'))]);
        }
    } catch (Throwable $e) { json_err($e->getMessage(), 404); }
}

if (preg_match('#^/api/projects/([a-z0-9\-]+)/sessions/([a-z0-9\-]+)$#', $uri, $m) && $method === 'DELETE') {
    try { delete_session($m[1], $m[2]); json_out(['ok' => true]); }
    catch (Throwable $e) { json_err($e->getMessage(), 404); }
}

/* ---------------- API: files ---------------- */

if (preg_match('#^/api/projects/([a-z0-9\-]+)/tree$#', $uri, $m) && $method === 'GET') {
    try {
        $tree = file_tree($m[1]);
        json_out(['ok' => true, 'tree' => $tree, 'files' => flatten_files($tree),
            'undoable' => version_undoable($m[1]), 'redoable' => version_redoable($m[1]),
            'latest' => version_latest_map($m[1]), 'latest_redo' => version_latest_map($m[1], 'redo')]);
    } catch (Throwable $e) { json_err($e->getMessage(), 404); }
}

/* ---------------- API: version history (undo a bot change) ---------------- */

if (preg_match('#^/api/projects/([a-z0-9\-]+)/versions$#', $uri, $m) && $method === 'GET') {
    try {
        json_out(['ok' => true, 'versions' => version_list($m[1], (string)($_GET['path'] ?? ''))]);
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

if (preg_match('#^/api/projects/([a-z0-9\-]+)/version-comment$#', $uri, $m) && $method === 'POST') {
    $id = $m[1];
    try {
        $in = json_in();
        $path = (string)($in['path'] ?? '');
        if ($path === '') json_err('Missing path');
        json_out(['ok' => true] + version_comment($id, $path, (int)($in['index'] ?? -1), (string)($in['comment'] ?? '')));
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

/* ---------------- API: skills ---------------- */
if (preg_match('#^/api/projects/([a-z0-9\-]+)/skills$#', $uri, $m)) {
    $id = $m[1];
    try {
        if ($method === 'GET') json_out(['ok' => true, 'skills' => skills_list($id)]);
        if ($method === 'POST') json_out(['ok' => true, 'skill' => skill_save($id, json_in()), 'skills' => skills_list($id)]);
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

if (preg_match('#^/api/projects/([a-z0-9\-]+)/skills/([A-Za-z0-9_.\-]+)$#', $uri, $m)) {
    $id = $m[1];
    try {
        if ($method === 'DELETE') {
            skill_delete($id, $m[2]);
            json_out(['ok' => true, 'skills' => skills_list($id)]);
        }
    } catch (Throwable $e) { json_err($e->getMessage(), 404); }
}

/* Scratch space for the preview element picker. Kept outside files/ so it never
   shows up in the file tree or in a project download. */
if (preg_match('#^/api/projects/([a-z0-9\-]+)/pick$#', $uri, $m) && $method === 'POST') {
    $id = $m[1];
    try {
        if (!is_dir(project_dir($id))) json_err('No such project', 404);
        $html = (string)(json_in()['html'] ?? '');
        if (strlen($html) > 200000) $html = substr($html, 0, 200000);
        file_put_contents(project_dir($id) . '/.picked.html', $html);
        @chmod(project_dir($id) . '/.picked.html', 0666);
        json_out(['ok' => true, 'bytes' => strlen($html)]);
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

if (preg_match('#^/api/projects/([a-z0-9\-]+)/revert$#', $uri, $m) && $method === 'POST') {
    $id = $m[1];
    try {
        $in = json_in();
        $path = (string)($in['path'] ?? '');
        if ($path === '') json_err('Missing path');
        // exact restore of one history entry
        if (isset($in['index'])) {
            $result = version_restore($id, $path, (int)$in['index']);
        } else {
            $dir = ($in['direction'] ?? 'undo') === 'redo' ? 'redo' : 'undo';
            $result = version_step($id, $path, $dir);
        }
        $project = load_project($id);
        $project['updated_at'] = now();
        save_project($project);
        json_out(['ok' => true] + $result);
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

if (preg_match('#^/api/projects/([a-z0-9\-]+)/file$#', $uri, $m)) {
    $id = $m[1];
    try {
        if ($method === 'GET') {
            $path = (string)($_GET['path'] ?? '');
            $target = safe_file_path($id, $path);
            if (!is_file($target)) json_err('File not found', 404);
            json_out(['ok' => true, 'path' => $path, 'content' => (string)file_get_contents($target)]);
        }
        if ($method === 'POST') {
            $in = json_in();
            $path = (string)($in['path'] ?? '');
            $target = safe_file_path($id, $path);
            $content = (string)($in['content'] ?? '');
            // snapshot the previous state so the write can be undone from the file tree
            $old = is_file($target) ? (string)file_get_contents($target) : null;
            if ($old !== $content) version_push($id, $path, $old, 'manual');
            file_put_contents($target, $content);
            $project = load_project($id);
            $project['updated_at'] = now();
            save_project($project);
            json_out(['ok' => true, 'path' => $path, 'bytes' => filesize($target)]);
        }
        if ($method === 'DELETE') {
            $target = safe_file_path($id, (string)($_GET['path'] ?? ''));
            if (is_file($target)) unlink($target);
            json_out(['ok' => true]);
        }
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

/* ---------------- API: uploads (attachments) ---------------- */

if (preg_match('#^/api/projects/([a-z0-9\-]+)/upload$#', $uri, $m) && $method === 'POST') {
    $id = $m[1];
    try {
        files_dir($id);
        $f = $_FILES['files'] ?? null;
        if (!$f || !isset($f['name'][0])) json_err('No files received (field name must be "files")');
        $out = [];
        foreach ((array)$f['name'] as $i => $name) {
            if ((int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $out[] = ['error' => 'upload failed for ' . basename((string)$name)];
                continue;
            }
            $size = (int)($f['size'][$i] ?? 0);
            if ($size > 8 * 1024 * 1024) { $out[] = ['error' => basename((string)$name) . ': larger than 8 MB']; continue; }

            $base = preg_replace('/[^A-Za-z0-9._\-]+/', '-', basename((string)$name)) ?? 'file';
            $base = ltrim($base, '.-');
            if ($base === '' ) $base = 'file';
            $rel = $base;
            $pi = pathinfo($base);
            $stem = $pi['filename'] ?? $base;
            $ext = $pi['extension'] ?? '';
            $n = 2;
            while (@filesize(safe_file_path($id, $rel)) !== false) {
                $rel = $stem . '-' . $n++ . ($ext !== '' ? '.' . $ext : '');
            }
            $target = safe_file_path($id, $rel);
            $moved = is_uploaded_file((string)($f['tmp_name'][$i] ?? ''))
                ? move_uploaded_file((string)$f['tmp_name'][$i], $target)
                : @copy((string)($f['tmp_name'][$i] ?? ''), $target);
            if (!$moved) { $out[] = ['error' => 'could not save ' . basename((string)$name)]; continue; }
            $imgExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'];
            $out[] = [
                'path' => $rel,
                'bytes' => filesize($target),
                'image' => in_array(strtolower($ext), $imgExt, true),
            ];
        }
        $project = load_project($id);
        $project['updated_at'] = now();
        save_project($project);
        json_out(['ok' => true, 'files' => $out]);
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

/* ---------------- API: chat ---------------- */

if (preg_match('#^/api/projects/([a-z0-9\-]+)/messages$#', $uri, $m) && $method === 'GET') {
    try {
        $id = $m[1];
        $sid = isset($_GET['sid']) ? (string)$_GET['sid'] : ensure_default_session($id);
        json_out(['ok' => true, 'session_id' => $sid, 'messages' => load_messages($id, $sid)]);
    } catch (Throwable $e) { json_err($e->getMessage(), 404); }
}

if (preg_match('#^/api/projects/([a-z0-9\-]+)/clear$#', $uri, $m) && $method === 'POST') {
    try {
        $id = $m[1];
        $in = json_in();
        $sid = (string)($in['session_id'] ?? '') ?: ensure_default_session($id);
        save_messages($id, $sid, []);
        json_out(['ok' => true]);
    } catch (Throwable $e) { json_err($e->getMessage(), 404); }
}

if (preg_match('#^/api/projects/([a-z0-9\-]+)/chat$#', $uri, $m) && $method === 'POST') {
    $id = $m[1];
    try {
        $project = load_project($id);
        $provider = active_model();
        $input = json_in();
        $userMsg = trim((string)($input['message'] ?? ''));
        if ($userMsg === '') json_err('Empty message');
        if (empty($provider['model'])) json_err('No model selected. Open Settings and pick a model.');

        $sid = (string)($input['session_id'] ?? '') ?: ensure_default_session($id);
        $messages = load_messages($id, $sid);

        $userEntry = ['role' => 'user', 'content' => $userMsg, 'time' => now()];
        $expanded = expand_mentions($userMsg, $id);
        if ($expanded !== $userMsg) $userEntry['content_api'] = $expanded;
        $atts = [];
        foreach ((array)($input['attachments'] ?? []) as $a) {
            $path = (string)($a['path'] ?? '');
            try { $target = safe_file_path($id, $path); } catch (Throwable $e) { continue; }
            if (!is_file($target)) continue;
            $atts[] = [
                'path' => $path, 'name' => basename($path),
                'image' => !empty($a['image']), 'size' => filesize($target),
            ];
        }
        if ($atts) $userEntry['attachments'] = $atts;
        $messages[] = $userEntry;

        // attached skills become extra context for this turn only
        $skillCtx = skills_context($id, (array)($input['skills'] ?? []));
        if ($skillCtx !== '') {
            $last = count($messages) - 1;
            $messages[$last]['content'] .= $skillCtx;
            if (!isset($messages[$last]['content_api'])) $messages[$last]['content_api'] = $messages[$last]['content'];
        }

        $fileList = flatten_files(file_tree($id));

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
        @ini_set('zlib.output_compression', '0');
        @set_time_limit(0);
        ignore_user_abort(true);

        $emit = function (string $event, mixed $data): void {
            echo "event: {$event}\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level() > 0) @ob_flush();
            flush();
        };

        $emit('start', ['ok' => true, 'model' => $provider['model'], 'provider' => $provider['name'],
            'session_id' => $sid, 'context' => ai_context_usage($messages, $project, $fileList)]);
        $full = '';
        $GLOBALS['__ai_reason'] = '';
        $GLOBALS['__ai_on_reason'] = function (string $chunk) use ($emit) {
            // throttle: reasoning can fire per-token and would flood the UI
            static $n = 0;
            if ((++$n % 4) !== 0) return;
            $emit('reasoning', ['text' => $chunk]);
        };
        try {
            $full = ai_chat_stream($messages, $provider, $project, $fileList, function (string $delta) use ($emit) {
                $emit('delta', ['text' => $delta]);
            });
        } catch (Throwable $e) {
            $emit('error', ['error' => $e->getMessage()]);
            echo "event: done\n" . json_encode(['ok' => false]) . "\n\n";
            exit;
        }
        $GLOBALS['__ai_on_reason'] = null;

        $files = extract_files($full);
        $mode = ($project['apply_mode'] ?? 'ask') === 'auto' ? 'auto' : 'ask';
        $changes = $files ? changes_build($id, $files) : [];
        $writable = array_values(array_filter($changes, fn($c) => in_array($c['status'], ['new', 'modified'], true)));

        $applied = [];
        $applyError = null;
        if ($writable && $mode === 'auto') {
            try {
                $applied = apply_files($id, array_column($writable, 'content', 'path'), version_label($userMsg));
            } catch (Throwable $e) { $applyError = $e->getMessage(); }
        } elseif ($writable) {
            pending_save($id, $writable, $userMsg); // ask mode: wait for approval
        }

        $messages[] = ['role' => 'assistant', 'content' => $full, 'time' => now(), 'files' => array_keys($files)];
        save_messages($id, $sid, $messages);

        $project = load_project($id);
        $project['updated_at'] = now();
        save_project($project);

        $publicChanges = array_map(function ($c) {
            $out = array_diff_key($c, ['content' => 1]);
            // keep the SSE payload sane: a full-file diff can be thousands of rows
            if (is_array($out['diff'] ?? null) && count($out['diff']) > 400) {
                $out['diff'] = array_slice($out['diff'], 0, 400);
                $out['diff_capped'] = true;
            }
            return $out;
        }, $writable);
        $emit('files', [
            'applied' => $applied,
            'error' => $applyError,
            'mode' => $mode,
            'pending' => $mode === 'ask',
            'changes' => $publicChanges,
            'truncated' => !empty($GLOBALS['__files_truncated']),
        ]);
        $emit('done', ['ok' => true, 'files' => array_keys($files), 'session_id' => $sid,
            'truncated' => !empty($GLOBALS['__files_truncated'])]);
        exit;
    } catch (Throwable $e) {
        json_err($e->getMessage(), 500);
    }
}

/* ---------------- API: pending changes (approve / reject) ---------------- */

if (preg_match('#^/api/projects/([a-z0-9\-]+)/changes$#', $uri, $m)) {
    $id = $m[1];
    try {
        if ($method === 'GET') json_out(['ok' => true, 'changes' => pending_load($id)]);
        if ($method === 'POST') {
            $in = json_in();
            $action = (string)($in['action'] ?? 'apply');
            $changes = pending_load($id);
            if (!$changes) json_err('No pending changes');
            $paths = isset($in['paths']) ? array_values(array_map('strval', (array)$in['paths'])) : null;
            $subset = $paths === null ? $changes
                : array_values(array_filter($changes, fn($c) => in_array($c['path'], $paths, true)));
            if (!$subset) json_err('Nothing selected');

            if ($action === 'reject') {
                pending_forget($id, $paths);
                json_out(['ok' => true, 'rejected' => array_column($subset, 'path')]);
            }
            $applied = apply_files($id, array_column($subset, 'content', 'path'),
                version_label(pending_note($id) !== '' ? pending_note($id) : 'approved changes'));
            pending_forget($id, array_column($subset, 'path'));
            $project = load_project($id);
            $project['updated_at'] = now();
            save_project($project);
            json_out(['ok' => true, 'applied' => $applied]);
        }
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

/* ---------------- API: run / test / zip ---------------- */

if (preg_match('#^/api/projects/([a-z0-9\-]+)/server$#', $uri, $m)) {
    $id = $m[1];
    $in = json_in();
    $action = (string)($in['action'] ?? 'status');
    try {
        $result = match ($action) {
            'start' => start_server($id),
            'stop'  => stop_server($id),
            default => server_status($id),
        };
        json_out(['ok' => true, 'server' => $result]);
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

if (preg_match('#^/api/projects/([a-z0-9\-]+)/test$#', $uri, $m) && $method === 'POST') {
    try { json_out(['ok' => true, 'results' => run_tests($m[1])]); }
    catch (Throwable $e) { json_err($e->getMessage()); }
}

if (preg_match('#^/api/projects/([a-z0-9\-]+)/zip$#', $uri, $m) && $method === 'GET') {
    try {
        $id = $m[1];
        $project = load_project($id);
        $file = project_zip($id);
        $isZip = str_ends_with($file, '.zip');
        header('Content-Type: ' . ($isZip ? 'application/zip' : 'application/x-tar'));
        header('Content-Disposition: attachment; filename="' . $project['id'] . ($isZip ? '.zip' : '.tar') . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    } catch (Throwable $e) { json_err($e->getMessage()); }
}

/* ---------------- Preview (in-process sandbox) ---------------- */

if (preg_match('#^/preview/([a-z0-9\-]+)(/.*)?$#', $uri, $m)) {
    $id = $m[1];
    $path = $m[2] ?? '/';
    try {
        $project = load_project($id);
    } catch (Throwable $e) {
        http_response_code(404);
        echo sandbox_page('Not found', '<h1>404</h1><p>Project not found.</p>');
        exit;
    }
    $status = server_status($id);
    $isTestProbe = ($_SERVER['HTTP_X_SANDBOX_TEST'] ?? '') === '1';
    if (!$status['running'] && !$isTestProbe) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo sandbox_page('Preview stopped', '<h1>&#9209;</h1><p>Preview is stopped for <b>' . htmlspecialchars($project['name']) . '</b>.<br>Press <b>Run</b> in the toolbar to start it.</p>');
        exit;
    }
    sandbox_request($id, $path); // never returns
}

/* ---------------- 404 ---------------- */

http_response_code(404);
if ($method === 'GET' && !str_starts_with($uri, '/api/')) {
    view('home', ['projects' => list_projects(), 'error' => 'Page not found']);
}
json_err('Not found', 404);
