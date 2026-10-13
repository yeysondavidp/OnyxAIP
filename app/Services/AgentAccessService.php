<?php

namespace App\Services;

use App\Enums\AgentAbility;
use App\Enums\UserRole;
use App\Jobs\WriteAuditLog;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Agent service accounts and their Sanctum tokens (US-20.1, ADR-004).
 *
 * An agent is a User with role `agent`, an unusable random password and an
 * undeliverable `.invalid` email, so it can only ever authenticate by token.
 */
class AgentAccessService
{
    public const MAX_EXPIRY_DAYS = 90;

    /**
     * Create an agent account and its first token.
     *
     * @param  list<AgentAbility>  $abilities
     * @return array{0: User, 1: string} the agent and the plain-text token (shown once)
     */
    public function createAgent(User $issuer, string $name, ?int $clientId, array $abilities, int $expiresInDays): array
    {
        return DB::transaction(function () use ($issuer, $name, $clientId, $abilities, $expiresInDays): array {
            $agent = User::create([
                'name'      => $name,
                'email'     => 'agent-'.Str::uuid().'@agents.invalid',
                'password'  => Str::random(64),
                'client_id' => $clientId,
                'role'      => UserRole::Agent,
            ]);

            return [$agent, $this->issueToken($issuer, $agent, $abilities, $expiresInDays)];
        });
    }

    /**
     * Issue a new token for an existing agent.
     *
     * @param  list<AgentAbility>  $abilities
     * @return string the plain-text token (shown once)
     */
    public function issueToken(User $issuer, User $agent, array $abilities, int $expiresInDays): string
    {
        if (! $agent->isAgent()) {
            throw new \InvalidArgumentException('Tokens can only be issued to agent accounts.');
        }

        if ($expiresInDays < 1 || $expiresInDays > self::MAX_EXPIRY_DAYS) {
            throw new \InvalidArgumentException('Token expiry must be between 1 and '.self::MAX_EXPIRY_DAYS.' days.');
        }

        $abilityValues = array_map(fn (AgentAbility $a) => $a->value, $abilities);

        return DB::transaction(function () use ($issuer, $agent, $abilityValues, $expiresInDays): string {
            $newToken = $agent->createToken(
                $agent->name.' '.now()->format('Y-m-d'),
                $abilityValues,
                Carbon::now()->addDays($expiresInDays),
            );

            $token = $newToken->accessToken;
            $token->forceFill(['created_by_user_id' => $issuer->id])->save();

            $this->audit($issuer, 'agent_token.issued', $token, null, [
                'agent_id'   => $agent->id,
                'abilities'  => $abilityValues,
                'expires_at' => $token->expires_at->toIso8601String(),
            ]);

            return $newToken->plainTextToken;
        });
    }

    public function revokeToken(User $issuer, PersonalAccessToken $token): void
    {
        $before = [
            'agent_id'   => $token->tokenable_id,
            'abilities'  => $token->abilities,
            'expires_at' => $token->expires_at->toIso8601String(),
        ];

        $token->delete();

        $this->audit($issuer, 'agent_token.revoked', $token, $before, null);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(User $actor, string $action, SanctumPersonalAccessToken $token, ?array $before, ?array $after): void
    {
        WriteAuditLog::dispatch(
            userId: $actor->id,
            userRole: $actor->role->value,
            action: $action,
            auditableType: PersonalAccessToken::class,
            auditableId: (int) $token->getKey(),
            before: $before,
            after: $after,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );
    }
}
