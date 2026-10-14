<?php

namespace App\Ai;

/** Provider-neutral result — no vendor type crosses the AiClient boundary (ADR-003). */
final readonly class AiResponse
{
    public function __construct(
        public string $text,
        public string $provider,
        public string $model,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}

    /**
     * The text parsed as JSON, for requests made with a JSON schema.
     *
     * @return array<string, mixed>
     *
     * @throws AiUnavailableException when the provider did not return a JSON object
     */
    public function json(): array
    {
        $decoded = json_decode($this->text, true);

        if (! is_array($decoded)) {
            throw AiUnavailableException::providerFailed();
        }

        return $decoded;
    }
}
