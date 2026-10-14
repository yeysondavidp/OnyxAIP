<?php

namespace App\Ai\Drivers;

use App\Ai\AiRequest;
use App\Ai\AiResponse;
use App\Ai\AiUnavailableException;
use App\Contracts\AiClient;

/** Test double for every AI feature's tests — no network, no key (ADR-003 decision 4). */
final class FakeAiClient implements AiClient
{
    /** @var list<AiRequest> */
    public array $requests = [];

    public function __construct(
        private readonly string $reply = '{}',
        private readonly bool $fail = false,
    ) {}

    public function complete(AiRequest $request): AiResponse
    {
        $this->requests[] = $request;

        if ($this->fail) {
            throw AiUnavailableException::providerFailed();
        }

        return new AiResponse($this->reply, 'fake', 'fake-model', 0, 0);
    }
}
