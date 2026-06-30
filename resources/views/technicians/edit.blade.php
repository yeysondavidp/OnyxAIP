<x-layouts.app title="Edit — {{ $profile->name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('technicians.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Technicians</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('technicians.show', $profile) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">{{ $profile->name }}</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Edit</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 720px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Edit Technician</h1>
        </div>

        @php
            $currentSpecs  = old('specialty_categories', $profile->specialty_categories ?? []);
            $currentCerts  = old('certifications', $profile->certifications ?? []);
            $currentClients = old('preferred_client_ids', array_map('strval', $profile->preferred_client_ids ?? []));
        @endphp

        <form method="POST" action="{{ route('technicians.update', $profile) }}" novalidate
            x-data="{
                specialties: {{ json_encode($currentSpecs) }},
                certInput: '',
                certs: {{ json_encode($currentCerts) }},
                addCert() {
                    const val = this.certInput.trim();
                    if (val && !this.certs.includes(val)) this.certs.push(val);
                    this.certInput = '';
                },
                removeCert(c) { this.certs = this.certs.filter(x => x !== c); },
                toggleSpec(v) {
                    const idx = this.specialties.indexOf(v);
                    if (idx === -1) this.specialties.push(v);
                    else this.specialties.splice(idx, 1);
                },
            }">
            @csrf @method('PUT')

            <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Contact details</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input name="name" label="Full name"
                                :value="old('name', $profile->name)"
                                :error="$errors->first('name')" required />
                            <x-onyx.input name="phone" label="Phone" type="tel"
                                :value="old('phone', $profile->phone)"
                                :error="$errors->first('phone')" />
                        </div>
                        <x-onyx.input name="email" label="Email address" type="email"
                            :value="old('email', $profile->email)"
                            :error="$errors->first('email')" required />
                    </div>
                </x-onyx.card>

                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Specialties</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-2); margin-top: var(--space-4);">
                        @foreach ($specialties as $spec)
                            <label style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); cursor: pointer; min-height: 44px;"
                                :style="specialties.includes('{{ $spec->value }}') ? 'border-color: var(--bronze-500); background: var(--bronze-50);' : ''">
                                <input type="checkbox" name="specialty_categories[]" value="{{ $spec->value }}"
                                    :checked="specialties.includes('{{ $spec->value }}')"
                                    @change="toggleSpec('{{ $spec->value }}')"
                                    style="accent-color: var(--bronze-600); flex-shrink: 0;">
                                <span style="font-size: var(--fs-14); color: var(--text-primary);">{{ $spec->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </x-onyx.card>

                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Certifications</x-onyx.eyebrow>
                    <div style="margin-top: var(--space-4);">
                        <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-3);">
                            <input type="text" x-model="certInput" @keydown.enter.prevent="addCert()"
                                placeholder="Type a certification and press Enter"
                                style="flex: 1; height: 40px; padding: 0 var(--space-3); border: 1px solid var(--border-default); border-radius: var(--radius-md); font-size: var(--fs-14);">
                            <x-onyx.button type="button" variant="outline" size="sm" @click="addCert()">Add</x-onyx.button>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                            <template x-for="cert in certs" :key="cert">
                                <input type="hidden" name="certifications[]" :value="cert">
                                <span style="display: inline-flex; align-items: center; gap: var(--space-1); background: var(--bronze-100); color: var(--bronze-800); font-size: var(--fs-13); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm);">
                                    <span x-text="cert"></span>
                                    <button type="button" @click="removeCert(cert)" style="background: none; border: none; cursor: pointer; color: var(--bronze-600); font-size: 14px; line-height: 1; padding: 0 2px;">×</button>
                                </span>
                            </template>
                        </div>
                    </div>
                </x-onyx.card>

                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Preferred clients &amp; asset competency</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">
                        <div>
                            <label style="display: block; font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary); margin-bottom: var(--space-2);">Preferred clients</label>
                            <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                                @foreach ($clients as $client)
                                    <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); cursor: pointer; min-height: 44px;">
                                        <input type="checkbox" name="preferred_client_ids[]" value="{{ $client->id }}"
                                            @checked(in_array((string) $client->id, $currentClients))
                                            style="accent-color: var(--bronze-600);">
                                        {{ $client->client_name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <x-onyx.textarea name="asset_competency" label="Asset competency (optional)"
                            :value="old('asset_competency', $profile->asset_competency)"
                            :error="$errors->first('asset_competency')" rows="2" />
                    </div>
                </x-onyx.card>

                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Account link (optional)</x-onyx.eyebrow>
                    <div style="margin-top: var(--space-4);">
                        <x-onyx.select name="user_id" label="Technician account" :error="$errors->first('user_id')">
                            <option value="">— Guest profile (no account) —</option>
                            @foreach ($techUsers as $u)
                                <option value="{{ $u->id }}" @selected(old('user_id', $profile->user_id) == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </x-onyx.select>
                    </div>
                </x-onyx.card>

                <div style="display: flex; gap: var(--space-3);">
                    <x-onyx.button type="submit" variant="primary">Save changes</x-onyx.button>
                    <x-onyx.button href="{{ route('technicians.show', $profile) }}" variant="ghost">Cancel</x-onyx.button>
                </div>

            </div>
        </form>
    </div>

</x-layouts.app>
