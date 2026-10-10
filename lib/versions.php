<?php
declare(strict_types=1);

/**
 * Per-file version history: undo, redo and human comments.
 *
 * A snapshot is recorded *before* a file is overwritten, so the top of the
 * undo list is always "what this file looked like before the most recent write".
 *
 * Undo pops that snapshot and stashes what was on disk into the redo list;
 * redo puts it back the other way round. That gives real undo/redo instead of
 * the old revert-as-toggle, and every snapshot can carry a comment explaining
 * what changed (e.g. the chat message that triggered it).
 *
 * Stored in <project>/.versions.json — outside files/, so it never shows up in
 * the file tree or in a project download. The legacy flat layout
 * ({"path": [...]}) is read transparently and upgraded on first write.
 */

const VERSION_LIMIT = 25;            // snapshots kept per file (per direction)
const VERSION_STORE_MAX = 5242880;   // 5 MB — trim oldest when exceeded

function versions_file(string $id): string
{
    return project_dir($id) . '/.versions.json';
}

/** @return array{files: array, redo: array} */
function versions_read(string $id): array
{
    $f = versions_file($id);
    if (!is_file($f)) return ['files' => [], 'redo' => []];
    $raw = json_decode((string)file_get_contents($f), true);
    if (!is_array($raw)) return ['files' => [], 'redo' => []];
    if (isset($raw['files']) && is_array($raw['files'])) {
        return ['files' => $raw['files'], 'redo' => is_array($raw['redo'] ?? null) ? $raw['redo'] : []];
    }
    return ['files' => $raw, 'redo' => []]; // legacy flat layout
}

function versions_write(string $id, array $files, array $redo): void
{
    $clean = function (array $list): array {
        $list = array_values(array_filter($list, 'is_array'));
        if (count($list) > VERSION_LIMIT) $list = array_slice($list, -VERSION_LIMIT);
        return $list;
    };
    $out = [];
    foreach ($files as $rel => $list) { $list = $clean($list); if ($list) $out[(string)$rel] = $list; }
    $redoOut = [];
    foreach ($redo as $rel => $list) { $list = $clean($list); if ($list) $redoOut[(string)$rel] = $list; }

    $payload = ['files' => $out, 'redo' => $redoOut];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return;
    if (strlen($json) > VERSION_STORE_MAX) {
        $payload = versions_trim($payload, VERSION_STORE_MAX / 2);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) return;
    }
    file_put_contents(versions_file($id), $json);
    @chmod(versions_file($id), 0666); // the app may run as the CLI user or the web-server user
}

/** Drop the oldest snapshots (across both directions) until the store fits. */
function versions_trim(array $payload, int $budget): array
{
    $all = [];
    foreach (['files', 'redo'] as $dir) {
        foreach (($payload[$dir] ?? []) as $rel => $list) {
            foreach ($list as $i => $snap) {
                $all[] = ['dir' => $dir, 'rel' => $rel, 'i' => $i, 'ts' => (int)($snap['ts'] ?? 0),
                    'size' => strlen((string)($snap['content'] ?? ''))];
            }
        }
    }
    usort($all, fn($a, $b) => $a['ts'] <=> $b['ts']);
    $total = array_sum(array_column($all, 'size'));
    $drop = [];
    foreach ($all as $e) {
        if ($total <= $budget) break;
        $total -= $e['size'];
        $drop[$e['dir']][$e['rel']][] = $e['i'];
    }
    foreach ($drop as $dir => $byRel) {
        foreach ($byRel as $rel => $indexes) {
            foreach ($indexes as $i) unset($payload[$dir][$rel][$i]);
            $payload[$dir][$rel] = array_values($payload[$dir][$rel]);
        }
    }
    return $payload;
}

function version_snap(?string $content, string $label): array
{
    return [
        'ts'      => time(),
        'label'   => $label !== '' ? $label : 'change',
        'comment' => '',
        'bytes'   => $content === null ? 0 : strlen($content),
        'content' => $content,
    ];
}

/**
 * Turn a chat message into a short, readable version comment.
 * Collapses whitespace and clips, so the history list stays scannable.
 */
function version_label(string $text, int $max = 70): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    if ($text === '') return 'change';
    if (function_exists('mb_strlen') && mb_strlen($text) > $max) $text = mb_substr($text, 0, $max - 1) . '…';
    elseif (strlen($text) > $max) $text = substr($text, 0, $max - 3) . '...';
    return $text;
}

/**
 * Record what a file looked like before it was overwritten.
 * Pass null as $content when the file did not exist yet (undo then deletes it).
 */
function version_push(string $id, string $rel, ?string $content, string $label = 'change'): void
{
    $d = versions_read($id);
    $list = is_array($d['files'][$rel] ?? null) ? $d['files'][$rel] : [];
    $list[] = version_snap($content, $label);
    if (count($list) > VERSION_LIMIT) $list = array_slice($list, -VERSION_LIMIT);
    $d['files'][$rel] = $list;
    unset($d['redo'][$rel]); // a fresh write invalidates any redo trail
    versions_write($id, $d['files'], $d['redo']);
}

/** Files that currently have at least one snapshot we can go back to. */
function version_undoable(string $id): array
{
    $out = [];
    foreach (versions_read($id)['files'] as $rel => $list) {
        if (is_array($list) && $list) $out[] = (string)$rel;
    }
    sort($out);
    return $out;
}

/** Files that currently have something we can redo. */
function version_redoable(string $id): array
{
    $out = [];
    foreach (versions_read($id)['redo'] as $rel => $list) {
        if (is_array($list) && $list) $out[] = (string)$rel;
    }
    sort($out);
    return $out;
}

