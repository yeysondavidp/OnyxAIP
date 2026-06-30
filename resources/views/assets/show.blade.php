<x-layouts.app title="{{ $asset->asset_name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('assets.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Asset Registry</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $asset->asset_name }}</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('assets.label', $asset) }}" variant="outline" size="sm">Download label</x-onyx.button>
        <x-onyx.button href="{{ route('assets.edit', $asset) }}" variant="outline" size="sm">Edit</x-onyx.button>
    </x-slot:headerActions>

    {{-- Header --}}
    <div style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-1); flex-wrap: wrap;">
            <h1 style="font-size: var(--fs-28); font-weight: var(--weight-bold); color: var(--text-primary);">{{ $asset->asset_name }}</h1>
            <x-onyx.badge :tone="$asset->asset_status->tone()" variant="soft">{{ $asset->asset_status->label() }}</x-onyx.badge>
        </div>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-4); font-size: var(--fs-14); color: var(--text-secondary);">
            <span style="font-family: monospace; font-size: var(--fs-13);">{{ $asset->asset_code }}</span>
            <span>·</span>
            <span>{{ $asset->asset_type->label() }}</span>
            @if ($asset->client)
                <span>·</span>
                <a href="{{ route('clients.show', $asset->client) }}" style="color: var(--bronze-600); text-decoration: none;">{{ $asset->client->client_name }}</a>
            @endif
            @if ($asset->store)
                <span>·</span>
                <a href="{{ route('stores.show', $asset->store) }}" style="color: var(--bronze-600); text-decoration: none;">{{ $asset->store->store_name }}</a>
            @endif
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 300px; gap: var(--space-6); align-items: start;">

        {{-- Left column --}}
        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            {{-- Base details --}}
            <x-onyx.card variant="default" padding="lg">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Asset details</h2>
                <dl style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                    <div style="display: flex; gap: var(--space-3);">
                        <dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Manufacturer</dt>
                        <dd style="color: var(--text-primary);">{{ $asset->manufacturer }}</dd>
                    </div>
                    <div style="display: flex; gap: var(--space-3);">
                        <dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Model</dt>
                        <dd style="color: var(--text-primary);">{{ $asset->model }}</dd>
                    </div>
                    @if ($asset->serial_number)
                        <div style="display: flex; gap: var(--space-3);">
                            <dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Serial number</dt>
                            <dd style="color: var(--text-primary); font-family: monospace; font-size: var(--fs-13);">{{ $asset->serial_number }}</dd>
                        </div>
                    @endif
                    @if ($asset->location_notes)
                        <div style="display: flex; gap: var(--space-3);">
                            <dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Location</dt>
                            <dd style="color: var(--text-primary);">{{ $asset->location_notes }}</dd>
                        </div>
                    @endif
                    @if ($asset->install_date)
                        <div style="display: flex; gap: var(--space-3);">
                            <dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Installed</dt>
                            <dd style="color: var(--text-primary);">{{ $asset->install_date->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if ($asset->purchase_date)
                        <div style="display: flex; gap: var(--space-3);">
                            <dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Purchased</dt>
                            <dd style="color: var(--text-primary);">{{ $asset->purchase_date->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if ($asset->warranty_expiry)
                        <div style="display: flex; gap: var(--space-3);">
                            <dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Warranty expiry</dt>
                            <dd style="color: var(--text-primary);">
                                {{ $asset->warranty_expiry->format('d M Y') }}
                                @if ($asset->warranty_expiry->isPast())
                                    <x-onyx.badge tone="critical" variant="soft" size="xs">Expired</x-onyx.badge>
                                @elseif ($asset->warranty_expiry->diffInDays(now()) <= 90)
                                    <x-onyx.badge tone="warning" variant="soft" size="xs">Expiring soon</x-onyx.badge>
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-onyx.card>

            {{-- Type-specific detail --}}
            @if ($asset->asset_type === \App\Enums\AssetType::DigitalScreen && $asset->screenDetail)
                @php $sd = $asset->screenDetail; @endphp
                <x-onyx.card variant="default" padding="lg">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Screen detail</h2>
                    <dl style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                        <div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Size</dt><dd style="color: var(--text-primary);">{{ $sd->screen_size_inches }}"</dd></div>
                        <div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Resolution</dt><dd style="color: var(--text-primary);">{{ $sd->resolution_width }} × {{ $sd->resolution_height }}</dd></div>
                        <div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Orientation</dt><dd style="color: var(--text-primary);">{{ $sd->orientation->label() }}</dd></div>
                        <div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Mount type</dt><dd style="color: var(--text-primary);">{{ $sd->mount_type }}</dd></div>
                        <div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Totem supplied by</dt><dd style="color: var(--text-primary);">{{ $sd->totem_supplied_by->label() }}</dd></div>
                    </dl>
                </x-onyx.card>

            @elseif ($asset->asset_type === \App\Enums\AssetType::MediaPlayer && $asset->playerDetail)
                @php $pd = $asset->playerDetail; @endphp
                <x-onyx.card variant="default" padding="lg">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Player detail</h2>
                    <dl style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                        <div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Player type</dt><dd style="color: var(--text-primary);">{{ $pd->player_type->label() }}</dd></div>
                        @if ($pd->cms_platform)<div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">CMS platform</dt><dd style="color: var(--text-primary);">{{ $pd->cms_platform }}</dd></div>@endif
                        @if ($pd->ip_address)<div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">IP address</dt><dd style="color: var(--text-primary); font-family: monospace; font-size: var(--fs-13);">{{ $pd->ip_address }}</dd></div>@endif
                        @if ($pd->mac_address)<div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">MAC address</dt><dd style="color: var(--text-primary); font-family: monospace; font-size: var(--fs-13);">{{ $pd->mac_address }}</dd></div>@endif
                        @if ($pd->firmware_version)<div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Firmware</dt><dd style="color: var(--text-primary);">{{ $pd->firmware_version }}</dd></div>@endif
                    </dl>
                </x-onyx.card>

            @elseif ($asset->asset_type === \App\Enums\AssetType::Lightbox && $asset->lightboxDetail)
                @php $ld = $asset->lightboxDetail; @endphp
                <x-onyx.card variant="default" padding="lg">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Lightbox detail</h2>
                    <dl style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                        <div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Dimensions</dt><dd style="color: var(--text-primary);">{{ $ld->lightbox_dimensions }}</dd></div>
                        <div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Light type</dt><dd style="color: var(--text-primary);">{{ $ld->light_type->label() }}</dd></div>
                        <div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Content change</dt><dd style="color: var(--text-primary);">{{ $ld->content_change_frequency->label() }}</dd></div>
                    </dl>
                </x-onyx.card>

            @elseif ($asset->asset_type === \App\Enums\AssetType::Infrastructure && $asset->infrastructureDetail)
                @php $infra = $asset->infrastructureDetail; @endphp
                <x-onyx.card variant="default" padding="lg">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Infrastructure detail</h2>
                    <dl style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                        @if ($infra->cable_type)<div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Cable type</dt><dd style="color: var(--text-primary);">{{ $infra->cable_type }}</dd></div>@endif
                        @if ($infra->length)<div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Length</dt><dd style="color: var(--text-primary);">{{ $infra->length }} m</dd></div>@endif
                    </dl>
                </x-onyx.card>

            @elseif ($asset->asset_type === \App\Enums\AssetType::WindowFixture && $asset->windowFixtureDetail)
                @php $wf = $asset->windowFixtureDetail; @endphp
                @if ($wf->fixture_dimensions)
                    <x-onyx.card variant="default" padding="lg">
                        <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Fixture detail</h2>
                        <dl style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                            <div style="display: flex; gap: var(--space-3);"><dt style="width: 150px; flex-shrink: 0; color: var(--text-secondary);">Dimensions</dt><dd style="color: var(--text-primary);">{{ $wf->fixture_dimensions }}</dd></div>
                        </dl>
                    </x-onyx.card>
                @endif
            @endif

            {{-- Service history placeholder --}}
            <x-onyx.card variant="default" padding="none">
                <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle);">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">Service History</h2>
                </div>
                <div style="padding: var(--space-10);">
                    <x-onyx.empty icon="clock" heading="No service history yet" body="Service records will appear here once jobs have been completed against this asset." size="sm" />
                </div>
            </x-onyx.card>

        </div>

        {{-- Right sidebar --}}
        <div style="display: flex; flex-direction: column; gap: var(--space-4);">

            {{-- QR Code (US-07.1) --}}
            <x-onyx.card variant="default" padding="md">
                <h3 style="font-size: var(--fs-13); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide); margin-bottom: var(--space-3);">QR Code</h3>
                @if ($asset->qr_code_path)
                    @php $qrUri = app(\App\Services\QrCodeService::class)->getDataUri($asset); @endphp
                    <div style="text-align: center;">
                        <img src="{{ $qrUri }}" alt="QR code for {{ $asset->asset_code }}" style="width: 160px; height: 160px; display: block; margin: 0 auto;">
                        <p style="font-family: monospace; font-size: var(--fs-12); color: var(--text-secondary); margin-top: var(--space-2);">{{ $asset->asset_code }}</p>
                        <div style="margin-top: var(--space-3);">
                            <x-onyx.button href="{{ route('assets.label', $asset) }}" variant="outline" size="xs" style="width: 100%;">Download label</x-onyx.button>
                        </div>
                    </div>
                @else
                    <p style="font-size: var(--fs-13); color: var(--text-muted);">QR code not yet generated.</p>
                @endif
            </x-onyx.card>

            <x-onyx.card variant="default" padding="md">
                <h3 style="font-size: var(--fs-13); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide); margin-bottom: var(--space-3);">Location</h3>
                @if ($asset->store)
                    <p style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">
                        <a href="{{ route('stores.show', $asset->store) }}" style="color: var(--bronze-600); text-decoration: none;">{{ $asset->store->store_name }}</a>
                    </p>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">{{ $asset->store->suburb }}, {{ $asset->store->state?->value }}</p>
                @endif
                @if ($asset->client)
                    <p style="font-size: var(--fs-13); color: var(--text-muted); margin-top: var(--space-2);">
                        <a href="{{ route('clients.show', $asset->client) }}" style="color: var(--text-muted); text-decoration: none;">{{ $asset->client->client_name }}</a>
                    </p>
                @endif
            </x-onyx.card>

            @if ($asset->notes)
                <x-onyx.card variant="default" padding="md">
                    <h3 style="font-size: var(--fs-13); font-weight: var(--weight-semibold); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide); margin-bottom: var(--space-3);">Internal Notes</h3>
                    <p style="font-size: var(--fs-14); color: var(--text-secondary); white-space: pre-wrap;">{{ $asset->notes }}</p>
                </x-onyx.card>
            @endif

        </div>
    </div>

</x-layouts.app>
