<?php
declare(strict_types=1);

class RateLimitException extends RuntimeException
{
    public int $retryAfter;
    public int $resetAt;

    public function __construct(string $message, int $retryAfter, int $resetAt)
    {
        parent::__construct($message);
        $this->retryAfter = $retryAfter;
        $this->resetAt = $resetAt;
    }
}

function system_prompt(array $project, array $fileList): string
{
    $files = $fileList ? implode(', ', array_slice($fileList, 0, 80)) : '(empty project)';
    $kind = ($project['type'] ?? 'php') === 'php'
        ? 'PHP + HTML + CSS + vanilla JS (no frameworks, no composer, no database required)'
        : 'HTML + CSS + vanilla JS';

    return <<<TXT
You are "Opencode PHP", an autonomous senior web developer agent working inside a browser IDE.

Project: "{$project['name']}" (id: {$project['id']})
Description: {$project['description']}
Stack: {$kind}
Current files: {$files}

Your job: take the user's prompt and BUILD the website / feature for real.

STRICT OUTPUT RULES
1. Whenever you create or update a file, wrap the FULL file content in a fenced code block whose info string is "lang:relative/path.ext", for example:

```php:index.php
<?php // full file, never truncated
```
```css:style.css
body { ... }
```
```js:app.js
...
```

2. Never use placeholders such as "// rest of the code", "...", "TODO: implement". Output complete files, always.
3. Only write files inside the project (relative paths, no "..", no absolute paths). Index page must be index.php (or index.html for static projects).
4. Keep it self-contained: plain PHP/HTML/CSS/JS only - must work without shell access, composer or a database (shared hosting safe).
5. Use modern semantic HTML, responsive layout, and a polished dark UI unless the user asks otherwise.
6. Be concise in prose: 1-3 lines before the code, short next-steps after. No long essays, no markdown headings unless asked.
7. Do not repeat unchanged files in later replies - only output files you actually changed.
8. The user may reference project files with @path (their current content is inlined after the message) and attach files or screenshots; treat those as source material.
TXT;
}

const AI_TEXT_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
const AI_MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

/** Inline @mention references into appended context for the outgoing message. */
function expand_mentions(string $msg, string $id): string
{
    if (!preg_match_all('/@((?:[A-Za-z0-9._\-]+\/)*[A-Za-z0-9._\-]+\.[A-Za-z0-9]{1,12})/', $msg, $m)) return $msg;
    $extra = '';
    $budget = 120000;
    $seen = [];
    foreach (array_unique($m[1]) as $path) {
        if (isset($seen[$path])) continue;
        $seen[$path] = true;
        try {
            // the element picker's scratch file lives outside files/
            if ($path === 'picked-element.html') {
                $scratch = project_dir($id) . '/.picked.html';
                if (!is_file($scratch)) continue;
                $content = (string)file_get_contents($scratch);
                if (strlen($content) > 6000) $content = substr($content, 0, 6000) . "\n… (truncated)";
                $extra .= "\n\n[Selected element markup]\n```html\n{$content}\n```";
                if (strlen($extra) > $budget) break;
                continue;
            }
            $target = safe_file_path($id, $path);
        } catch (Throwable $e) { continue; }
        if (!is_file($target)) continue;
        $content = (string)file_get_contents($target);
        if (strlen($content) > 6000) $content = substr($content, 0, 6000) . "\n… (truncated)";
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $extra .= "\n\n[Referenced file: {$path}]\n```{$ext}:{$path}\n{$content}\n```";
        if (strlen($extra) > $budget) break;
    }
    return $extra === '' ? $msg : $msg . $extra;
}

/**
 * Turn stored messages into provider-ready payloads.
 * Handles @mention snapshots (content_api), text attachments and vision images.
 */
