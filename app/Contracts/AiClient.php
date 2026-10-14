<?php

namespace App\Contracts;

use App\Ai\AiRequest;
use App\Ai\AiResponse;
use App\Ai\AiUnavailableException;

/**
 * One AI provider behind the ADR-003 gateway. Features never call a driver directly —
 * they call App\Ai\AiGateway, which enforces the client's AI setting, limits and audit.
 */
interface AiClient
{
    /** @throws AiUnavailableException on any failure, timeout or refusal */
    public function complete(AiRequest $request): AiResponse;
}
