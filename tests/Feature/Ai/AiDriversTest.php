<?php

use Anthropic\Client;
use App\Ai\AiRequest;
use App\Ai\AiUnavailableException;
use App\Ai\Drivers\AnthropicDriver;
use App\Ai\Drivers\OpenAiCompatibleDriver;
use App\Ai\SanitisedImage;
use App\Enums\AiDataCategory;
use App\Models\Client as OnyxClient;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const LABEL_SCHEMA = ['type' => 'object', 'properties' => ['serial' => ['type' => 'string']], 'required' => ['serial'], 'additionalProperties' => false];

function labelRequest(): AiRequest
{
    $path = tempnam(sys_get_temp_dir(), 'drv-');
    imagejpeg(imagecreatetruecolor(40, 20), $path);

    return new AiRequest(OnyxClient::factory()->make(), AiDataCategory::LabelCrop, 'Read device labels.', 'Read this label.', [SanitisedImage::fromFile($path)], LABEL_SCHEMA);
}

/** @param list<array<string, mixed>> $history */
function anthropicDriverReturning(array $body, array &$history): AnthropicDriver
{
    $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode($body))]));
    $stack->push(Middleware::history($history));

    return new AnthropicDriver(new Client(apiKey: 'test-key', requestOptions: ['transporter' => new Guzzle(['handler' => $stack]), 'maxRetries' => 0]), 'claude-haiku-5-5', 'low');
}

function anthropicMessage(string $text, string $stopReason = 'end_turn'): array
{
    return [
        'id'          => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-5-5',
        'content'     => [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'], ['type' => 'text', 'text' => $text]],
        'stop_reason' => $stopReason, 'stop_sequence' => null,
        'usage'       => ['input_tokens' => 1200, 'output_tokens' => 40],
    ];
}

it('anthropic driver sends the sanitised image, schema and effort, and reads text past thinking blocks', function () {
    $history  = [];
    $response = anthropicDriverReturning(anthropicMessage('{"serial":"MIB192600317"}'), $history)->complete(labelRequest());
    $sent     = json_decode((string) $history[0]['request']->getBody(), true);

    expect([
        'model'  => $sent['model'],
        'image'  => $sent['messages'][0]['content'][0]['source']['media_type'],
        'format' => $sent['output_config']['format']['type'],
        'effort' => $sent['output_config']['effort'],
        'serial' => $response->json()['serial'],
        'tokens' => [$response->inputTokens, $response->outputTokens],
    ])->toBe(['model' => 'claude-haiku-5-5', 'image' => 'image/jpeg', 'format' => 'json_schema', 'effort' => 'low', 'serial' => 'MIB192600317', 'tokens' => [1200, 40]]);
});

it('anthropic driver names the workspace when an organisation-level key needs it', function () {
    $history = [];
    $stack   = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode(anthropicMessage('{"serial":"X1"}')))]));
    $stack->push(Middleware::history($history));
    $driver = new AnthropicDriver(new Client(apiKey: 'test-key', requestOptions: ['transporter' => new Guzzle(['handler' => $stack]), 'maxRetries' => 0]), 'claude-haiku-5-5', 'low', 'wrkspc_test');

    $driver->complete(labelRequest());

    expect($history[0]['request']->getHeaderLine('anthropic-workspace-id'))->toBe('wrkspc_test');
});

it('anthropic driver turns a refusal into the one AI exception', function () {
    $history = [];

    anthropicDriverReturning(anthropicMessage('', 'refusal'), $history)->complete(labelRequest());
})->throws(AiUnavailableException::class, 'declined');

it('openai-compatible driver speaks OpenAI by default', function () {
    Http::fake(['api.openai.test/*' => Http::response(['model' => 'gpt-x', 'choices' => [['message' => ['content' => '{"serial":"A1"}'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 9, 'completion_tokens' => 3]])]);

    $response = (new OpenAiCompatibleDriver('https://api.openai.test/v1', 'sk-test', 'gpt-x', false, true, 10))->complete(labelRequest());

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.openai.test/v1/chat/completions'
        && $r->hasHeader('Authorization', 'Bearer sk-test')
        && is_array($r['messages'][1]['content'][0]['image_url'])
        && $r['response_format']['type'] === 'json_schema');
    expect([$response->json()['serial'], $response->inputTokens])->toBe(['A1', 9]);
});

it('the same driver works unmodified against a self-hosted Ollama endpoint', function () {
    Http::fake(['ollama.internal:11434/*' => Http::response(['model' => 'gemma3', 'choices' => [['message' => ['content' => '{"serial":"B2"}'], 'finish_reason' => 'stop']]])]);

    $response = (new OpenAiCompatibleDriver('http://ollama.internal:11434/v1', null, 'gemma3', true, false, 10))->complete(labelRequest());

    Http::assertSent(fn (Request $r) => $r->url() === 'http://ollama.internal:11434/v1/chat/completions'
        && ! $r->hasHeader('Authorization')
        && is_string($r['messages'][1]['content'][0]['image_url'])
        && str_starts_with($r['messages'][1]['content'][0]['image_url'], 'data:image/jpeg;base64,')
        && $r['response_format']['type'] === 'json_object'
        && str_contains($r['messages'][0]['content'], '"serial"'));
    expect([$response->json()['serial'], $response->inputTokens])->toBe(['B2', null]);
});

it('openai-compatible driver turns an HTTP failure into the one AI exception', function () {
    Http::fake(['*' => Http::response('down', 503)]);

    (new OpenAiCompatibleDriver('https://api.openai.test/v1', 'k', 'gpt-x', false, true, 10))->complete(labelRequest());
})->throws(AiUnavailableException::class);
