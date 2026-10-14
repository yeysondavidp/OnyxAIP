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

    /** @param string|list<string> $reply one reply, or a sequence returned call by call */
    public function __construct(
        private readonly string|array $reply = '{}',
        private readonly bool $fail = false,
    ) {}

    public function complete(AiRequest $request): AiResponse
    {
        $this->requests[] = $request;

        if ($this->fail) {
            throw AiUnavailableException::providerFailed();
        }

        $reply = is_array($this->reply) ? $this->reply[(count($this->requests) - 1) % count($this->reply)] : $this->reply;

        return new AiResponse($reply, 'fake', 'fake-model', 0, 0);
    }
}
