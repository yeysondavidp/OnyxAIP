<?php

namespace App\Ai;

use App\Enums\AiDataCategory;
use App\Models\Client;

/**
 * One AI call, provider-neutral. Images can only be SanitisedImage, so metadata stripping
 * (ADR-003 §6) cannot be skipped; the category must match what is attached.
 */
final readonly class AiRequest
{
    /**
     * @param  list<SanitisedImage>  $images
     * @param  array<string, mixed>|null  $jsonSchema  JSON Schema the reply must follow
     */
    public function __construct(
        public Client $client,
        public AiDataCategory $category,
        public string $system,
        public string $prompt,
        public array $images = [],
        public ?array $jsonSchema = null,
        public int $maxTokens = 2048,
    ) {
        if ($category === AiDataCategory::Text && $images !== []) {
            throw new \InvalidArgumentException('A text request cannot carry images.');
        }

        if ($category !== AiDataCategory::Text && $images === []) {
            throw new \InvalidArgumentException("A {$category->value} request needs at least one image.");
        }
    }
}