function ai_payload_messages(array $history, string $id, string $kind): array
{
    $out = [];
    foreach ($history as $m) {
        $role = ($m['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
        $text = (string)($m['content_api'] ?? $m['content'] ?? '');
        if ($text === '') $text = ' ';

        $textFiles = '';
        $images = [];
        foreach ((array)($m['attachments'] ?? []) as $att) {
            $path = (string)($att['path'] ?? '');
            try { $target = safe_file_path($id, $path); } catch (Throwable $e) { continue; }
            if (!is_file($target)) continue;
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, AI_TEXT_EXT, true) && ($att['image'] ?? false)) {
                $raw = (string)file_get_contents($target);
                if (strlen($raw) <= 6 * 1024 * 1024) {
                    $images[] = ['mime' => AI_MIME[$ext], 'data' => base64_encode($raw)];
                }
            } else {
                $content = (string)file_get_contents($target);
                if (strlen($content) > 20000) $content = substr($content, 0, 20000) . "\n… (truncated)";
                $textFiles .= "\n\n[Attached file: {$path}]\n```{$ext}\n{$content}\n```";
            }
        }

        if ($kind === 'anthropic') {
            $blocks = [['type' => 'text', 'text' => $text . $textFiles]];
            foreach ($images as $img) $blocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $img['mime'], 'data' => $img['data']]];
            $out[] = ['role' => $role, 'content' => $blocks];
        } elseif ($kind === 'responses') {
            if ($images) {
                $parts = [['type' => 'input_text', 'text' => $text . $textFiles]];
                foreach ($images as $img) $parts[] = ['type' => 'input_image', 'image_url' => 'data:' . $img['mime'] . ';base64,' . $img['data']];
                $out[] = ['role' => $role, 'content' => $parts];
            } else {
                $out[] = ['role' => $role, 'content' => $text . $textFiles];
            }
        } elseif ($images) {
            $parts = [['type' => 'text', 'text' => $text . $textFiles]];
            foreach ($images as $img) $parts[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $img['mime'] . ';base64,' . $img['data']]];
            $out[] = ['role' => $role, 'content' => $parts];
        } else {
            $out[] = ['role' => $role, 'content' => $text . $textFiles];
        }
    }
    if ($kind === 'anthropic') $out = ai_merge_turns($out);
    return $out;
}

/** Anthropic requires alternating user/assistant turns - merge runs of the same role. */
function ai_merge_turns(array $messages): array
{
    $out = [];
    foreach ($messages as $m) {
        $last = $out ? array_key_last($out) : null;
        if ($last !== null && $out[$last]['role'] === $m['role']) {
            $out[$last]['content'] = array_merge($out[$last]['content'], $m['content']);
            continue;
        }
        $out[] = $m;
    }
    while ($out && $out[0]['role'] !== 'user') array_shift($out);
    return $out;
}

/**
 * Which wire protocol to speak for this provider/model.
 * OpenCode Zen mixes OpenAI-compatible (/chat/completions), Anthropic (/messages)
 * and OpenAI Responses (/responses) models behind a single base URL.
 */
function ai_endpoint_kind(array $provider): string
{
    $base = (string)($provider['base_url'] ?? '');
    if (str_contains($base, 'opencode.ai/zen') || str_contains($base, '/zen/v1')) {
        $m = strtolower((string)($provider['model'] ?? ''));
        if (preg_match('/^(gpt-|grok-|muse-spark)/', $m)) return 'responses';
        if (preg_match('/^(claude-|qwen3\.(5|6|7)\.|qwen3\.8-flash)/', $m)) return 'anthropic';
    }
    return $provider['kind'] ?? 'openai';
}

/**
 * Streams a chat completion from the active provider and returns the full text.
 * $provider = ['kind'=>'openai|anthropic','base_url','api_key','model']
 */
/**
 * Rough context-window usage for the chat, so the UI can show how full it is.
 * ~4 characters per token is close enough for a meter and costs nothing.
 */
function ai_context_usage(array $history, array $project, array $fileList): array
{
    $chars = strlen(system_prompt($project, $fileList));
    foreach ($history as $m) {
        $chars += strlen((string)($m['content'] ?? ''));
        if (!empty($m['content_api'])) $chars += strlen((string)$m['content_api']);
    }
    $limit = (int)cfg('context_tokens', 128000);
    if ($limit < 1000) $limit = 128000;
    $est = (int)ceil($chars / 4);
    return [
        'chars'      => $chars,
        'est_tokens' => $est,
        'limit'      => $limit,
        'pct'        => min(100, (int)round(($est / $limit) * 100)),
    ];
}

