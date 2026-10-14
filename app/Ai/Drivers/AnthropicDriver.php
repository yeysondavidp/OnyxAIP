<?php

namespace App\Ai\Drivers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Messages\Base64ImageSource;
use Anthropic\Messages\ImageBlockParam;
use Anthropic\Messages\TextBlockParam;
use App\Ai\AiRequest;
use App\Ai\AiResponse;
use App\Ai\AiUnavailableException;
use App\Contracts\AiClient;
use Psr\Http\Client\ClientExceptionInterface;

/** Claude through the official Anthropic SDK — the only file that imports it (ADR-003). */
final class AnthropicDriver implements AiClient
{
    public function __construct(
        private readonly Client $client,
        private readonly string $model,
        private readonly string $effort,
        // Required when the API key is organisation-level rather than workspace-scoped.
        private readonly ?string $workspaceId = null,
    ) {}

    public function complete(AiRequest $request): AiResponse
    {
        $content = [];
        foreach ($request->images as $image) {
            $content[] = ImageBlockParam::with(source: Base64ImageSource::with(data: $image->base64(), mediaType: $image->mediaType()));
        }
        $content[] = TextBlockParam::with(text: $request->prompt);

        $outputConfig = ['effort' => $request->effort ?? $this->effort];
        if ($request->jsonSchema !== null) {
            $outputConfig['format'] = ['type' => 'json_schema', 'schema' => $request->jsonSchema];
        }

        try {
            $message = $this->client->messages->create(
                maxTokens: $request->maxTokens,
                messages: [['role' => 'user', 'content' => $content]],
                model: $this->model,
                outputConfig: $outputConfig,
                system: $request->system,
                workspaceID: $this->workspaceId,
            );
        } catch (AnthropicException|ClientExceptionInterface $e) {
            throw AiUnavailableException::providerFailed($e);
        }

        if ($message->stopReason === 'refusal') {
            throw AiUnavailableException::declined();
        }

        // Thinking blocks may come first — read the text by type, not position.
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        if ($text === '' || $message->stopReason === 'max_tokens') {
            throw AiUnavailableException::providerFailed();
        }

        return new AiResponse($text, 'anthropic', $message->model, $message->usage->inputTokens, $message->usage->outputTokens);
    }
}
