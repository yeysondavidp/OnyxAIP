<?php

namespace App\Mcp\Tools;

use App\Enums\AgentAbility;
use App\Jobs\WriteAuditLog;
use App\Models\Asset;
use App\Models\User;
use App\Support\FormRequestRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Base for every ONYX MCP tool (ADR-004, US-20.2).
 *
 * A tool is listed only to tokens carrying its ability, and handle() re-checks
 * that ability and then the normal Policy — the listing is convenience, the
 * check in agent() is the guard. Every call is audit-logged against the token.
 */
abstract class AgentTool extends Tool
{
    /** Maximum rows any list tool returns in one call. */
    public const MAX_RESULTS = 50;

    abstract protected function ability(): AgentAbility;

    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->agentCan($this->ability());
    }

    /**
     * Resolve the calling agent, enforce this tool's ability and audit the call.
     *
     * @throws AuthorizationException
     */
    protected function agent(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->agentCan($this->ability())) {
            throw new AuthorizationException("This access key is not allowed to: {$this->ability()->label()}.");
        }

        WriteAuditLog::dispatch(
            userId: $user->id,
            userRole: $user->role->value,
            action: 'mcp.tool_called',
            auditableType: PersonalAccessToken::class,
            auditableId: (int) $user->currentAccessToken()->getKey(),
            before: null,
            after: ['tool' => $this->name(), 'arguments' => $request->all()],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );

        return $user;
    }

    /**
     * Same Policy check the web controllers use.
     *
     * @param  array<int, mixed>|mixed  $arguments
     *
     * @throws AuthorizationException
     */
    protected function authorizeFor(User $user, string $ability, mixed $arguments): void
    {
        Gate::forUser($user)->authorize($ability, $arguments);
    }

    /**
     * Validate with a web FormRequest's own rules (see FormRequestRules).
     *
     * @param  class-string<FormRequest>  $formRequest
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $routeParameters
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateWith(string $formRequest, array $data, array $routeParameters = []): array
    {
        return FormRequestRules::validate($formRequest, $data, $routeParameters);
    }

    /** @param array<string, mixed> $planned */
    protected function dryRunResponse(string $summary, array $planned): Response
    {
        return Response::json(['dry_run' => true, 'would' => $summary, 'planned' => $planned]);
    }

    /**
     * The register fields an agent may see for an asset. Deliberately excludes
     * router credentials, QR file paths and anything else not needed on site.
     * Callers must eager-load `store:id,store_code` and `playerDetail`.
     *
     * @return array<string, mixed>
     */
    protected static function assetSummary(Asset $asset): array
    {
        return [
            'asset_code'     => $asset->asset_code,
            'asset_type'     => $asset->asset_type->value,
            'asset_name'     => $asset->asset_name,
            'manufacturer'   => $asset->manufacturer,
            'model'          => $asset->model,
            'serial_number'  => $asset->serial_number,
            'mac_address'    => $asset->playerDetail?->mac_address,
            'asset_status'   => $asset->asset_status->value,
            'store_code'     => $asset->store?->store_code,
            'location_notes' => $asset->location_notes,
        ];
    }

    protected static function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