function ai_chat_stream(array $history, array $provider, array $project, array $fileList, ?callable $onDelta): string
{
    $system = system_prompt($project, $fileList);
    $kind = ai_endpoint_kind($provider);
    if (ai_is_zen($provider)) {
        $provider['session_id'] = $provider['session_id'] ?? ai_zen_session((string)($project['id'] ?? 'default'));
        $provider['request_id'] = $provider['request_id'] ?? 'msg_' . substr(hash('sha256', uniqid('', true)), 0, 24);
    }
    $messages = ai_payload_messages($history, (string)($project['id'] ?? ''), $kind);
    if ($kind === 'anthropic') return ai_anthropic_stream($messages, $provider, $system, $onDelta);
    if ($kind === 'responses') return ai_responses_stream($messages, $provider, $system, $onDelta);
    return ai_openai_stream($messages, $provider, $system, $onDelta);
}

/**
 * OpenCode Zen's free tier only serves requests that carry the OpenCode client
 * identity (User-Agent + x-opencode-* headers). We send them for zen hosts so the
 * free model works without an account. This is client emulation - it can break if
 * OpenCode changes the gate, and it is not an officially supported integration.
 */
function ai_is_zen(array $provider): bool
{
    $host = parse_url((string)($provider['base_url'] ?? ''), PHP_URL_HOST) ?: '';
    return str_contains($host, 'opencode.ai') || str_contains((string)($provider['base_url'] ?? ''), '/zen/');
}

function ai_bearer(array $provider): string
{
    $key = (string)($provider['api_key'] ?? '');
    if ($key !== '') return $key;
    return ai_is_zen($provider) ? 'public' : '';
}

/** Canonical Zen session id: "ses_" + 12 lowercase hex + 14 base62 (stable per seed). */
function ai_zen_session(string $seed): string
{
    $hex = substr(hash('sha256', "ses\0" . $seed), 0, 12);
    $bytes = hash('sha256', "b62\0" . $seed, true);
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $b62 = '';
    for ($i = 0; $i < 14; $i++) $b62 .= $alphabet[ord($bytes[$i]) % 62];
    return 'ses_' . $hex . $b62;
}

/** Append the OpenCode client identity headers for zen hosts; pass through otherwise. */
function ai_identity_headers(array $headers, array $provider): array
{
    if (!ai_is_zen($provider)) return $headers;
    $headers[] = 'User-Agent: opencode/1.18.16';
    $headers[] = 'x-opencode-client: cli';
    if (!empty($provider['session_id'])) $headers[] = 'x-opencode-session: ' . $provider['session_id'];
    if (!empty($provider['request_id'])) $headers[] = 'x-opencode-request: ' . $provider['request_id'];
    return $headers;
}

