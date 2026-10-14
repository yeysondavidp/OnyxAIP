<?php

namespace App\Ai\Drivers;

use App\Ai\AiRequest;
use App\Ai\AiResponse;
use App\Ai\AiUnavailableException;
use App\Contracts\AiClient;

/** The default when no provider is configured: every feature falls back to manual entry. */
final class DisabledDriver implements AiClient
{
    public function complete(AiRequest $request): AiResponse
    {
        throw AiUnavailableException::providerFailed();
    }
}
