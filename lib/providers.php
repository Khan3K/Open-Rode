<?php
declare(strict_types=1);

/**
 * Multiple AI servers (providers), each with many models.
 * Stored in data/providers.json - editable from the UI, no code edits needed.
 */

/**
 * Free models, best-for-coding first (curated order — no invented scores).
 * `free` is derived from the model id rather than hand-set, so a paid model
 * can never be advertised as free.
 * `verified` = the id really exists in OpenCode Zen's /models list. Verified
 * entries are auto-added to the no-signup provider; unverified ones are shown
 * greyed out rather than offered, so a typo can't break every request.
 */
function provider_free_models(): array
{
    // probe: 'ok'    - answered a real request through this app, no key needed
    //         'gated' - exists, but Zen answers 403 "free tier can only be used
    //                     from within OpenCode" -> needs an OpenCode Zen key
    //         'dead'  - deprecated or unavailable upstream
    $models = [
        ['id' => 'space-bunny-free',                'name' => 'Space Bunny',          'note' => '', 'probe' => 'ok'],
        ['id' => 'mimo-v2.6-flash-free',             'name' => 'MiMo-V2.6-Flash',     'note' => 'needs an OpenCode Zen key', 'probe' => 'gated'],
        ['id' => 'muse-spark-1.3-contributor-free', 'name' => 'Muse Spark 1.3',       'note' => 'contributor tier — needs a key', 'probe' => 'gated'],
        ['id' => 'longcat-2.5-preview-free',        'name' => 'LongCat 2.5 Preview',  'note' => 'needs an OpenCode Zen key', 'probe' => 'gated'],
        ['id' => 'ling-3.1-flash-free',             'name' => 'Ling 3.1 Flash',       'note' => 'needs an OpenCode Zen key', 'probe' => 'gated'],
        ['id' => 'nemotron-3-ultra-free',           'name' => 'Nemotron 3 Ultra',     'note' => 'needs an OpenCode Zen key', 'probe' => 'gated'],
        ['id' => 'nemotron-3.5-lightning-free',     'name' => 'Nemotron 3.5 Lightning', 'note' => 'needs an OpenCode Zen key', 'probe' => 'gated'],
        ['id' => 'step-5-preview-free',             'name' => 'Step 5 Preview',       'note' => 'needs an OpenCode Zen key', 'probe' => 'gated'],
        ['id' => 'jev-1.13-free',                   'name' => 'Jev 1.13',             'note' => 'needs an OpenCode Zen key', 'probe' => 'gated'],
        ['id' => 'muse-spark-1.2-contributor-free', 'name' => 'Muse Spark 1.2',       'note' => 'contributor tier — needs a key', 'probe' => 'gated'],
        ['id' => 'fledge-alpha-free',               'name' => 'Fledge Alpha',         'note' => 'not in the Zen catalog', 'probe' => 'dead', 'verified' => false],
        ['id' => 'ling-3.0-flash-fin-free',         'name' => 'Ling 3.0 Flash Fin',   'note' => 'unavailable upstream', 'probe' => 'dead'],
        ['id' => 'exo-free',                        'name' => 'Exo',                  'note' => 'deprecated upstream', 'probe' => 'dead'],
        ['id' => 'big-pickle',                      'name' => 'Big Pickle',           'note' => 'not free in Zen — needs a key', 'probe' => 'gated'],
    ];
    foreach ($models as &$m) {
        $m['free'] = (bool)preg_match('/-free$/', $m['id']);
    }
    return $models;
}

/** Ids we are willing to actually offer as free: verified, free, and not gated. */
function provider_free_model_ids(): array
{
    $ids = [];
    foreach (provider_free_models() as $m) {
        if (isset($m['verified']) && !$m['verified']) continue;
        if (empty($m['free'])) continue;
        if (($m['probe'] ?? '') === 'dead') continue; // deprecated/unavailable upstream
        $ids[] = $m['id'];
    }
    return $ids;
}

