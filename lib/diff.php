<?php
declare(strict_types=1);

/**
 * Line-based diffs for the approval flow.
 * Returns rows: ['t'=>' '|'+'|'-', 'a'=>oldLineNo|null, 'b'=>newLineNo|null, 's'=>text]
 * Common prefix/suffix are trimmed first; the LCS middle is capped so huge
 * rewrites degrade to a naive replace instead of blowing memory.
 */
function diff_lines(string $old, string $new): array
{
    $a = $old === '' ? [] : explode("\n", $old);
    $b = $new === '' ? [] : explode("\n", $new);

    $pre = 0;
    $maxPre = min(count($a), count($b));
    while ($pre < $maxPre && $a[$pre] === $b[$pre]) $pre++;

    $suf = 0;
    $maxSuf = min(count($a) - $pre, count($b) - $pre);
    while ($suf < $maxSuf && $a[count($a) - 1 - $suf] === $b[count($b) - 1 - $suf]) $suf++;

    $midA = array_slice($a, $pre, count($a) - $pre - $suf);
    $midB = array_slice($b, $pre, count($b) - $pre - $suf);

    $rows = [];
    for ($i = 0; $i < $pre; $i++) $rows[] = ['t' => ' ', 'a' => $i + 1, 'b' => $i + 1, 's' => $a[$i]];

    $n = count($midA);
    $m = count($midB);
    if ($n * $m > 1000000) {
        foreach ($midA as $line) $rows[] = ['t' => '-', 'a' => $pre + 1, 'b' => null, 's' => $line];
        foreach ($midB as $line) $rows[] = ['t' => '+', 'a' => null, 'b' => $pre + 1, 's' => $line];
    } elseif ($n === 0 || $m === 0) {
        foreach ($midA as $line) $rows[] = ['t' => '-', 'a' => $pre + 1, 'b' => null, 's' => $line];
        foreach ($midB as $line) $rows[] = ['t' => '+', 'a' => null, 'b' => $pre + 1, 's' => $line];
    } else {
        // classic LCS table (bounded by the prefix/suffix trim above)
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $midA[$i] === $midB[$j]
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }
        $i = 0; $j = 0;
        $ai = $pre + 1; $bi = $pre + 1;
        while ($i < $n && $j < $m) {
            if ($midA[$i] === $midB[$j]) {
                $rows[] = ['t' => ' ', 'a' => $ai++, 'b' => $bi++, 's' => $midA[$i]];
                $i++; $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $rows[] = ['t' => '-', 'a' => $ai++, 'b' => null, 's' => $midA[$i++]];
            } else {
                $rows[] = ['t' => '+', 'a' => null, 'b' => $bi++, 's' => $midB[$j++]];
            }
        }
        while ($i < $n) $rows[] = ['t' => '-', 'a' => $ai++, 'b' => null, 's' => $midA[$i++]];
        while ($j < $m) $rows[] = ['t' => '+', 'a' => null, 'b' => $bi++, 's' => $midB[$j++]];
    }

    for ($k = 0; $k < $suf; $k++) {
        $rows[] = ['t' => ' ', 'a' => count($a) - $suf + $k + 1, 'b' => count($b) - $suf + $k + 1, 's' => $a[count($a) - $suf + $k]];
    }
    return $rows;
}

/**
 * Build change records (with diffs) for files an assistant reply wants to write.
 * Returns list of ['path','status','added','removed','diff']
 */
function changes_build(string $id, array $files): array
{
    $out = [];
    foreach ($files as $rel => $content) {
        try {
            $target = safe_file_path($id, $rel);
        } catch (Throwable $e) {
            $out[] = ['path' => $rel, 'status' => 'invalid', 'added' => 0, 'removed' => 0, 'diff' => []];
            continue;
        }
        $exists = is_file($target);
        $old = $exists ? (string)file_get_contents($target) : '';
        if ($exists && $old === $content) {
            $out[] = ['path' => $rel, 'status' => 'unchanged', 'added' => 0, 'removed' => 0, 'diff' => [], 'content' => $content];
            continue;
        }
        $diff = diff_lines($old, $content);
        $added = $removed = 0;
        foreach ($diff as $row) {
            if ($row['t'] === '+') $added++;
            elseif ($row['t'] === '-') $removed++;
        }
        $out[] = [
            'path' => $rel,
            'status' => $exists ? 'modified' : 'new',
            'added' => $added,
            'removed' => $removed,
            'diff' => $diff,
            'content' => $content,
        ];
    }
    return $out;
}

/* ---------------- pending changes (ask-before-apply) ---------------- */

function pending_file(string $id): string
{
    return project_dir($id) . '/pending.json';
}

function pending_save(string $id, array $changes, string $note = ''): void
{
    file_put_contents(pending_file($id), json_encode(
        ['created_at' => now(), 'note' => $note, 'changes' => $changes],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ));
}

/** The chat message that produced the pending changes, if recorded. */
function pending_note(string $id): string
{
    $f = pending_file($id);
    if (!is_file($f)) return '';
    $data = json_decode((string)file_get_contents($f), true);
    return is_array($data) ? trim((string)($data['note'] ?? '')) : '';
}

function pending_load(string $id): array
{
    $file = pending_file($id);
    if (!is_file($file)) return [];
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data['changes'] ?? null) ? $data['changes'] : [];
}

function pending_clear(string $id): void
{
    $file = pending_file($id);
    if (is_file($file)) unlink($file);
}

/** Remove specific paths (or everything) from the pending store. */
function pending_forget(string $id, ?array $paths = null): void
{
    if ($paths === null) { pending_clear($id); return; }
    $keep = array_values(array_filter(pending_load($id), fn($c) => !in_array($c['path'], $paths, true)));
    if ($keep) pending_save($id, $keep); else pending_clear($id);
}