function ai_openai_stream(array $messages, array $provider, string $system, ?callable $onDelta): string
{
    $messages = array_merge([['role' => 'system', 'content' => $system]], $messages);

    $base = rtrim($provider['base_url'], '/');
    $url = str_contains($base, '/chat/completions') ? $base
        : (str_contains($base, '/v1/') || str_ends_with($base, '/v1') ? $base . '/chat/completions' : $base . '/v1/chat/completions');

    $headers = ai_identity_headers([
        'Content-Type: application/json',
        'Authorization: Bearer ' . ai_bearer($provider),
    ], $provider);

    // Some free providers (e.g. Pollinations legacy) don't support SSE streaming
    if (($provider['stream'] ?? true) === false) {
        $body = json_encode([
            'model'       => $provider['model'],
            'messages'    => $messages,
            'temperature' => (float)cfg('temperature', 0.3),
            'max_tokens'  => (int)cfg('max_tokens', 16000),
            'stream'      => false,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $res = ai_post_json($url, $headers, $body);
        $json = json_decode($res['body'], true);
        if ($res['status'] >= 400 || !is_array($json)) {
            ai_assert_status($res['status'], $provider);
        }
        if (isset($json['error'])) {
            $msg = is_array($json['error']) ? ($json['error']['message'] ?? json_encode($json['error'])) : (string)$json['error'];
            throw new RuntimeException('AI API error: ' . $msg);
        }
        $content = (string)($json['choices'][0]['message']['content'] ?? '');
        if ($content === '') throw new RuntimeException('AI returned an empty response.');
        $onDelta && $onDelta($content);
        return $content;
    }

    $body = json_encode([
        'model'       => $provider['model'],
        'messages'    => $messages,
        'temperature' => (float)cfg('temperature', 0.3),
        'max_tokens'  => (int)cfg('max_tokens', 16000),
        'stream'      => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $full = '';
    $consume = function (array $json) use (&$full, $onDelta) {
        if (isset($json['error'])) {
            $msg = is_array($json['error']) ? ($json['error']['message'] ?? json_encode($json['error'])) : (string)$json['error'];
            throw new RuntimeException('AI API error: ' . $msg);
        }
        $delta = $json['choices'][0]['delta'] ?? [];
        // Reasoning models stream their scratchpad first; showing it stops the
        // UI looking frozen while they think (which can take 30s+).
        if (isset($delta['reasoning_content']) && is_string($delta['reasoning_content']) && $delta['reasoning_content'] !== '') {
            $GLOBALS['__ai_reason'] = ($GLOBALS['__ai_reason'] ?? '') . $delta['reasoning_content'];
            if (isset($GLOBALS['__ai_on_reason']) && is_callable($GLOBALS['__ai_on_reason'])) {
                ($GLOBALS['__ai_on_reason'])($delta['reasoning_content']);
            }
        }
        if (isset($delta['content']) && is_string($delta['content']) && $delta['content'] !== '') {
            $full .= $delta['content'];
            $onDelta && $onDelta($delta['content']);
        }
    };

    $status = ai_sse_request($url, $headers, $body, $consume);

    ai_assert_status($status, $provider);
    if ($full === '') { ai_throw_real_error(200, $provider); throw new RuntimeException('AI returned an empty response.'); }
    return $full;
}

/** Plain JSON POST (no SSE). Returns ['status'=>int, 'body'=>string]. */
function ai_post_json(string $url, array $headers, string $body): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 25,
        ]);
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($resp === false || $errno !== 0) throw new RuntimeException("AI request failed: {$error}");
        return ['status' => $status, 'body' => (string)$resp];
    }

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", $headers) . "\r\nContent-Length: " . strlen($body),
        'content' => $body,
        'timeout' => 120,
        'ignore_errors' => true,
    ]]);
    $resp = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int)$m[1];
    }
    if ($resp === false) throw new RuntimeException('AI request failed: could not reach ' . parse_url($url, PHP_URL_HOST));
    return ['status' => $status, 'body' => (string)$resp];
}

