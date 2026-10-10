<?php
declare(strict_types=1);

/**
 * Extract files from an assistant message.
 * Recognized fences: ```lang:path.ext  or  ```file:path.ext  or ```path.ext (rare, only if it looks like a file)
 */
function extract_files(string $text): array
{
    $files = [];
    $GLOBALS['__files_truncated'] = false;
    $pattern = '/```([A-Za-z0-9_+\-.]*)\s*:?([^\n`]*)\n(.*?)```/s';

    if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
        $matches = [];
    }

    $consumed = 0;
    foreach ($matches as $m) {
        $lang = strtolower(trim($m[1]));
        $info = trim($m[2]);
        $code = $m[3];
        $offset = $m[0] !== '' ? (int)strpos($text, $m[0]) : 0;
        $consumed = max($consumed, $offset + strlen($m[0]));

        $path = null;
        if (in_array($lang, ['file', 'filepath', 'filename'], true) && $info !== '') {
            $path = $info;
        } elseif ($info !== '' && str_contains($info, '/') || ($info !== '' && str_contains($info, '.'))) {
            // ```php:index.php  -> lang=php, info=index.php
            $path = $info;
        }

        if ($path === null) continue;
        $path = trim($path, " \t\"'");
        if (!is_valid_file_path($path)) continue;

        // Strip one leading newline produced by the fence
        $code = preg_replace('/^\n/', '', $code) ?? $code;
        $files[$path] = rtrim($code, "\n") . "\n";
    }

    // A response that ran out of tokens leaves the last block unterminated.
    // Salvage it instead of silently dropping the whole edit.
    $tail = substr($text, $consumed);
    if (preg_match('/```([A-Za-z0-9_+\-.]*)\s*:?([^\n`]*)\n(.*)$/s', $tail, $t)) {
        $lang = strtolower(trim($t[1]));
        $info = trim($t[2]);
        $code = preg_replace('/^\n/', '', $t[3]) ?? $t[3];
        $path = null;
        if (in_array($lang, ['file', 'filepath', 'filename'], true) && $info !== '') $path = $info;
        elseif ($info !== '' && (str_contains($info, '/') || str_contains($info, '.'))) $path = $info;
        $path = $path !== null ? trim($path, " \t\"'") : '';
        if ($path !== '' && is_valid_file_path($path) && !isset($files[$path])) {
            $files[$path] = rtrim($code, "\n") . "\n";
            $GLOBALS['__files_truncated'] = true;
        }
    }

    return $files;
}

function is_valid_file_path(string $path): bool
{
    if (strlen($path) > 200) return false;
    if (preg_match('#^(https?:)?//#i', $path)) return false;
    if (str_contains($path, '\\') || str_contains($path, "\0")) return false;
    if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) return false;
    foreach (explode('/', $path) as $part) {
        if ($part === '..' || $part === '') return false;
    }
    return (bool)preg_match('/\.[A-Za-z0-9]{1,12}$/', $path);
}

/** Write extracted files into the project. Returns a report list. */
function apply_files(string $id, array $files, string $label = 'chat'): array
{
    $applied = [];
    foreach ($files as $rel => $content) {
        $target = safe_file_path($id, $rel);
        // snapshot the previous state so the change can be undone from the file tree
        $existed = is_file($target);
        $old = $existed ? (string)file_get_contents($target) : null;
        if ($old !== $content) version_push($id, $rel, $old, $label);
        $ok = file_put_contents($target, $content);
        if ($ok === false) throw new RuntimeException("Could not write {$rel}");
        $applied[] = ['path' => $rel, 'bytes' => strlen($content)];
    }
    return $applied;
}

function list_changed_files(string $text): array
{
    return array_keys(extract_files($text));
}