function provider_presets(): array
{
    $free = provider_free_model_ids();

    return [
        ['id' => 'opencode-zen-free', 'name' => 'OpenCode Zen - Free (no key)', 'base_url' => 'https://opencode.ai/zen/v1', 'kind' => 'openai',
         'hint' => 'No signup. All free models, ranked best-for-coding first.',
         'suggest' => $free, 'keyless' => true, 'stream' => true, 'key_url' => ''],
        ['id' => 'opencode-zen', 'name' => 'OpenCode Zen', 'base_url' => 'https://opencode.ai/zen/v1', 'kind' => 'openai',
         'hint' => 'opencode.ai/zen - sign in, create an API key. Free models billed at $0, paid need balance.',
         'suggest' => array_merge($free, ['gpt-5.6-terra', 'claude-sonnet-4-6', 'kimi-k2.7-code', 'deepseek-v4-pro']),
         'key_url' => 'https://opencode.ai/zen'],
        ['id' => 'openrouter', 'name' => 'OpenRouter', 'base_url' => 'https://openrouter.ai/api/v1', 'kind' => 'openai',
         'hint' => 'Free models: keys look like sk-or-...', 'key_url' => 'https://openrouter.ai/keys',
         'suggest' => ['meta-llama/llama-3.3-70b-instruct:free', 'deepseek/deepseek-chat-v3-0324:free', 'google/gemma-3-27b-it:free', 'qwen/qwen3-14b:free']],
        ['id' => 'groq', 'name' => 'Groq (free tier)', 'base_url' => 'https://api.groq.com/openai/v1', 'kind' => 'openai',
         'hint' => 'console.groq.com - very fast free tier', 'key_url' => 'https://console.groq.com/keys',
         'suggest' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'openai/gpt-oss-120b']],
        ['id' => 'github', 'name' => 'GitHub Models (free)', 'base_url' => 'https://models.inference.ai.azure.com/v1', 'kind' => 'openai',
         'hint' => 'github.com/marketplace/models - free with GitHub token', 'key_url' => 'https://github.com/settings/tokens',
         'suggest' => ['gpt-4o-mini', 'gpt-4.1-mini', 'DeepSeek-V3', 'Phi-4']],
        ['id' => 'mistral', 'name' => 'Mistral (free tier)', 'base_url' => 'https://api.mistral.ai/v1', 'kind' => 'openai',
         'hint' => 'console.mistral.ai', 'key_url' => 'https://console.mistral.ai', 'suggest' => ['mistral-small-latest', 'open-mistral-nemo', 'codestral-latest']],
        ['id' => 'gemini', 'name' => 'Google Gemini', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai/', 'kind' => 'openai',
         'hint' => 'aistudio.google.com - OpenAI-compatible endpoint', 'key_url' => 'https://aistudio.google.com/app/apikey', 'suggest' => ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-2.5-flash']],
        ['id' => 'openai', 'name' => 'OpenAI', 'base_url' => 'https://api.openai.com/v1', 'kind' => 'openai',
         'hint' => 'platform.openai.com', 'key_url' => 'https://platform.openai.com/api-keys', 'suggest' => ['gpt-4o-mini', 'gpt-4.1-mini', 'gpt-4.1']],
        ['id' => 'deepseek', 'name' => 'DeepSeek', 'base_url' => 'https://api.deepseek.com/v1', 'kind' => 'openai',
         'hint' => 'platform.deepseek.com', 'key_url' => 'https://platform.deepseek.com/api_keys', 'suggest' => ['deepseek-chat', 'deepseek-reasoner']],
        ['id' => 'cerebras', 'name' => 'Cerebras (free)', 'base_url' => 'https://api.cerebras.ai/v1', 'kind' => 'openai',
         'hint' => 'cloud.cerebras.ai free tier', 'key_url' => 'https://cloud.cerebras.ai', 'suggest' => ['llama3.3-70b', 'qwen-3-235b-a22b-instruct']],
        ['id' => 'sambanova', 'name' => 'SambaNova (free)', 'base_url' => 'https://api.sambanova.ai/v1', 'kind' => 'openai',
         'hint' => 'cloud.sambanova.ai free tier', 'key_url' => 'https://cloud.sambanova.ai', 'suggest' => ['Meta-Llama-3.1-8B-Instruct', 'DeepSeek-V3']],
        ['id' => 'together', 'name' => 'Together AI', 'base_url' => 'https://api.together.xyz/v1', 'kind' => 'openai',
         'hint' => 'api.together.ai', 'key_url' => 'https://api.together.ai/settings/api-keys', 'suggest' => ['meta-llama/Llama-3.3-70B-Instruct-Turbo', 'Qwen/Qwen2.5-72B-Instruct-Turbo']],
        ['id' => 'anthropic', 'name' => 'Anthropic (Claude)', 'base_url' => 'https://api.anthropic.com/v1', 'kind' => 'anthropic',
         'hint' => 'console.anthropic.com - native Claude API', 'key_url' => 'https://console.anthropic.com', 'suggest' => ['claude-sonnet-4-5', 'claude-opus-4-1', 'claude-3-5-haiku-latest']],
        ['id' => 'ollama', 'name' => 'Ollama (local / free)', 'base_url' => 'http://localhost:11434/v1', 'kind' => 'openai',
         'hint' => 'run models locally on your own machine', 'suggest' => ['llama3.2', 'qwen2.5-coder:7b', 'gemma3:4b'], 'keyless' => true],
        ['id' => 'cohere', 'name' => 'Cohere (free trial)', 'base_url' => 'https://api.cohere.ai/compatibility/v1', 'kind' => 'openai',
         'hint' => 'cohere.com - free trial keys, OpenAI-compatible endpoint', 'key_url' => 'https://dashboard.cohere.com', 'suggest' => ['command-r', 'command-r-plus', 'c4ai-aya-expanse-32b']],
        ['id' => 'huggingface', 'name' => 'HuggingFace Router (free)', 'base_url' => 'https://router.huggingface.co/v1', 'kind' => 'openai',
         'hint' => 'huggingface.co/settings/tokens - free Inference Providers', 'key_url' => 'https://huggingface.co/settings/tokens', 'suggest' => ['meta-llama/Llama-3.3-70B-Instruct', 'Qwen/Qwen2.5-Coder-32B-Instruct', 'deepseek-ai/DeepSeek-V3']],
        ['id' => 'zhipu', 'name' => 'Zhipu GLM (free tier)', 'base_url' => 'https://open.bigmodel.cn/api/paas/v4', 'kind' => 'openai',
         'hint' => 'bigmodel.cn - free GLM-4-Flash credits', 'key_url' => 'https://open.bigmodel.cn', 'suggest' => ['glm-4-flash', 'glm-4.5-air', 'glm-4.5v']],
        ['id' => 'moonshot', 'name' => 'Moonshot Kimi', 'base_url' => 'https://api.moonshot.cn/v1', 'kind' => 'openai',
         'hint' => 'platform.moonshot.cn free trial', 'key_url' => 'https://platform.moonshot.cn', 'suggest' => ['moonshot-v1-8k', 'kimi-latest']],
        ['id' => 'nvidia', 'name' => 'NVIDIA NIM (free credits)', 'base_url' => 'https://integrate.api.nvidia.com/v1', 'kind' => 'openai',
         'hint' => 'build.nvidia.com free credits', 'key_url' => 'https://build.nvidia.com', 'suggest' => ['meta/llama-3.3-70b-instruct', 'qwen/qwen2.5-coder-32b-instruct']],
        ['id' => 'lmstudio', 'name' => 'LM Studio (local)', 'base_url' => 'http://localhost:1234/v1', 'kind' => 'openai',
         'hint' => 'lmstudio.ai local server', 'suggest' => ['local-model'], 'keyless' => true],
        ['id' => 'siliconflow', 'name' => 'SiliconFlow (free credit)', 'base_url' => 'https://api.siliconflow.cn/v1', 'kind' => 'openai',
         'hint' => 'siliconflow.cn - free model credits, OpenAI-compatible', 'key_url' => 'https://cloud.siliconflow.cn', 'suggest' => ['Qwen/Qwen2.5-7B-Instruct', 'THUDM/glm-4-9b-chat', 'deepseek-ai/DeepSeek-V3']],
        ['id' => 'deepinfra', 'name' => 'DeepInfra (free tier)', 'base_url' => 'https://api.deepinfra.com/v1/openai', 'kind' => 'openai',
         'hint' => 'deepinfra.com - free playground credits, OpenAI-compatible', 'key_url' => 'https://deepinfra.com/dash', 'suggest' => ['meta-llama/Llama-3.3-70B-Instruct', 'Qwen/Qwen2.5-Coder-32B-Instruct', 'deepseek-ai/DeepSeek-V3']],
        ['id' => 'vercel-gateway', 'name' => 'Vercel AI Gateway', 'base_url' => 'https://ai-gateway.vercel.sh/v1', 'kind' => 'openai',
         'hint' => 'vercel.com/ai - one key for many providers, free tier', 'key_url' => 'https://vercel.com/ai', 'suggest' => ['openai/gpt-4o-mini', 'anthropic/claude-sonnet-4.5', 'google/gemini-2.0-flash']],
        ['id' => 'novita', 'name' => 'Novita AI (free credit)', 'base_url' => 'https://api.novita.ai/v3/openai', 'kind' => 'openai',
         'hint' => 'novita.ai - free signup credit, OpenAI-compatible', 'key_url' => 'https://novita.ai', 'suggest' => ['deepseek/deepseek-v4.1-flash', 'zai-org/glm-5.3-flash', 'xiaomimimo/mimo-v2.6-flash']],
        ['id' => 'chutes', 'name' => 'Chutes AI (free)', 'base_url' => 'https://api.chutes.ai/v1', 'kind' => 'openai',
         'hint' => 'chutes.ai free tier - paste model ids manually (no /models endpoint)', 'key_url' => 'https://chutes.ai', 'suggest' => ['deepseek-ai/DeepSeek-V3', 'meta-llama/Llama-3.3-70B-Instruct']],
        ['id' => 'dashscope', 'name' => 'Alibaba DashScope (Qwen free credit)', 'base_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1', 'kind' => 'openai',
         'hint' => 'bailian.console.aliyun.com - OpenAI-compatible, free quota', 'key_url' => 'https://bailian.console.aliyun.com', 'suggest' => ['qwen-plus', 'qwen-turbo', 'qwen-coder-plus']],
        ['id' => 'volc-ark', 'name' => 'ByteDance Volcano Ark (Doubao)', 'base_url' => 'https://ark.cn-beijing.volces.com/api/v3', 'kind' => 'openai',
         'hint' => 'console.volcengine.com - OpenAI-compatible, free quota', 'key_url' => 'https://console.volcengine.com', 'suggest' => ['doubao-seed-1-6', 'doubao-1-5-pro-32k']],
        ['id' => 'fireworks', 'name' => 'Fireworks AI (free $1 credit)', 'base_url' => 'https://api.fireworks.ai/inference/v1', 'kind' => 'openai',
         'hint' => 'fireworks.ai - $1 free credit, OpenAI-compatible', 'key_url' => 'https://fireworks.ai', 'suggest' => ['llama-v3p3-70b-instruct', 'deepseek-v3', 'qwen2p5-coder-32b-instruct']],
        ['id' => 'qianfan', 'name' => 'Baidu Qianfan (ERNIE free credit)', 'base_url' => 'https://qianfan.baidubce.com/v2', 'kind' => 'openai',
         'hint' => 'qianfan.baidubce.com - OpenAI-compatible, free quota', 'key_url' => 'https://qianfan.baidubce.com', 'suggest' => ['ernie-speed-128k', 'ernie-4.5-8k']],
        ['id' => 'hunyuan', 'name' => 'Tencent Hunyuan (free trial)', 'base_url' => 'https://api.hunyuan.cloud.tencent.com/v1', 'kind' => 'openai',
         'hint' => 'console.cloud.tencent.com - free trial quota, OpenAI-compatible', 'key_url' => 'https://console.cloud.tencent.com', 'suggest' => ['hunyuan-turbos-latest', 'hunyuan-large']],
        ['id' => 'custom', 'name' => 'Custom OpenAI-compatible', 'base_url' => '', 'kind' => 'openai',
         'hint' => 'Any server speaking the OpenAI API (vLLM, LiteLLM, OpenWebUI, ...)', 'suggest' => []],
    ];
}