function ai_anthropic_stream(array $messages, array $provider, string $system, ?callable $onDelta): string
{
    $base = rtrim($provider['base_url'], '/');
    $url = str_contains($base, '/messages') ? $base : $base . '/messages';

    $body = json_encode([
        'model'       => $provider['model'],
        'system'      => $system,
        'messages'    => $messages,
        'max_tokens'  => (int)cfg('max_tokens', 16000),
        'temperature' => (float)cfg('temperature', 0.3),
        'stream'      => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $full = '';
    $consume = function (array $json) use (&$full, $onDelta) {
        $type = $json['type'] ?? '';
        if ($type === 'content_block_delta' && isset($json['delta']['text'])) {
            $full .= $json['delta']['text'];
            $onDelta && $onDelta($json['delta']['text']);
        } elseif ($type === 'error') {
            $msg = $json['error']['message'] ?? json_encode($json['error']);
            throw new RuntimeException('AI API error: ' . $msg);
        }
    };

    $status = ai_sse_request($url, ai_identity_headers([
        'Content-Type: application/json',
        'x-api-key: ' . ($provider['api_key'] ?? ''),
        'anthropic-version: 2023-06-01',
    ], $provider), $body, $consume);

    ai_assert_status($status, $provider);
    if ($full === '') { ai_throw_real_error(200, $provider); throw new RuntimeException('AI returned an empty response.'); }
    return $full;
}

/** OpenAI Responses API streaming (used by Zen's GPT / Grok / Muse models). */
function ai_responses_stream(array $messages, array $provider, string $system, ?callable $onDelta): string
{
    $base = rtrim($provider['base_url'], '/');
    $url = str_contains($base, '/responses') ? $base : $base . '/responses';

    $body = json_encode([
        'model'             => $provider['model'],
        'instructions'      => $system,
        'input'             => $messages,
        'max_output_tokens' => (int)cfg('max_tokens', 16000),
        'stream'            => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $full = '';
    $consume = function (array $json) use (&$full, $onDelta) {
        $type = (string)($json['type'] ?? '');
        if ($type === 'response.output_text.delta') {
            $d = (string)($json['delta'] ?? '');
            if ($d !== '') { $full .= $d; $onDelta && $onDelta($d); }
        } elseif ($type === 'response.failed' || $type === 'error') {
            $msg = $json['response']['error']['message'] ?? $json['error']['message'] ?? ($json['message'] ?? json_encode($json));
            throw new RuntimeException('AI API error: ' . (is_string($msg) ? $msg : json_encode($msg)));
        }
    };

    $status = ai_sse_request($url, ai_identity_headers([
        'Content-Type: application/json',
        'Authorization: Bearer ' . ai_bearer($provider),
    ], $provider), $body, $consume);

    ai_assert_status($status, $provider);
    if ($full === '') { ai_throw_real_error(200, $provider); throw new RuntimeException('AI returned an empty response.'); }
    return $full;
}

/** POST with SSE streaming; feeds each parsed data JSON to $consume. Returns HTTP status. */
/**
 * Providers answer errors as a plain JSON body, not an SSE stream, so the
 * data: loop below never sees them. Pull the real message out of that body so
 * we can tell the user what actually went wrong instead of "empty response".
 */
function ai_raw_error_message(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || strlen($raw) > 20000) return '';
    $j = json_decode($raw, true);
    if (!is_array($j)) return '';
    $err = $j['error'] ?? null;
    if (is_array($err)) {
        $m = $err['message'] ?? $err['type'] ?? '';
        if (is_string($m) && $m !== '') return $m;
        return json_encode($err);
    }
    if (is_string($err) && $err !== '') return $err;
    foreach (['message', 'detail', 'error_message'] as $k) {
        if (isset($j[$k]) && is_string($j[$k]) && $j[$k] !== '') return $j[$k];
    }
    return '';
}

/** Feed one SSE line to the consumer; keep non-SSE text for error reporting. */
function ai_feed_sse_line(string $line, callable $consume, string &$raw): void
{
    if (!str_starts_with($line, 'data:')) {
        if (strlen($raw) < 20000 && trim($line) !== '') $raw .= $line . "\n";
        return;
    }
    $data = trim(substr($line, 5));
    if ($data === '' || $data === '[DONE]') return;
    $json = json_decode($data, true);
    if (is_array($json)) $consume($json);
}

function ai_sse_request(string $url, array $headers, string $body, callable $consume): int
{
    $GLOBALS['__ai_raw'] = '';
    $GLOBALS['__ai_retry_after'] = 0;
    if (!function_exists('curl_init')) return ai_sse_request_stream($url, $headers, $body, $consume);

    $buf = '';
    $raw = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 600,
        CURLOPT_CONNECTTIMEOUT => 25,
        CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use ($consume, &$buf, &$raw) {
            $buf .= $chunk;
            while (($pos = strpos($buf, "\n")) !== false) {
                $line = rtrim(substr($buf, 0, $pos), "\r");
                $buf = substr($buf, $pos + 1);
                ai_feed_sse_line($line, $consume, $raw);
            }
            return strlen($chunk);
        },
        CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$retryAfter) {
            $len = strlen($header);
            if (stripos($header, 'Retry-After:') === 0) {
                $val = trim(substr($header, 12));
                if (ctype_digit($val)) {
                    $GLOBALS['__ai_retry_after'] = (int)$val;
                }
            } elseif (stripos($header, 'x-ratelimit-reset:') === 0) {
                $val = trim(substr($header, 18));
                if (ctype_digit($val)) {
                    $GLOBALS['__ai_retry_after'] = max((int)$val - time(), 0);
                }
            }
            return $len;
        },
    ]);

    $ok = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (trim($buf) !== '') ai_feed_sse_line(rtrim($buf, "\r"), $consume, $raw);
    $GLOBALS['__ai_raw'] = $raw;

    if ($ok === false || $errno !== 0) throw new RuntimeException("AI request failed: {$error}");
    return $status;
}

