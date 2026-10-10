<?php
declare(strict_types=1);

function preview_url(string $id): string
{
    return url('preview/' . $id . '/');
}

function shell_available(): bool
{
    if (function_exists('shell_exec')) {
        $out = @shell_exec('echo ok 2>/dev/null');
        if (is_string($out) && str_contains($out, 'ok')) return true;
    }
    return false;
}

/** Locate a PHP CLI binary when shell access exists (for php -l linting). */
function php_cli_bin(): ?string
{
    if (!shell_available()) return null;
    $candidates = [
        PHP_BINDIR . '/php',
        (string)getenv('OPENRODE_PHP'),
        '/usr/bin/php',
        '/usr/local/bin/php',
        '/opt/homebrew/bin/php',   // macOS (Apple Silicon)
        '/opt/local/bin/php',      // MacPorts
    ];
    foreach ($candidates as $c) {
        if ($c !== '' && is_file($c) && is_executable($c)) return $c;
    }
    $found = trim((string)@shell_exec('command -v php 2>/dev/null'));
    return $found !== '' ? $found : null;
}

/**
 * Run status. The sandbox needs no process at all - "running" only gates
 * the preview so the Run/Stop button still behaves like a real server.
 */
function server_status(string $id): array
{
    $project = load_project($id);
    $running = (bool)($project['server']['running'] ?? false);
    return [
        'running' => $running,
        'mode' => 'sandbox',
        'url' => $running ? preview_url($id) : null,
        'php' => PHP_VERSION,
    ];
}

function start_server(string $id): array
{
    $project = load_project($id);
    $project['server'] = ['running' => true, 'mode' => 'sandbox'];
    $project['updated_at'] = now();
    save_project($project);

    // warm-up: actually render the front page so Run reports real boot status
    $probe = sandbox_probe($id, 'index.php');
    $status = server_status($id);
    $status['boot'] = [
        'ok' => $probe['status'] < 500 && !errors_have_fatal($probe['errors']),
        'status' => $probe['status'],
        'errors' => array_slice($probe['errors'], 0, 3),
    ];
    return $status;
}

function errors_have_fatal(array $errors): bool
{
    foreach ($errors as $e) if (($e['level'] ?? '') === 'error') return true;
    return false;
}

function stop_server(string $id): array
{
    $project = load_project($id);
    $project['server'] = ['running' => false, 'mode' => 'sandbox'];
    $project['updated_at'] = now();
    save_project($project);
    return ['running' => false, 'mode' => 'sandbox', 'url' => null, 'php' => PHP_VERSION];
}

/* ------------------------------------------------------------------ */
/* Tests                                                               */
/* ------------------------------------------------------------------ */

function testable_pages(string $id): array
{
    $files = flatten_files(file_tree($id));
    $pages = [];
    foreach ($files as $f) {
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, ['php', 'html', 'htm'], true)) continue;
        $base = basename($f);
        if ($base[0] === '_' || preg_match('/^(config|setup|install)/i', $base)) continue;
        if (substr_count($f, '/') > 2) continue;      // skip deep includes
        $pages[] = $f;
        if (count($pages) >= 8) break;
    }
    // front page first
    usort($pages, fn($a, $b) => (int)!str_starts_with(basename($a), 'index') <=> (int)!str_starts_with(basename($b), 'index'));
    return $pages;
}

