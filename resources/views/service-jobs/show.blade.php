<x-layouts.app title="{{ $job->job_name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('jobs.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Service Jobs</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $job->job_reference }}</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        @if (! $job->trashed())
            <x-onyx.button href="{{ route('jobs.edit', $job) }}" variant="outline" size="sm">Edit</x-onyx.button>
        @endif
    </x-slot:headerActions>

    {{-- Flash messages --}}
    @if (session('success'))
        <x-onyx.alert tone="positive" style="margin-bottom: var(--space-4);">{{ session('success') }}</x-onyx.alert>
    @endif

    {{-- Header --}}
    <div style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-1); flex-wrap: wrap;">
            <h1 style="font-size: var(--fs-28); font-weight: var(--weight-bold); color: var(--text-primary);">{{ $job->job_name }}</h1>
            <x-onyx.badge :tone="$job->job_status->tone()" variant="soft">{{ $job->job_status->label() }}</x-onyx.badge>
            @if ($job->sla_breached)
                <x-onyx.badge tone="critical" variant="solid">SLA Breached</x-onyx.badge>
            @endif
            @if ($job->job_level > 0)
                <x-onyx.badge tone="neutral" variant="soft">{{ $job->job_level === 1 ? 'Sub-job' : 'Remediation' }}</x-onyx.badge>
            @endif
        </div>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-4); font-size: var(--fs-14); color: var(--text-secondary);">
            <span style="font-family: monospace; font-size: var(--fs-13);">{{ $job->job_reference }}</span>
            <span>·</span>
            <span>{{ $job->job_type->label() }}</span>
            @if ($job->client)
                <span>·</span>
                <a href="{{ route('clients.show', $job->client) }}" style="color: var(--bronze-600); text-decoration: none;">{{ $job->client->client_name }}</a>
            @endif
            @if ($job->store)
                <span>·</span>
                <a href="{{ route('stores.show', $job->store) }}" style="color: var(--bronze-600); text-decoration: none;">{{ $job->store->store_name }}</a>
            @endif
        </div>
    </div>

    {{-- Two-column layout --}}
    <div style="display: grid; grid-template-columns: 1fr 320px; gap: var(--space-6); align-items: start;">

        {{-- Left ─────────────────────────────────────────────────────── --}}
        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            {{-- Status actions --}}
            @php
                $status  = $job->job_status;
                $canEdit = ! $job->trashed();
            @endphp

            @if ($canEdit && $status === \App\Enums\JobStatus::Draft)
                <x-onyx.card variant="default" padding="lg">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); margin-bottom: var(--space-3);">Send invitation</h2>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-bottom: var(--space-3);">
                        Mark this job as invited to notify assigned technicians.
                    </p>
                    <form method="POST" action="{{ route('jobs.invite', $job) }}">
                        @csrf
                        <x-onyx.button type="submit" variant="primary">Send invitation</x-onyx.button>
                    </form>
                </x-onyx.card>
            @endif

            @if ($canEdit && $status === \App\Enums\JobStatus::InProgress)
                <x-onyx.card variant="default" padding="lg">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); margin-bottom: var(--space-3);">Force complete</h2>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-bottom: var(--space-3);">
                        Force-complete the job if not all technicians have submitted. A reason is required.
                    </p>
                    <form method="POST" action="{{ route('jobs.force-complete', $job) }}">
                        @csrf
                        <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                            <x-onyx.textarea name="force_complete_reason" label="Reason" rows="2"
                                :error="$errors->first('force_complete_reason')" required
                                placeholder="Explain why you are force-completing this job…" />
                            <div>
                                <x-onyx.button type="submit" variant="primary">Force complete</x-onyx.button>
                            </div>
                        </div>
                    </form>
                </x-onyx.card>
            @endif

            @if ($canEdit && $status === \App\Enums\JobStatus::Completed)
                <x-onyx.card variant="default" padding="lg">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); margin-bottom: var(--space-3);">Review job</h2>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-bottom: var(--space-3);">
                        Validate the job if the work is satisfactory, or flag it for remediation.
                    </p>
                    <div style="display: flex; gap: var(--space-3);">
                        <form method="POST" action="{{ route('jobs.validate', $job) }}">
                            @csrf
                            <x-onyx.button type="submit" variant="primary">Validate</x-onyx.button>
                        </form>
                        <form method="POST" action="{{ route('jobs.flag-remediation', $job) }}">
                            @csrf
                            <x-onyx.button type="submit" variant="outline">Flag for remediation</x-onyx.button>
                        </form>
                    </div>
                </x-onyx.card>
            @endif

            {{-- Job description --}}
            <x-onyx.card variant="default" padding="lg">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Scope &amp; description</h2>
                <p style="font-size: var(--fs-14); color: var(--text-primary); white-space: pre-wrap; line-height: 1.6;">{{ $job->job_description }}</p>
            </x-onyx.card>

            {{-- Affected assets --}}
            <x-onyx.card variant="default" padding="none">
                <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between;">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">Affected Assets</h2>
                    <span style="font-size: var(--fs-13); color: var(--text-secondary);">{{ $job->assets->count() }} asset{{ $job->assets->count() === 1 ? '' : 's' }}</span>
                </div>
                @if ($job->assets->isEmpty())
                    <div style="padding: var(--space-6);">
                        <x-onyx.empty title="No assets attached" description="Edit the job to attach affected assets." />
                    </div>
                @else
                    <div style="divide-y: var(--border-subtle);">
                        @foreach ($job->assets as $asset)
                            <div style="padding: var(--space-4) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <a href="{{ route('assets.show', $asset) }}"
                                        style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary); text-decoration: none;">
                                        {{ $asset->asset_name }}
                                    </a>
                                    <div style="font-size: var(--fs-12); color: var(--text-secondary); margin-top: var(--space-1);">
                                        <span style="font-family: monospace;">{{ $asset->asset_code }}</span>
                                        · {{ $asset->asset_type->label() }}
                                        @if ($asset->location_notes)
                                            · {{ $asset->location_notes }}
                                        @endif
                                    </div>
                                </div>
                                <x-onyx.badge :tone="$asset->asset_status->tone()" variant="soft">{{ $asset->asset_status->label() }}</x-onyx.badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-onyx.card>

            {{-- PM Attachments --}}
            <x-onyx.card variant="default" padding="none">
                <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle);">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">Attachments</h2>
                </div>

                @if ($job->attachments->isEmpty())
                    <div style="padding: var(--space-4) var(--space-6);">
                        <p style="font-size: var(--fs-13); color: var(--text-tertiary);">No attachments yet.</p>
                    </div>
                @else
                    @foreach ($job->attachments as $att)
                        <div style="padding: var(--space-3) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: var(--space-3);">
                                <span style="font-size: var(--fs-14); color: var(--text-primary);">{{ $att->original_filename }}</span>
                                <span style="font-size: var(--fs-12); color: var(--text-tertiary);">{{ $att->mime_type }}</span>
                            </div>
                            <div style="display: flex; gap: var(--space-2);">
                                <a href="{{ route('jobs.attachments.download', [$job, $att]) }}"
                                    style="font-size: var(--fs-13); color: var(--bronze-600); text-decoration: none;">Download</a>
                                <form method="POST" action="{{ route('jobs.attachments.destroy', [$job, $att]) }}"
                                    onsubmit="return confirm('Delete this attachment?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="font-size: var(--fs-13); color: var(--critical-600); background: none; border: none; cursor: pointer; padding: 0;">Delete</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endif

                {{-- Upload form --}}
                @if (! $job->trashed())
                    <div style="padding: var(--space-4) var(--space-6);">
                        @error('attachment')
                            <x-onyx.alert tone="critical" style="margin-bottom: var(--space-3);">{{ $message }}</x-onyx.alert>
                        @enderror
                        <form method="POST" action="{{ route('jobs.attachments.store', $job) }}" enctype="multipart/form-data"
                            style="display: flex; gap: var(--space-3); align-items: flex-end;">
                            @csrf
                            <div style="flex: 1;">
                                <label style="display: block; font-size: var(--fs-13); color: var(--text-secondary); margin-bottom: var(--space-1);">
                                    Add attachment (PDF, image, Word, Excel — max 20 MB)
                                </label>
                                <input type="file" name="attachment"
                                    style="font-size: var(--fs-13); border: 1px solid var(--border-default); border-radius: var(--radius-md); padding: var(--space-2); width: 100%; min-height: 44px;">
                            </div>
                            <x-onyx.button type="submit" variant="outline" size="sm">Upload</x-onyx.button>
                        </form>
                    </div>
                @endif
            </x-onyx.card>

            {{-- Child jobs --}}
            @if ($job->children->isNotEmpty() || $job->job_level === 0)
                <x-onyx.card variant="default" padding="none">
                    <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between;">
                        <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">
                            {{ $job->job_level === 0 ? 'Sub-jobs' : 'Remediation' }}
                        </h2>
                        @if ($job->job_level <= 1 && ! $job->trashed())
                            <x-onyx.button href="{{ route('jobs.create', ['parent_job_id' => $job->id]) }}" variant="outline" size="sm">
                                Add {{ $job->job_level === 0 ? 'sub-job' : 'remediation' }}
                            </x-onyx.button>
                        @endif
                    </div>
                    @if ($job->children->isEmpty())
                        <div style="padding: var(--space-4) var(--space-6);">
                            <p style="font-size: var(--fs-13); color: var(--text-tertiary);">No sub-jobs yet.</p>
                        </div>
                    @else
                        @foreach ($job->children as $child)
                            <div style="padding: var(--space-4) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <a href="{{ route('jobs.show', $child) }}"
                                        style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary); text-decoration: none;">
                                        {{ $child->job_name }}
                                    </a>
                                    <div style="font-size: var(--fs-12); color: var(--text-secondary); margin-top: var(--space-1);">
                                        <span style="font-family: monospace;">{{ $child->job_reference }}</span>
                                        · {{ $child->store?->store_name }}
                                    </div>
                                </div>
                                <x-onyx.badge :tone="$child->job_status->tone()" variant="soft">{{ $child->job_status->label() }}</x-onyx.badge>
                            </div>
                        @endforeach
                    @endif
                </x-onyx.card>
            @endif

        </div>

        {{-- Right sidebar ─────────────────────────────────────────────── --}}
        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            {{-- Job details --}}
            <x-onyx.card variant="default" padding="lg">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Details</h2>
                <dl style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                    <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                        <dt style="color: var(--text-secondary);">Store</dt>
                        <dd>
                            @if ($job->store)
                                <a href="{{ route('stores.show', $job->store) }}"
                                    style="color: var(--bronze-600); text-decoration: none;">{{ $job->store->store_name }}</a>
                            @endif
                        </dd>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                        <dt style="color: var(--text-secondary);">Timezone</dt>
                        <dd style="font-family: monospace; font-size: var(--fs-13);">{{ $job->job_timezone }}</dd>
                    </div>
                    @if ($job->scheduled_date)
                        <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                            <dt style="color: var(--text-secondary);">Scheduled</dt>
                            <dd>{{ $job->scheduled_date->format('d M Y') }}
                                @if ($job->scheduled_time) at {{ substr((string)$job->scheduled_time, 0, 5) }} @endif
                            </dd>
                        </div>
                    @endif
                    <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                        <dt style="color: var(--text-secondary);">Early start</dt>
                        <dd>{{ $job->early_start_window->label() }}</dd>
                    </div>
                    @if ($job->parent)
                        <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                            <dt style="color: var(--text-secondary);">Parent campaign</dt>
                            <dd>
                                <a href="{{ route('jobs.show', $job->parent) }}"
                                    style="color: var(--bronze-600); text-decoration: none;">{{ $job->parent->job_name }}</a>
                            </dd>
                        </div>
                    @endif
                    @if ($job->client_name || $job->client_email)
                        <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                            <dt style="color: var(--text-secondary);">Client contact</dt>
                            <dd>
                                @if ($job->client_name) {{ $job->client_name }} @endif
                                @if ($job->client_email) <br><a href="mailto:{{ $job->client_email }}" style="color: var(--bronze-600);">{{ $job->client_email }}</a> @endif
                            </dd>
                        </div>
                    @endif
                    @if ($job->force_complete_reason)
                        <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                            <dt style="color: var(--text-secondary);">Force-complete reason</dt>
                            <dd style="color: var(--text-primary);">{{ $job->force_complete_reason }}</dd>
                        </div>
                    @endif
                    <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                        <dt style="color: var(--text-secondary);">Created</dt>
                        <dd>{{ $job->created_at?->format('d M Y') }}</dd>
                    </div>
                </dl>
            </x-onyx.card>

            {{-- Technicians --}}
            <x-onyx.card variant="default" padding="lg">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Technicians</h2>
                @if ($job->technicians->isEmpty())
                    <p style="font-size: var(--fs-13); color: var(--text-tertiary);">No technicians assigned.</p>
                @else
                    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                        @foreach ($job->technicians as $tech)
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-2);">
                                <div>
                                    <div style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $tech->name }}</div>
                                    <div style="font-size: var(--fs-12); color: var(--text-secondary);">{{ $tech->email }}</div>
                                </div>
                                @php
                                    $techStatus = \App\Enums\TechnicianJobStatus::tryFrom($tech->pivot->technician_status);
                                @endphp
                                @if ($techStatus)
                                    <x-onyx.badge :tone="$techStatus->tone()" variant="soft">{{ $techStatus->label() }}</x-onyx.badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-onyx.card>

            {{-- Cancel --}}
            @if (! $job->trashed() && ! $job->job_status->isTerminal())
                <x-onyx.card variant="outline" padding="lg">
                    <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--critical-600); margin-bottom: var(--space-2);">Cancel job</h2>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-bottom: var(--space-3);">
                        Cancelling removes the job from the active board. This action can be seen in the audit log.
                    </p>
                    <form method="POST" action="{{ route('jobs.destroy', $job) }}"
                        onsubmit="return confirm('Cancel this job? It will be removed from the active board.')">
                        @csrf @method('DELETE')
                        <x-onyx.button type="submit" variant="ghost"
                            style="color: var(--critical-600); border-color: var(--critical-300);">
                            Cancel job
                        </x-onyx.button>
                    </form>
                </x-onyx.card>
            @endif

        </div>

    </div>

</x-layouts.app>
