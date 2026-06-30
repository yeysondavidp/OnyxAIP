<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
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

        return view('clients.create');
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

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        $this->authorize('update', $client);

        return view('clients.edit', compact('client'));
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
}
