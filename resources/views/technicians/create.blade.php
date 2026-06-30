<x-layouts.app title="Add Technician">

    <x-slot:breadcrumbs>
        <a href="{{ route('technicians.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Technicians</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Add technician</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 720px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Add Technician</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Add a new field technician or installer to the directory.</p>
        </div>

        <form method="POST" action="{{ route('technicians.store') }}" novalidate
            x-data="{
                specialties: {{ json_encode(old('specialty_categories', [])) }},
                certInput: '',
                certs: {{ json_encode(old('certifications', [])) }},
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
            @csrf

            <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Contact details</x-onyx.eyebrow>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-4);">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <x-onyx.input name="name" label="Full name" :value="old('name')"
                                :error="$errors->first('name')" required />
                            <x-onyx.input name="phone" label="Phone" type="tel" :value="old('phone')"
                                :error="$errors->first('phone')" />
                        </div>
                        <x-onyx.input name="email" label="Email address" type="email" :value="old('email')"
                            :error="$errors->first('email')" required />
                    </div>
                </x-onyx.card>

                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Specialties</x-onyx.eyebrow>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">Select all that apply.</p>
                    @error('specialty_categories')
                        <x-onyx.alert tone="critical" style="margin-top: var(--space-2);">{{ $message }}</x-onyx.alert>
                    @enderror
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
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">e.g. White Card, EWP, Working at Heights.</p>
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
                                            @checked(in_array($client->id, old('preferred_client_ids', [])))
                                            style="accent-color: var(--bronze-600);">
                                        {{ $client->client_name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <x-onyx.textarea name="asset_competency" label="Asset competency (optional)"
                            :value="old('asset_competency')" :error="$errors->first('asset_competency')"
                            rows="2" placeholder="e.g. Samsung commercial displays, Beat MIB 02" />
                    </div>
                </x-onyx.card>

                <x-onyx.card variant="default" padding="xl">
                    <x-onyx.eyebrow>Account link (optional)</x-onyx.eyebrow>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">
                        Link this profile to an existing ONYX technician account. Leave blank for a guest-only profile.
                    </p>
                    <div style="margin-top: var(--space-4);">
                        <x-onyx.select name="user_id" label="Technician account" :error="$errors->first('user_id')">
                            <option value="">— Guest profile (no account) —</option>
                            @foreach ($techUsers as $u)
                                <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </x-onyx.select>
                    </div>
                </x-onyx.card>

                <div style="display: flex; gap: var(--space-3);">
                    <x-onyx.button type="submit" variant="primary">Add technician</x-onyx.button>
                    <x-onyx.button href="{{ route('technicians.index') }}" variant="ghost">Cancel</x-onyx.button>
                </div>

            </div>
        </form>
    </div>

</x-layouts.app>