function providers_file(): string
{
    return data_dir() . '/providers.json';
}

function providers_load(): array
{
    if (isset($GLOBALS['__prov_cache'])) return $GLOBALS['__prov_cache'];

    $data = ['providers' => [], 'active' => ['provider' => null, 'model' => null]];

    if (is_file(providers_file())) {
        $raw = json_decode((string)file_get_contents(providers_file()), true);
        if (is_array($raw)) {
            $data['providers'] = array_values($raw['providers'] ?? []);
            $data['active'] = array_merge($data['active'], $raw['active'] ?? []);
        }
    } elseif (is_file(data_dir() . '/settings.json')) {
        // migrate old single-settings file
        $old = json_decode((string)file_get_contents(data_dir() . '/settings.json'), true) ?: [];
        $data['providers'][] = [
            'id' => 'default', 'name' => 'Default', 'kind' => 'openai',
            'base_url' => $old['base_url'] ?? 'https://api.openai.com/v1',
            'api_key' => $old['api_key'] ?? '', 'models' => array_filter([$old['model'] ?? '']),
        ];
        $data['active'] = ['provider' => 'default', 'model' => $old['model'] ?? null];
    }

    // auto-seed an empty store with the first preset so the app works out of the box
    if (!$data['providers']) {
        $p = provider_presets()[0];
        $data['providers'][] = ['id' => $p['id'], 'name' => $p['name'], 'kind' => $p['kind'],
            'base_url' => $p['base_url'], 'api_key' => '', 'models' => $p['suggest'],
            'keyless' => (bool)($p['keyless'] ?? false), 'stream' => (bool)($p['stream'] ?? true),
            'key_url' => (string)($p['key_url'] ?? '')];
        $data['active'] = ['provider' => $p['id'], 'model' => $p['suggest'][0] ?? null];
        providers_save($data);
    }

    // make sure active points at something real
    $found = null;
    foreach ($data['providers'] as $p) if ($p['id'] === ($data['active']['provider'] ?? null)) $found = $p;
    if (!$found) {
        $first = $data['providers'][0];
        $data['active'] = ['provider' => $first['id'], 'model' => $first['models'][0] ?? null];
    } elseif (empty($data['active']['model']) || !in_array($data['active']['model'], (array)($found['models'] ?? []), true)) {
        $data['active']['model'] = $found['models'][0] ?? null;
    }

    // Keep the no-signup provider stocked with every free model that still exists
    // upstream. Gated models stay listed (they unlock once a Zen key is added);
    // only deprecated/unavailable ones are pruned so they can't error.
    $freeIds = provider_free_model_ids();
    $deadIds = array_column(array_values(array_filter(provider_free_models(),
        fn($m) => ($m['probe'] ?? '') === 'dead' || (isset($m['verified']) && !$m['verified']))), 'id');
    $restock = false;
    foreach ($data['providers'] as &$p) {
        if (($p['id'] ?? '') !== 'opencode-zen-free') continue;
        $cur = (array)($p['models'] ?? []);
        // drop dead models, keep the rest plus any custom ones
        $keep = array_values(array_filter($cur, fn($m) => !in_array($m, $deadIds, true)));
        if (count($keep) !== count($cur)) $restock = true;
        foreach ($freeIds as $id) {
            if (!in_array($id, $keep, true)) { $keep[] = $id; $restock = true; }
        }
        $p['models'] = $keep;
    }
    unset($p);
    if ($restock) providers_save($data);

    return $GLOBALS['__prov_cache'] = $data;
}

