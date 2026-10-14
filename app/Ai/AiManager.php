<?php

namespace App\Ai;

use Anthropic\Client;
use App\Ai\Drivers\AnthropicDriver;
use App\Ai\Drivers\DisabledDriver;
use App\Ai\Drivers\OpenAiCompatibleDriver;
use App\Contracts\AiClient;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Manager;

/**
 * Resolves the configured AI driver, like Storage/Mail resolve theirs (ADR-003 decision 2).
 *
 * @method AiClient driver(?string $driver = null)
 */
class AiManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('ai.default', 'disabled');
    }

    public function createDisabledDriver(): AiClient
    {
        return new DisabledDriver;
    }

    public function createAnthropicDriver(): AiClient
    {
        $config = (array) $this->config->get('ai.providers.anthropic');

        // The SDK leaves timeouts to the HTTP transport, so give it one that enforces ours.
        $client = new Client(
            apiKey: (string) ($config['api_key'] ?? ''),
            requestOptions: [
                'transporter' => new GuzzleClient(['timeout' => $this->timeout(), 'connect_timeout' => 5]),
                'maxRetries'  => 1,
            ],
        );

        $workspace = (string) ($config['workspace_id'] ?? '');

        return new AnthropicDriver($client, (string) $config['model'], (string) $config['effort'], $workspace !== '' ? $workspace : null);
    }

    public function createOpenaiCompatibleDriver(): AiClient
    {
        $config = (array) $this->config->get('ai.providers.openai_compatible');

        return new OpenAiCompatibleDriver(
            baseUrl: (string) $config['base_url'],
            apiKey: isset($config['api_key']) ? (string) $config['api_key'] : null,
            model: (string) $config['model'],
            imageUrlAsString: (bool) $config['image_url_as_string'],
            jsonSchemaSupported: (bool) $config['json_schema_supported'],
            timeoutSeconds: $this->timeout(),
        );
    }

    private function timeout(): int
    {
        return (int) $this->config->get('ai.timeout_seconds', 20);
    }
}
