<?php

namespace App\Ai;

/**
 * The single failure type every AI caller handles (ADR-003 decision 5): the feature falls back to
 * manual entry. The message is plain language and never includes provider payloads or keys.
 */
class AiUnavailableException extends \RuntimeException
{
    public static function notAllowed(): self
    {
        return new self('AI is switched off for this client.');
    }

    public static function rateLimited(): self
    {
        return new self('Too many AI requests right now. Please fill this in by hand or try again in a minute.');
    }

    public static function providerFailed(?\Throwable $previous = null): self
    {
        return new self('The AI service is not available right now. Please fill this in by hand.', 0, $previous);
    }

    public static function declined(): self
    {
        return new self('The AI service declined this request. Please fill this in by hand.');
    }
}
