<?php

namespace App\Http\Controllers;

use App\Enums\AgentAbility;
use App\Enums\UserRole;
use App\Http\Requests\IssueAgentTokenRequest;
use App\Http\Requests\StoreAgentRequest;
use App\Models\Client;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\AgentAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Settings → Agent access (US-20.1, ADR-004). */
class AgentAccessController extends Controller
{
    public function __construct(private readonly AgentAccessService $agents) {}

    public function index(): View
    {
        $this->authorize('manageAgents', User::class);

        $agents = User::where('role', UserRole::Agent)
            ->with(['tokens' => fn ($q) => $q->with('createdBy:id,name')->orderByDesc('created_at')])
            ->orderBy('name')
            ->get();

        $clientNames = Client::whereIn('id', $agents->pluck('client_id')->filter())->pluck('client_name', 'id');

        return view('settings.agents.index', compact('agents', 'clientNames'));
    }

    public function create(): View
    {
        $this->authorize('manageAgents', User::class);

        return view('settings.agents.create', $this->formData() + [
            'agent'   => null,
            'clients' => Client::where('is_active', true)->orderBy('client_name')->get(['id', 'client_name']),
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $clientId = $request->validated('client_id');

        [$agent, $plainToken] = $this->agents->createAgent(
            $request->user(),
            $request->validated('name'),
            $clientId === null ? null : (int) $clientId,
            $request->abilities(),
            (int) $request->validated('expires_in_days'),
        );

        return $this->showTokenOnce($agent, $plainToken);
    }

    public function createToken(User $agent): View
    {
        $this->authorize('manageAgents', User::class);
        abort_unless($agent->isAgent(), 404);

        return view('settings.agents.create', $this->formData() + ['agent' => $agent, 'clients' => collect()]);
    }

    public function storeToken(IssueAgentTokenRequest $request, User $agent): RedirectResponse
    {
        abort_unless($agent->isAgent(), 404);

        $plainToken = $this->agents->issueToken(
            $request->user(),
            $agent,
            $request->abilities(),
            (int) $request->validated('expires_in_days'),
        );

        return $this->showTokenOnce($agent, $plainToken);
    }

    public function destroyToken(User $agent, PersonalAccessToken $token): RedirectResponse
    {
        $this->authorize('manageAgents', User::class);
        abort_unless(
            $agent->isAgent() && $token->tokenable_type === $agent->getMorphClass() && (int) $token->tokenable_id === $agent->id,
            404,
        );

        $this->agents->revokeToken(auth()->user(), $token);

        return redirect()->route('agents.index')->with('success', "Token revoked. {$agent->name} can no longer use it.");
    }

    /** The plain token is flashed for exactly one page view and never stored unhashed. */
    private function showTokenOnce(User $agent, string $plainToken): RedirectResponse
    {
        return redirect()->route('agents.index')->with('new_agent_token', [
            'agent' => $agent->name,
            'token' => $plainToken,
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'abilities'     => AgentAbility::cases(),
            'expiryOptions' => IssueAgentTokenRequest::EXPIRY_OPTIONS,
        ];
    }
}