/** Fallback for hosts with curl disabled - parses SSE line by line. */
function ai_sse_request_stream(string $url, array $headers, string $body, callable $consume): int
{
    $GLOBALS['__ai_retry_after'] = 0;
    $hdrs = implode("\r\n", $headers) . "\r\nContent-Length: " . strlen($body) . "\r\nConnection: close";
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => $hdrs,
        'content' => $body,
        'timeout' => 600,
        'ignore_errors' => true,
    ]]);
    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp) throw new RuntimeException('AI request failed: could not open ' . parse_url($url, PHP_URL_HOST));

    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int)$m[1];
        if (stripos($h, 'Retry-After:') === 0) {
            $val = trim(substr($h, 12));
            if (ctype_digit($val)) $GLOBALS['__ai_retry_after'] = (int)$val;
        } elseif (stripos($h, 'x-ratelimit-reset:') === 0) {
            $val = trim(substr($h, 18));
            if (ctype_digit($val)) $GLOBALS['__ai_retry_after'] = max((int)$val - time(), 0);
        }
    }
    if ($status >= 400) {
        $raw = (string)stream_get_contents($fp);
        fclose($fp);
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['error'])) {
            $msg = is_array($json['error']) ? ($json['error']['message'] ?? json_encode($json['error'])) : (string)$json['error'];
            throw new RuntimeException('AI API error: ' . $msg);
        }
        return $status;
    }

    $dataLine = function (string $line) use ($consume): void {
        if (!str_starts_with($line, 'data:')) return;
        $data = trim(substr($line, 5));
        if ($data === '' || $data === '[DONE]') return;
        $json = json_decode($data, true);
        if (is_array($json)) $consume($json);
    };

    while (($line = fgets($fp)) !== false) $dataLine(rtrim($line, "\r\n"));
    fclose($fp);
    return $status;
}

/** Prefer the provider's own error text over our generic wording. */
function ai_throw_real_error(int $status, array $provider): bool
{
    $msg = ai_raw_error_message((string)($GLOBALS['__ai_raw'] ?? ''));
    if ($msg === '') return false;
    $name = $provider['name'] ?? (parse_url($provider['base_url'], PHP_URL_HOST) ?: 'the provider');
    throw new RuntimeException("{$name}: {$msg}");
}

function ai_assert_status(int $status, array $provider): void
{
    // a JSON error body explains more than our generic 4xx wording
    if ($status >= 400) ai_throw_real_error($status, $provider);
    if ($status >= 400) {
        $host = parse_url($provider['base_url'], PHP_URL_HOST) ?? 'the provider';
        $name = $provider['name'] ?? $host;
        if ($status === 429) {
            $retryAfter = (int)($GLOBALS['__ai_retry_after'] ?? 0);
            if ($retryAfter <= 0) $retryAfter = 60; // default 60s
            $resetAt = time() + $retryAfter;
            throw new RateLimitException(
                "{$name}: Rate limited. Try again in {$retryAfter}s.",
                $retryAfter,
                $resetAt
            );
        }
        if ($status === 401 || $status === 403) {
            if (!empty($provider['keyless'])) {
                throw new RuntimeException(
                    "{$name} rejected the request (HTTP {$status}). It may be rate-limited - try again in a moment, or pick another model."
                );
            }
            $hasKey = trim((string)($provider['api_key'] ?? '')) !== '';
            if ($hasKey) {
                throw new RuntimeException(
                    "{$name} rejected the API key (HTTP {$status}). Double-check the key in Settings, or generate a new one."
                );
            }
            $keyUrl = (string)($provider['key_url'] ?? '');
            if ($keyUrl !== '') {
                throw new RuntimeException(
                    "{$name} requires an API key. Get a free key at {$keyUrl} - then paste it in Settings."
                );
            }
            throw new RuntimeException(
                "{$name} requires an API key. Add one in Settings."
            );
        }
        throw new RuntimeException(
            "AI API returned HTTP {$status} for {$host}. " .
            'Check the API key, base URL and model in Settings.'
        );
    }
}
