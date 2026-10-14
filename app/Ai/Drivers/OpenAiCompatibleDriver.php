<?php

namespace App\Ai\Drivers;

use App\Ai\AiRequest;
use App\Ai\AiResponse;
use App\Ai\AiUnavailableException;
use App\Contracts\AiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Any `/v1/chat/completions` endpoint: paid OpenAI or a self-hosted Ollama (ADR-003). The two
 * documented differences are config, not code: Ollama's examples send `image_url` as a plain
 * data-URI string (OpenAI uses `{url: ...}`), and Ollama documents JSON mode, not `json_schema`.
 */
final class OpenAiCompatibleDriver implements AiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly bool $imageUrlAsString,
        private readonly bool $jsonSchemaSupported,
        private readonly int $timeoutSeconds,
    ) {}

    public function complete(AiRequest $request): AiResponse
    {
        $parts = [];
        foreach ($request->images as $image) {
            $dataUri = 'data:'.$image->mediaType().';base64,'.$image->base64();
            $parts[] = ['type' => 'image_url', 'image_url' => $this->imageUrlAsString ? $dataUri : ['url' => $dataUri]];
        }
        $parts[] = ['type' => 'text', 'text' => $request->prompt];

        $system = $request->system;
        $body   = [
            'model'      => $this->model,
            'max_tokens' => $request->maxTokens,
            'messages'   => [],
        ];

        if ($request->jsonSchema !== null) {
            if ($this->jsonSchemaSupported) {
                $body['response_format'] = ['type' => 'json_schema', 'json_schema' => ['name' => 'result', 'schema' => $request->jsonSchema, 'strict' => true]];
            } else {
                $body['response_format'] = ['type' => 'json_object'];
                $system .= "\n\nReply with one JSON object that follows this JSON Schema:\n".json_encode($request->jsonSchema);
            }
        }

        $body['messages'] = [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $parts]];

        try {
            $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
                ->when($this->apiKey !== null && $this->apiKey !== '', fn ($http) => $http->withToken((string) $this->apiKey))
                ->timeout($this->timeoutSeconds)
                ->retry(2, 500, throw: true)
                ->acceptJson()
                ->post('/chat/completions', $body);
        } catch (ConnectionException|RequestException $e) {
            throw AiUnavailableException::providerFailed($e);
        }

        $choice = $response->json('choices.0');
        $text   = is_array($choice) ? (string) data_get($choice, 'message.content', '') : '';

        if ($text === '' || data_get($choice, 'finish_reason') === 'length') {
            throw AiUnavailableException::providerFailed();
        }

        return new AiResponse(
            $text,
            'openai_compatible',
            (string) ($response->json('model') ?? $this->model),
            is_numeric($response->json('usage.prompt_tokens')) ? (int) $response->json('usage.prompt_tokens') : null,
            is_numeric($response->json('usage.completion_tokens')) ? (int) $response->json('usage.completion_tokens') : null,
        );
    }
}
