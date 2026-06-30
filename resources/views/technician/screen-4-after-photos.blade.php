<x-layouts.technician title="After Photos — {{ $jobModel->job_name }}">

    @php
        $signedParams = request()->only(['token', 'technician_profile_id', 'expires', 'signature']);
        $uploadUrl    = route('technician.job.photos', array_merge(['job' => $jobModel->id], $signedParams));
        $completeUrl  = route('technician.job.complete', array_merge(['job' => $jobModel->id], $signedParams));
        $briefUrl     = route('technician.job.brief', array_merge(['job' => $jobModel->id], $signedParams));
        $csrfToken    = csrf_token();
        $assetIds     = $jobModel->assets->pluck('id')->toArray();
    @endphp

    <div class="tech-shell"
        x-data="{
            photos: [],
            outcomes: {{ json_encode($jobModel->assets->map(fn ($a) => ['asset_id' => $a->id, 'name' => $a->asset_name, 'status' => '', 'notes' => ''])->values()) }},
            completionNotes: '',
            gpsLat: null,
            gpsLng: null,
            gpsStatus: 'pending',
            submitting: false,

            addPhoto(event) {
                const files = Array.from(event.target.files);
                files.forEach(file => {
                    const id      = crypto.randomUUID();
                    const preview = URL.createObjectURL(file);
                    const entry   = { id, file, preview, uploadId: id, status: 'queued', serverId: null, error: null };
                    this.photos.push(entry);
                    this.uploadOne(entry);
                });
                event.target.value = '';
            },

            removePhoto(id) {
                this.photos = this.photos.filter(p => p.id !== id);
            },

            uploadOne(entry) {
                const maxRetries = 3;
                let attempt = 0;
                const doUpload = () => {
                    entry.status = attempt > 0 ? 'retrying' : 'uploading';
                    const fd = new FormData();
                    fd.append('photo', entry.file);
                    fd.append('type', 'after');
                    fd.append('client_upload_id', entry.uploadId);
                    fd.append('_token', '{{ $csrfToken }}');
                    fetch('{{ $uploadUrl }}', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => { if (d.success) { entry.status = 'done'; entry.serverId = d.id; } else { throw new Error(d.error ?? 'Upload failed.'); } })
                        .catch(err => {
                            if (++attempt < maxRetries) { setTimeout(doUpload, Math.pow(2, attempt) * 1000); }
                            else { entry.status = 'failed'; entry.error = err.message; }
                        });
                };
                doUpload();
            },

            retryPhoto(id) {
                const e = this.photos.find(p => p.id === id);
                if (e) { e.error = null; this.uploadOne(e); }
            },

            get doneCount() { return this.photos.filter(p => p.status === 'done').length; },
            get uploadsComplete() { return this.doneCount > 0 && this.photos.every(p => p.status !== 'uploading' && p.status !== 'retrying'); },

            captureGps() {
                if (!navigator.geolocation) { this.gpsStatus = 'failed'; return; }
                navigator.geolocation.getCurrentPosition(
                    pos => { this.gpsLat = pos.coords.latitude; this.gpsLng = pos.coords.longitude; this.gpsStatus = 'granted'; },
                    ()  => { this.gpsStatus = 'denied'; },
                    { timeout: 10000 }
                );
            },

            submit() {
                if (!this.uploadsComplete || this.submitting) return;
                this.submitting = true;
                const fd = new FormData();
                fd.append('_token', '{{ $csrfToken }}');
                fd.append('gps_status', this.gpsStatus === 'pending' ? 'skipped' : this.gpsStatus);
                if (this.gpsLat !== null) fd.append('gps_lat', this.gpsLat);
                if (this.gpsLng !== null) fd.append('gps_lng', this.gpsLng);
                if (this.completionNotes) fd.append('completion_notes', this.completionNotes);
                this.outcomes.forEach((o, i) => {
                    fd.append('outcomes[' + i + '][asset_id]', o.asset_id);
                    fd.append('outcomes[' + i + '][status]', o.status || 'active');
                    if (o.notes) fd.append('outcomes[' + i + '][notes]', o.notes);
                });
                // Use form submit to preserve signed URL redirect chain
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ $completeUrl }}';
                for (const [k, v] of fd.entries()) {
                    const inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = k; inp.value = v;
                    form.appendChild(inp);
                }
                document.body.appendChild(form);
                form.submit();
            },
        }"
        x-init="captureGps()">

        {{-- Header --}}
        <div class="tech-header">
            <p style="font-size: var(--fs-12); color: var(--onyx-400); margin-bottom: var(--space-1);">Step 3 of 4</p>
            <h1 style="font-size: var(--fs-18); font-weight: var(--weight-semibold);">After photos &amp; outcomes</h1>
        </div>

        <div style="flex: 1; padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-5); padding-bottom: 100px;">

            @if ($errors->any())
                <div style="background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.3); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); font-size: var(--fs-13); color: #dc2626;">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- GPS status --}}
            <div x-show="gpsStatus === 'denied' || gpsStatus === 'failed'"
                style="background: rgba(234,179,8,.1); border: 1px solid rgba(234,179,8,.3); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); font-size: var(--fs-13); color: #854d0e;">
                Location access was not available. We'll record your submission without GPS — that's okay.
            </div>

            {{-- After photos --}}
            <div>
                <p style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-2);">After photos <span style="color: var(--critical-600);">*</span></p>
                <label style="display: flex; align-items: center; justify-content: center; gap: var(--space-3); height: 52px; border: 2px dashed var(--border-default); border-radius: var(--radius-lg); font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-secondary); cursor: pointer; margin-bottom: var(--space-3);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Take after photo
                    <input type="file" accept="image/*" capture="environment" multiple @change="addPhoto($event)" style="position:absolute;opacity:0;width:1px;height:1px;">
                </label>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-2);">
                    <template x-for="p in photos" :key="p.id">
                        <div style="position: relative; aspect-ratio: 1; border-radius: var(--radius-md); overflow: hidden; background: var(--surface-tertiary);">
                            <img :src="p.preview" style="width:100%;height:100%;object-fit:cover;">
                            <template x-if="p.status !== 'done'">
                                <div style="position:absolute;inset:0;background:rgba(0,0,0,.5);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;">
                                    <span style="font-size:var(--fs-11);color:#fff;" x-text="p.status === 'retrying' ? 'Retrying…' : (p.status === 'failed' ? (p.error ?? 'Failed') : 'Uploading…')"></span>
                                    <template x-if="p.status === 'failed'">
                                        <button type="button" @click="retryPhoto(p.id)" style="font-size:var(--fs-11);color:#fff;background:rgba(255,255,255,.2);border:none;border-radius:4px;padding:2px 8px;cursor:pointer;min-height:32px;">Retry</button>
                                    </template>
                                </div>
                            </template>
                            <template x-if="p.status === 'done'">
                                <button type="button" @click="removePhoto(p.id)" style="position:absolute;top:4px;right:4px;width:28px;height:28px;background:rgba(0,0,0,.6);border:none;border-radius:50%;color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;">×</button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Per-asset outcomes --}}
            @if ($jobModel->assets->isNotEmpty())
                <div>
                    <p style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-3);">Asset outcomes</p>
                    <template x-for="(outcome, i) in outcomes" :key="outcome.asset_id">
                        <div style="background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: var(--space-4); margin-bottom: var(--space-3);">
                            <p style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-2);" x-text="outcome.name"></p>
                            <div style="display: flex; flex-wrap: wrap; gap: var(--space-2); margin-bottom: var(--space-3);">
                                @foreach ($postStatuses as $ps)
                                    <button type="button"
                                        @click="outcomes[i].status = '{{ $ps->value }}'"
                                        :style="outcomes[i].status === '{{ $ps->value }}' ? 'background:var(--bronze-100);border:1px solid var(--bronze-400);color:var(--bronze-800);font-weight:600;' : 'background:var(--surface-tertiary);border:1px solid var(--border-default);color:var(--text-primary);'"
                                        style="min-height:44px;padding:var(--space-2) var(--space-3);border-radius:var(--radius-md);font-size:var(--fs-13);cursor:pointer;">
                                        {{ $ps->label() }}
                                    </button>
                                @endforeach
                            </div>
                            <textarea x-model="outcomes[i].notes" maxlength="500" rows="2"
                                placeholder="Notes (optional, max 500 chars)"
                                style="width:100%;border:1px solid var(--border-default);border-radius:var(--radius-md);padding:var(--space-2) var(--space-3);font-size:var(--fs-13);resize:none;background:var(--surface-primary);"></textarea>
                            <p style="font-size:var(--fs-11);color:var(--text-tertiary);text-align:right;" x-text="(outcomes[i].notes?.length ?? 0) + '/500'"></p>
                        </div>
                    </template>
                </div>
            @endif

            {{-- Completion notes --}}
            <div>
                <label style="display:block;font-size:var(--fs-14);font-weight:var(--weight-semibold);color:var(--text-primary);margin-bottom:var(--space-2);">
                    Completion notes (optional)
                </label>
                <textarea x-model="completionNotes" maxlength="1000" rows="3"
                    placeholder="Any general notes about this visit…"
                    style="width:100%;border:1px solid var(--border-default);border-radius:var(--radius-md);padding:var(--space-3);font-size:var(--fs-14);resize:none;background:var(--surface-primary);"></textarea>
                <p style="font-size:var(--fs-11);color:var(--text-tertiary);text-align:right;" x-text="(completionNotes?.length ?? 0) + '/1000'"></p>
            </div>

        </div>

        {{-- Sticky bar --}}
        <div class="tech-sticky-bar" style="display:flex;flex-direction:column;gap:var(--space-3);">
            <button type="button" @click="submit()"
                :disabled="!uploadsComplete || submitting"
                :style="(!uploadsComplete || submitting) ? 'opacity:.4;cursor:not-allowed;' : ''"
                style="width:100%;height:56px;background:var(--bronze-700);color:#fff;font-size:var(--fs-16);font-weight:var(--weight-bold);border:none;border-radius:var(--radius-lg);cursor:pointer;">
                <span x-text="submitting ? 'Submitting…' : 'Submit job'"></span>
            </button>
            <p x-show="!uploadsComplete && photos.length === 0" style="text-align:center;font-size:var(--fs-13);color:var(--text-tertiary);">
                Add at least one after-photo before submitting.
            </p>
            <a href="{{ $briefUrl }}" style="display:flex;align-items:center;justify-content:center;height:44px;color:var(--text-secondary);font-size:var(--fs-14);text-decoration:none;border:1px solid var(--border-default);border-radius:var(--radius-md);">
                Back to brief
            </a>
        </div>

    </div>

</x-layouts.technician>
