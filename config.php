<?php
declare(strict_types=1);

return [
    // Any OpenAI-compatible endpoint: OpenAI, OpenRouter, Groq, Ollama, LM Studio, your own server...
    'api_key'    => getenv('OPENRODE_API_KEY') ?: 'sk-your-key-here',
    'base_url'   => rtrim(getenv('OPENRODE_BASE_URL') ?: 'https://api.openai.com/v1', '/'),
    'model'      => getenv('OPENRODE_MODEL') ?: 'gpt-4o-mini',
    'temperature'=> (float)(getenv('OPENRODE_TEMPERATURE') ?: 0.3),
    'max_tokens' => (int)(getenv('OPENRODE_MAX_TOKENS') ?: 8000),

    // Preview / test servers
    'port_start' => 8100,
    'port_end'   => 8399,
    'php_bin'    => getenv('OPENRODE_PHP') ?: PHP_BINARY,

    // Project storage
    'data_dir'   => __DIR__ . '/data',
];
