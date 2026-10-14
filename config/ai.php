<?php

/*
 * AI provider gateway (ADR-003). Switching provider is configuration only. Every call goes
 * through App\Ai\AiGateway, which enforces the client's AI mode and data rules (ADR-003 §6).
 * Leave AI_PROVIDER unset (= "disabled") and every AI feature falls back to manual entry.
 */
return [

    'default' => env('AI_PROVIDER', 'disabled'),

    // Requests per client per minute — a runaway loop can't run up a provider bill.
    'per_client_per_minute' => (int) env('AI_PER_CLIENT_PER_MINUTE', 30),

    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 20),

    'providers' => [

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model'   => env('AI_MODEL', 'claude-haiku-5-5'),
            'effort'  => env('AI_EFFORT', 'low'),
        ],

        // Paid OpenAI or a self-hosted Ollama (base URL e.g. http://ollama.internal:11434/v1).
        'openai_compatible' => [
            'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key'  => env('AI_API_KEY'),
            'model'    => env('AI_MODEL'),
            // Ollama: true / false. OpenAI: false / true.
            'image_url_as_string'   => (bool) env('AI_IMAGE_URL_AS_STRING', false),
            'json_schema_supported' => (bool) env('AI_JSON_SCHEMA_SUPPORTED', true),
        ],

    ],

];
