<?php

namespace App\Ai;

use App\Jobs\WriteAuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The only way a feature talks to an AI provider (ADR-003). It enforces the client's AI mode for
 * the data category (§6), a per-client rate limit, one failure type, and an audit entry that
 * records what kind of data left ONYX and where — never the content itself.
 */
class AiGateway
{
    public function __construct(private readonly AiManager $manager) {}

    /** @throws AiUnavailableException */
    public function complete(AiRequest $request): AiResponse
    {
        if (! $request->client->ai_mode->allows($request->category)) {
            throw AiUnavailableException::notAllowed();
        }

        $key = 'ai:client:'.$request->client->id;
        if (RateLimiter::tooManyAttempts($key, (int) config('ai.per_client_per_minute', 30))) {
            throw AiUnavailableException::rateLimited();
        }
        RateLimiter::hit($key, 60);

        $provider = $this->manager->getDefaultDriver();

        try {
            $response = $this->manager->driver()->complete($request);
        } catch (AiUnavailableException $e) {
            $this->audit($request, $provider, null);
            throw $e;
        } catch (\Throwable $e) {
            $this->audit($request, $provider, null);
            throw AiUnavailableException::providerFailed($e);
        }

        $this->audit($request, $provider, $response);

        return $response;
    }

    private function audit(AiRequest $request, string $provider, ?AiResponse $response): void
    {
        $user = auth()->user();

        WriteAuditLog::dispatch(
            userId: $user?->getAuthIdentifier() === null ? null : (int) $user->getAuthIdentifier(),
            userRole: $user instanceof User ? $user->role->value : null,
            action: $response === null ? 'ai.failed' : 'ai.completed',
            auditableType: Client::class,
            auditableId: $request->client->id,
            before: null,
            after: [
                'category'      => $request->category->value,
                'images'        => count($request->images),
                'provider'      => $provider,
                'model'         => $response?->model,
                'input_tokens'  => $response?->inputTokens,
                'output_tokens' => $response?->outputTokens,
            ],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );
    }
}