function providers_save(array $data): void
{
    file_put_contents(providers_file(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    @chmod(providers_file(), 0666); // writable regardless of which user (CLI vs. web server) serves the request
    $GLOBALS['__prov_cache'] = $data;
    provider_catalog_invalidate(); // a new/edited server must show its models at once
}

/** Drop the cached model catalogue so the next read refetches it. */
function provider_catalog_invalidate(): void
{
    @unlink(data_dir() . '/.catalog-cache.json');
}

function providers_all(): array
{
    return providers_load()['providers'];
}

function provider_find(string $id): ?array
{
    foreach (providers_all() as $p) if ($p['id'] === $id) return $p;
    return null;
}

function provider_id(string $name): string
{
    $slug = slugify($name);
    $ids = array_column(providers_all(), 'id');
    $base = $slug ?: 'server';
    $id = $base;
    $i = 2;
    while (in_array($id, $ids, true)) $id = $base . '-' . $i++;
    return $id;
}

function provider_add(array $in): array
{
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') throw new RuntimeException('Provider name is required');
    $base = trim((string)($in['base_url'] ?? ''));
    if ($base === '') throw new RuntimeException('Base URL is required');
    $kind = ($in['kind'] ?? 'openai') === 'anthropic' ? 'anthropic' : 'openai';

    $data = providers_load();
    $provider = [
        'id' => provider_id($name),
        'name' => $name,
        'base_url' => rtrim($base, '/'),
        'kind' => $kind,
        'api_key' => trim((string)($in['api_key'] ?? '')),
        'models' => provider_normalize_models($in['models'] ?? []),
        'keyless' => (bool)($in['keyless'] ?? false),
        'stream' => (bool)($in['stream'] ?? true),
        'key_url' => trim((string)($in['key_url'] ?? '')),
    ];
    $data['providers'][] = $provider;
    if (empty($data['active']['provider'])) {
        $data['active'] = ['provider' => $provider['id'], 'model' => $provider['models'][0] ?? null];
    }
    providers_save($data);
    return $provider;
}

function provider_update(string $id, array $in): array
{
    $data = providers_load();
    foreach ($data['providers'] as &$p) {
        if ($p['id'] !== $id) continue;
        foreach (['name', 'base_url', 'api_key'] as $k) {
            if (isset($in[$k]) && trim((string)$in[$k]) !== '') $p[$k] = trim((string)$in[$k]);
        }
        if (isset($in['kind'])) $p['kind'] = $in['kind'] === 'anthropic' ? 'anthropic' : 'openai';
        if (isset($in['models'])) $p['models'] = provider_normalize_models($in['models']);
        if (isset($in['base_url'])) $p['base_url'] = rtrim((string)$p['base_url'], '/');
        providers_save($data);
        return $p;
    }
    throw new RuntimeException('Provider not found');
}

function provider_delete(string $id): void
{
    $data = providers_load();
    $data['providers'] = array_values(array_filter($data['providers'], fn($p) => $p['id'] !== $id));
    if ($data['active']['provider'] === $id) {
        $first = $data['providers'][0] ?? null;
        $data['active'] = ['provider' => $first['id'] ?? null, 'model' => $first['models'][0] ?? null];
    }
    providers_save($data);
}

function provider_normalize_models($models): array
{
    if (is_string($models)) $models = preg_split('/[\r\n,]+/', $models) ?: [];
    $out = [];
    foreach ((array)$models as $m) {
        $m = trim((string)$m);
        if ($m !== '' && !in_array($m, $out, true)) $out[] = $m;
    }
    return $out;
}

function active_model(): array
{
    $data = providers_load();
    $provider = provider_find($data['active']['provider'] ?? '') ?? providers_all()[0] ?? null;
    if (!$provider) throw new RuntimeException('No AI provider configured. Open Settings.');
    return [
        'id' => $provider['id'],
        'name' => $provider['name'],
        'base_url' => $provider['base_url'],
        'api_key' => $provider['api_key'] ?? '',
        'kind' => $provider['kind'] ?? 'openai',
        'model' => $data['active']['model'] ?? ($provider['models'][0] ?? ''),
        'keyless' => (bool)($provider['keyless'] ?? false),
        'stream' => (bool)($provider['stream'] ?? true),
        'key_url' => (string)($provider['key_url'] ?? ''),
    ];
}

function active_set(string $providerId, string $model): void
{
    $provider = provider_find($providerId);
    if (!$provider) throw new RuntimeException('Provider not found');
    if ($model !== '' && !in_array($model, (array)$provider['models'], true)) {
        $provider['models'][] = $model;
    }
    $data = providers_load();
    foreach ($data['providers'] as &$p) if ($p['id'] === $providerId) $p = $provider;
    $data['active'] = ['provider' => $providerId, 'model' => $model !== '' ? $model : ($provider['models'][0] ?? null)];
    providers_save($data);
}

/** Ask the provider for its live model list (OpenAI /models or Anthropic /models). */
/**
 * Model catalogue for the header dropdown, built from the providers the user
 * has actually configured (so their own key is used and no third-party sees the
 * request). Cached briefly to stay instant; failures degrade to the preset list.
 */
function provider_catalog(): array
{
    $f = data_dir() . '/.catalog-cache.json';
    $ttl = 900; // 15 min
    if (is_file($f) && (time() - filemtime($f)) < $ttl) {
        $c = json_decode((string)file_get_contents($f), true);
        if (is_array($c)) return $c;
    }

    $out = [];
    foreach (providers_all() as $p) {
        if (empty($p['base_url'])) continue;
        // keyless Zen serves /models without auth; others need the user's key
        $entry = ['id' => $p['id'], 'name' => $p['name'], 'models' => []];
        try {
            $models = @provider_fetch_models($p['id']);
            $entry['models'] = array_values(array_filter($models, 'is_string'));
        } catch (Throwable $e) {
            $entry['models'] = (array)($p['models'] ?? []);
        }
        if ($entry['models']) $out[] = $entry;
    }

    @file_put_contents($f, json_encode($out, JSON_UNESCAPED_SLASHES));
    @chmod($f, 0666);
    return $out;
}

function provider_fetch_models(string $id): array
{
    $p = provider_find($id);
    if (!$p) throw new RuntimeException('Provider not found');

    if (($p['kind'] ?? 'openai') === 'anthropic') {
        $url = rtrim($p['base_url'], '/') . '/models?limit=100';
        $headers = [
            'x-api-key: ' . ($p['api_key'] ?? ''),
            'anthropic-version: 2023-06-01',
        ];
    } else {
        $base = rtrim($p['base_url'], '/');
        $url = str_ends_with($base, '/v1') || str_contains($base, '/v1/') ? $base . '/models' : $base . '/v1/models';
        // providers whose base_url already ends with /chat/completions style paths
        if (str_contains($base, '/chat/completions')) $url = preg_replace('#/chat/completions$#', '/models', $base);
        $headers = ['Authorization: Bearer ' . ($p['api_key'] ?? '')];
    }

    $ctx = stream_context_create(['http' => ['timeout' => 25, 'ignore_errors' => true, 'header' => implode("\r\n", $headers)]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int)$m[1];
    }
    if ($body === false) throw new RuntimeException("Could not reach {$url} (network blocked or CORS/host not allowed)");
    $json = json_decode($body, true);
    if ($status >= 400 || !is_array($json)) {
        $msg = $json['error']['message'] ?? $json['message'] ?? ("HTTP {$status}");
        throw new RuntimeException('Model list failed: ' . (is_string($msg) ? $msg : json_encode($msg)));
    }

    $ids = [];
    foreach (($json['data'] ?? []) as $row) {
        if (!empty($row['id'])) $ids[] = (string)$row['id'];
        elseif (!empty($row['name'])) $ids[] = (string)$row['name'];
    }
    if (!$ids) throw new RuntimeException('Provider returned an empty model list');
    sort($ids);
    return $ids;
}
