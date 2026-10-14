<x-layouts.app :title="'Verify '.$device->asset_code">

    <x-slot:breadcrumbs>
        <a href="{{ route('found-devices.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Devices to verify</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $device->asset_code }}</span>
    </x-slot:breadcrumbs>

    @php
        $type  = $device->asset_type->value;
        $value = fn (string $field, mixed $fallback = null) => old($field, $found[$field] ?? $fallback);
    @endphp

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Verify {{ $device->asset_name }}</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">
            {{ $device->asset_type->label() }} found at {{ $device->store?->store_name }}
            by {{ $device->foundBy?->name ?? 'a technician' }}{{ $device->foundOnJob ? ' on '.$device->foundOnJob->job_reference : '' }},
            {{ $device->created_at?->format('d/m/Y') }}. Check the photo, correct anything wrong, then verify.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-6); align-items: start;">

        {{-- What the technician saw --}}
        <x-onyx.card variant="default" padding="lg">
            <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); margin-bottom: var(--space-3);">Photo from site</h2>
            @if ($device->discovery_photo_path)
                <a href="{{ route('found-devices.photo', $device) }}" target="_blank" rel="noopener">
                    <img src="{{ route('found-devices.photo', $device) }}" alt="Photo of {{ $device->asset_name }} taken on site"
                        style="width: 100%; border-radius: var(--radius-md); border: 1px solid var(--border-subtle);">
                </a>
            @else
                <p style="color: var(--text-tertiary);">No photo.</p>
            @endif
            <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-3);">
                Technician entered: {{ $device->manufacturer }} {{ $device->model }}
                @if ($device->serial_number) · serial {{ $device->serial_number }} @endif
                @foreach ($found as $key => $item) · {{ str_replace('_', ' ', $key) }} {{ $item }} @endforeach
            </p>
        </x-onyx.card>

        {{-- Verify --}}
        <x-onyx.card variant="default" padding="lg">
            <form method="POST" action="{{ route('found-devices.verify', $device) }}" novalidate>
                @csrf
                @method('PUT')
                <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                    <x-onyx.input name="asset_code" label="Asset code" maxlength="40" :value="old('asset_code', $device->asset_code)" :error="$errors->first('asset_code')" required />
                    <x-onyx.input name="asset_name" label="Name" maxlength="255" :value="old('asset_name', $device->asset_name)" :error="$errors->first('asset_name')" required />
                    <x-onyx.input name="manufacturer" label="Manufacturer" :value="old('manufacturer', $device->manufacturer)" :error="$errors->first('manufacturer')" required />
                    <x-onyx.input name="model" label="Model" :value="old('model', $device->model)" :error="$errors->first('model')" required />
                    <x-onyx.input name="serial_number" label="Serial number" :value="old('serial_number', $device->serial_number)" :error="$errors->first('serial_number')" />
                    <x-onyx.input name="location_notes" label="Where it is in the store" maxlength="500" :value="old('location_notes', $device->location_notes)" :error="$errors->first('location_notes')" />

                    @if ($type === 'digital_screen')
                        <x-onyx.input name="screen_size_inches" label="Screen size (inches)" type="number" step="0.1" :value="$value('screen_size_inches')" :error="$errors->first('screen_size_inches')" required />
                        <x-onyx.input name="resolution_width" label="Resolution width (px)" type="number" :value="old('resolution_width')" :error="$errors->first('resolution_width')" required />
                        <x-onyx.input name="resolution_height" label="Resolution height (px)" type="number" :value="old('resolution_height')" :error="$errors->first('resolution_height')" required />
                        <x-onyx.select name="orientation" label="Orientation" :error="$errors->first('orientation')" required>
                            @foreach ($orientations as $o)<option value="{{ $o->value }}" @selected($value('orientation') === $o->value)>{{ ucfirst($o->value) }}</option>@endforeach
                        </x-onyx.select>
                        <x-onyx.input name="mount_type" label="Mount type" :value="old('mount_type')" :error="$errors->first('mount_type')" helper="e.g. Floor Totem, Wall Mount" required />
                        <x-onyx.select name="totem_supplied_by" label="Supplied by" :error="$errors->first('totem_supplied_by')" required>
                            @foreach ($totemSuppliedByOptions as $t)<option value="{{ $t->value }}" @selected(old('totem_supplied_by') === $t->value)>{{ ucfirst($t->value) }}</option>@endforeach
                        </x-onyx.select>
                    @elseif ($type === 'media_player')
                        <x-onyx.select name="player_type" label="Player type" :error="$errors->first('player_type')" required>
                            @foreach ($playerTypes as $p)<option value="{{ $p->value }}" @selected(old('player_type') === $p->value)>{{ str_replace('_', ' ', ucfirst($p->value)) }}</option>@endforeach
                        </x-onyx.select>
                        <x-onyx.input name="mac_address" label="MAC address" :value="$value('mac_address')" :error="$errors->first('mac_address')" />
                        <x-onyx.input name="cms_platform" label="CMS platform" :value="old('cms_platform')" :error="$errors->first('cms_platform')" />
                    @elseif ($type === 'lightbox')
                        <x-onyx.input name="lightbox_dimensions" label="Dimensions (W x H x D mm)" :value="old('lightbox_dimensions')" :error="$errors->first('lightbox_dimensions')" required />
                        <x-onyx.select name="light_type" label="Light type" :error="$errors->first('light_type')" required>
                            @foreach ($lightTypes as $l)<option value="{{ $l->value }}" @selected(old('light_type') === $l->value)>{{ ucfirst($l->value) }}</option>@endforeach
                        </x-onyx.select>
                        <x-onyx.select name="content_change_frequency" label="Content changes" :error="$errors->first('content_change_frequency')" required>
                            @foreach ($contentChangeFrequencies as $c)<option value="{{ $c->value }}" @selected(old('content_change_frequency') === $c->value)>{{ str_replace('_', ' ', ucfirst($c->value)) }}</option>@endforeach
                        </x-onyx.select>
                    @elseif ($type === 'infrastructure')
                        <x-onyx.input name="mac_address" label="MAC address" :value="$value('mac_address')" :error="$errors->first('mac_address')" />
                        <x-onyx.input name="cable_type" label="Cable type" :value="old('cable_type')" :error="$errors->first('cable_type')" />
                        <p style="font-size: var(--fs-12); color: var(--text-secondary);">Router passwords are added on the asset page after verifying.</p>
                    @else
                        <x-onyx.input name="fixture_dimensions" label="Dimensions" :value="old('fixture_dimensions')" :error="$errors->first('fixture_dimensions')" />
                    @endif

                    <x-onyx.select name="status" label="Status once verified" :error="$errors->first('status')" required>
                        @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(old('status', 'active') === $s->value)>{{ $s->label() }}</option>@endforeach
                    </x-onyx.select>
                </div>
                <div style="display: flex; justify-content: flex-end; margin-top: var(--space-6);">
                    <x-onyx.button type="submit" variant="accent">Verify and add to register</x-onyx.button>
                </div>
            </form>
        </x-onyx.card>
    </div>

    {{-- Not a new device --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-6); margin-top: var(--space-6);">
        <x-onyx.card variant="default" padding="lg">
            <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); margin-bottom: var(--space-2);">It's a device we already have</h2>
            <form method="POST" action="{{ route('found-devices.merge', $device) }}" novalidate style="display: flex; flex-direction: column; gap: var(--space-3);">
                @csrf
                <x-onyx.input name="into_asset_code" label="Existing asset code" placeholder="PAN-PLY-0001" :value="old('into_asset_code')" :error="$errors->first('into_asset_code')" helper="The existing device joins the visit and this photo is kept on the job." />
                <x-onyx.button type="submit" variant="outline">Merge into existing device</x-onyx.button>
            </form>
        </x-onyx.card>
        <x-onyx.card variant="default" padding="lg">
            <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); margin-bottom: var(--space-2);">Not a device we track</h2>
            <form method="POST" action="{{ route('found-devices.reject', $device) }}" novalidate style="display: flex; flex-direction: column; gap: var(--space-3);">
                @csrf
                <x-onyx.input name="reason" label="Why reject it?" maxlength="500" :value="old('reason')" :error="$errors->first('reason')" helper="e.g. Not ONYX equipment. It is removed from the register." />
                <x-onyx.button type="submit" variant="danger-outline">Reject and remove</x-onyx.button>
            </form>
        </x-onyx.card>
    </div>

</x-layouts.app>