function run_tests(string $id): array
{
    $results = ['passed' => true, 'lint' => [], 'pages' => [], 'php' => PHP_VERSION];
    $php = php_cli_bin();

    // 1) syntax lint (shell only - shared hosts may disable it)
    if ($php) {
        foreach (flatten_files(file_tree($id)) as $rel) {
            if (!str_ends_with(strtolower($rel), '.php')) continue;
            $file = files_dir($id) . '/' . $rel;
            $out = [];
            $code = 0;
            exec(escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
            $ok = $code === 0;
            $results['lint'][] = ['path' => $rel, 'ok' => $ok, 'output' => trim(implode("\n", $out))];
            if (!$ok) $results['passed'] = false;
        }
    } else {
        $results['lint_note'] = 'Shell access disabled on this host - syntax lint skipped, runtime checks below still ran.';
    }

    // 2) runtime render of every page through the sandbox.
    // Transport is decided once: if a loopback self-request fails (single-worker
    // host, allow_url_fopen off), fall back to in-process rendering for all pages.
    $transport = null;
    foreach (testable_pages($id) as $page) {
        $probe = sandbox_probe($id, $page, $transport);
        $transport = $probe['transport'] ?? 'http';
        $errors = $probe['errors'] ?? [];
        $fatals = array_filter($errors, fn($e) => ($e['level'] ?? '') === 'error');
        $warnings = array_filter($errors, fn($e) => ($e['level'] ?? '') === 'warning');
        $ok = $probe['status'] < 500 && !$fatals;
        $results['pages'][] = [
            'path' => $page,
            'ok' => $ok,
            'status' => $probe['status'],
            'bytes' => strlen($probe['body']),
            'transport' => $probe['transport'] ?? 'http',
            'errors' => array_map(fn($e) => $e['snippet'], array_values($fatals)),
            'warnings' => array_map(fn($e) => $e['snippet'], array_values($warnings)),
        ];
        if (!$ok) $results['passed'] = false;
    }

    if (!$results['pages']) {
        $results['pages'][] = ['path' => '-', 'ok' => false, 'status' => 0, 'bytes' => 0,
            'errors' => ['No entry page found - create index.php or index.html']];
        $results['passed'] = false;
    }

    // 3) required assets referenced by the front page should exist
    $front = files_dir($id) . '/index.php';
    if (!is_file($front)) $front = files_dir($id) . '/index.html';
    if (is_file($front)) {
        $html = (string)file_get_contents($front);
        preg_match_all('/(?:href|src)=["\']([^"\'#?]+)["\']/', $html, $mm);
        $missing = [];
        foreach (array_unique($mm[1]) as $ref) {
            if (preg_match('#^(https?:)?//#i', $ref) || str_starts_with($ref, 'data:')) continue;
            $target = files_dir($id) . '/' . ltrim($ref, '/');
            if (!is_file($target)) $missing[] = $ref;
        }
        $results['assets'] = ['ok' => !$missing, 'missing' => $missing];
        if ($missing) $results['passed'] = false;
    }

    return $results;
}

/* ------------------------------------------------------------------ */
/* Zip export                                                          */
/* ------------------------------------------------------------------ */

function project_zip(string $id): string
{
    if (!class_exists('ZipArchive')) {
        $tmp = gzip_project($id);
        return $tmp; // .tar.gz fallback
    }
    $project = load_project($id);
    $zipFile = project_dir($id) . '/' . $project['id'] . '.zip';
    if (is_file($zipFile)) @unlink($zipFile);

    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create zip');
    }
    $root = realpath(files_dir($id));
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $rel = ltrim(substr($file->getPathname(), strlen($root)), '/');
        $zip->addFile($file->getPathname(), $project['id'] . '/' . $rel);
    }
    $zip->close();
    if (!is_file($zipFile)) throw new RuntimeException('Zip failed');
    return $zipFile;
}

function gzip_project(string $id): string
{
    $project = load_project($id);
    $tar = project_dir($id) . '/' . $project['id'] . '.tar';
    $root = realpath(files_dir($id));
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) $files[] = $f->getPathname();
    if (function_exists('exec')) {
        exec('cd ' . escapeshellarg(dirname($root)) . ' && tar cf ' . escapeshellarg($tar) . ' ' . escapeshellarg(basename($root)) . ' 2>/dev/null', $o, $code);
        if ($code === 0 && is_file($tar)) return $tar;
    }
    throw new RuntimeException('ZipArchive and tar are both unavailable on this host');
}
