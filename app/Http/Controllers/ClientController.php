<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\SlaProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Client::class);

        return view('clients.index');
    }

    public function create(): View
    {
        $this->authorize('create', Client::class);

        return view('clients.create', ['slaProfiles' => $this->assignableSlaProfiles()]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated() + ['is_active' => true]);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', "Client '{$client->client_name}' has been created.");
    }

    public function show(Client $client): View
    {
        $this->authorize('view', $client);

        return view('clients.show', ['client' => $client->load('slaProfile')]);
    }

    public function edit(Client $client): View
    {
        $this->authorize('update', $client);

        return view('clients.edit', [
            'client'      => $client,
            'slaProfiles' => $this->assignableSlaProfiles($client),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('success', "Client '{$client->client_name}' has been updated.");
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->update(['is_active' => false]);

        return redirect()
            ->route('clients.index')
            ->with('success', "Client '{$client->client_name}' has been deactivated.");
    }

    /**
     * Active SLA profiles, plus the client's currently-assigned profile even if it has
     * since been deactivated — so it doesn't silently disappear from the select and
     * re-saving the form doesn't spuriously fail validation.
     *
     * @return Collection<int, SlaProfile>
     */
    private function assignableSlaProfiles(?Client $client = null): Collection
    {
        $profiles = SlaProfile::where('is_active', true)->orderBy('name')->get();

        if ($client?->sla_profile_id && ! $profiles->contains('id', $client->sla_profile_id)) {
            $current = SlaProfile::find($client->sla_profile_id);

            if ($current !== null) {
                $profiles->push($current);
            }
        }

        return $profiles;
    }
}