/** Newest snapshot timestamp per file, so the UI can pick "most recent" without
 *  fetching every file's history (that turned into an N+1 request storm). */
function version_latest_map(string $id, string $dir = 'files'): array
{
    $key = ($dir === 'redo') ? 'redo' : 'files';
    $out = [];
    foreach (versions_read($id)[$key] as $rel => $list) {
        if (!is_array($list) || !$list) continue;
        $ts = 0;
        foreach ($list as $s) $ts = max($ts, (int)($s['ts'] ?? 0));
        $out[(string)$rel] = $ts;
    }
    return $out;
}

/** Newest-first snapshot summary for one file (no content, for the UI). */
function version_list(string $id, string $rel): array
{
    $d = versions_read($id);
    $summarise = function (array $list, string $dir): array {
        return array_values(array_map(fn($s) => [
            'ts'      => (int)($s['ts'] ?? 0),
            'label'   => (string)($s['label'] ?? 'change'),
            'comment' => (string)($s['comment'] ?? ''),
            'bytes'   => (int)($s['bytes'] ?? 0),
            'was'     => ($s['content'] ?? null) === null ? 'missing' : 'file',
            'dir'     => $dir,
        ], array_reverse($list)));
    };
    return [
        'undo' => $summarise(is_array($d['files'][$rel] ?? null) ? $d['files'][$rel] : [], 'undo'),
        'redo' => $summarise(is_array($d['redo'][$rel] ?? null) ? $d['redo'][$rel] : [], 'redo'),
    ];
}

/** Attach or edit the human comment on a stored snapshot. */
function version_comment(string $id, string $rel, int $index, string $comment): array
{
    $d = versions_read($id);
    $comment = trim($comment);
    foreach (['files', 'redo'] as $dir) {
        $list = $d[$dir][$rel] ?? null;
        if (!is_array($list) || !isset($list[$index])) continue;
        $list[$index]['comment'] = $comment;
        $d[$dir][$rel] = $list;
        versions_write($id, $d['files'], $d['redo']);
        return ['path' => $rel, 'dir' => $dir, 'index' => $index, 'comment' => $comment];
    }
    throw new RuntimeException('No such version for ' . $rel);
}

/** Write a snapshot's content to disk. null => the file did not exist. */
function version_apply(string $id, string $rel, ?string $content): array
{
    $target = safe_file_path($id, $rel);
    if ($content === null) {
        if (is_file($target)) unlink($target);
        return ['path' => $rel, 'result' => 'deleted', 'bytes' => 0];
    }
    if (file_put_contents($target, $content) === false) {
        throw new RuntimeException('Could not restore ' . $rel);
    }
    return ['path' => $rel, 'result' => 'restored', 'bytes' => strlen($content)];
}

/**
 * Move one step back (undo) or forward (redo) for a file.
 * $dir is 'undo' or 'redo'; whatever is on disk is pushed onto the other list,
 * so the two directions always undo each other.
 */
function version_step(string $id, string $rel, string $dir = 'undo'): array
{
    $from = ($dir === 'redo') ? 'redo' : 'files';
    $to   = ($dir === 'redo') ? 'files' : 'redo';

    $d = versions_read($id);
    $list = $d[$from][$rel] ?? [];
    if (!is_array($list) || !$list) {
        throw new RuntimeException(($dir === 'redo' ? 'Nothing to redo' : 'No earlier version') . ' of ' . $rel);
    }
    $snap = array_pop($list);      // the state we are moving to
    $d[$from][$rel] = $list;
    if (!$d[$from][$rel]) unset($d[$from][$rel]);

    // whatever is on disk now becomes the entry on the opposite side
    $target = safe_file_path($id, $rel);
    $current = is_file($target) ? (string)file_get_contents($target) : null;
    $other = $d[$to][$rel] ?? [];
    $other[] = version_snap($current, $dir);
    $d[$to][$rel] = $other;

    $result = version_apply($id, $rel, $snap['content'] ?? null);
    versions_write($id, $d['files'], $d['redo']);
    return $result + ['direction' => $dir, 'restored_at' => (int)($snap['ts'] ?? 0)];
}

/**
 * Restore one specific version from the history list, whatever the current
 * position is. versions[$rel][$index] (index 0 = newest) becomes the live file.
 * The current depth is the number of entries on the redo stack, so we step in
 * whichever direction is needed — a naive "undo index+1 times" is wrong once the
 * user has already undone or redone something.
 */
function version_restore(string $id, string $rel, int $index): array
{
    $d = versions_read($id);
    $undo = is_array($d['files'][$rel] ?? null) ? $d['files'][$rel] : [];
    $n = count($undo);
    if ($index < 0 || $index >= $n) throw new RuntimeException('No such version of ' . $rel);

    $depth = count(is_array($d['redo'][$rel] ?? null) ? $d['redo'][$rel] : []); // undos already applied
    $target = $index + 1;   // snapshots[rel][index] is live at this depth
    $steps = $target - $depth;
    if ($steps === 0) {
        return ['path' => $rel, 'result' => 'already-current', 'bytes' => 0, 'steps' => 0];
    }
    $dir = $steps > 0 ? 'undo' : 'redo';
    $r = ['path' => $rel, 'result' => 'unchanged', 'bytes' => 0];
    for ($i = 0; $i < abs($steps); $i++) $r = version_step($id, $rel, $dir);
    return $r + ['steps' => abs($steps), 'direction' => $dir];
}

/** Backwards-compatible entry point: one step back. */
function version_revert(string $id, string $rel): array
{
    return version_step($id, $rel, 'undo');
}