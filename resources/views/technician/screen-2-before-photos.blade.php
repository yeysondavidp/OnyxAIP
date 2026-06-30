<x-layouts.technician title="Before Photos — {{ $jobModel->job_name }}">

    @php
        $signedParams = request()->only(['token', 'technician_profile_id', 'expires', 'signature']);
        $uploadUrl    = route('technician.job.photos', array_merge(['job' => $jobModel->id], $signedParams));
        $briefUrl     = route('technician.job.brief',  array_merge(['job' => $jobModel->id], $signedParams));
        $cancelUrl    = route('technician.job.cancel-start', array_merge(['job' => $jobModel->id], $signedParams));
        $csrfToken    = csrf_token();
    @endphp

    <div class="tech-shell"
        x-data="{
            photos: [],          /* [{id, file, preview, uploadId, status, serverId}] */
            uploading: false,

            addPhoto(event) {
                const files = Array.from(event.target.files);
                files.forEach(file => {
                    const id       = crypto.randomUUID();
                    const preview  = URL.createObjectURL(file);
                    const entry    = { id, file, preview, uploadId: id, status: 'queued', serverId: null, error: null };
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
                    fd.append('type', 'before');
                    fd.append('client_upload_id', entry.uploadId);
                    fd.append('_token', '{{ $csrfToken }}');

                    fetch('{{ $uploadUrl }}', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                entry.status   = 'done';
                                entry.serverId = data.id;
                            } else {
                                throw new Error(data.error ?? 'Upload failed.');
                            }
                        })
                        .catch(err => {
                            if (++attempt < maxRetries) {
                                setTimeout(doUpload, Math.pow(2, attempt) * 1000);
                            } else {
                                entry.status = 'failed';
                                entry.error  = err.message;
                            }
                        });
                };

                doUpload();
            },

            retryPhoto(id) {
                const entry = this.photos.find(p => p.id === id);
                if (entry) { entry.error = null; this.uploadOne(entry); }
            },

            get doneCount() {
                return this.photos.filter(p => p.status === 'done').length;
            },

            get canContinue() {
                return this.doneCount >= 1 && this.photos.every(p => p.status !== 'uploading' && p.status !== 'retrying');
            },
        }">

        {{-- Header --}}
        <div class="tech-header">
            <p style="font-size: var(--fs-12); color: var(--onyx-400); margin-bottom: var(--space-1);">Step 1 of 4</p>
            <h1 style="font-size: var(--fs-18); font-weight: var(--weight-semibold);">Before photos</h1>
        </div>

        <div style="flex: 1; padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-4);">

            <p style="font-size: var(--fs-14); color: var(--text-secondary);">
                Photograph the work area before you begin. At least one photo is required.
            </p>

            {{-- Photo capture button --}}
            <label style="display: flex; align-items: center; justify-content: center; gap: var(--space-3); height: 52px; border: 2px dashed var(--border-default); border-radius: var(--radius-lg); font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-secondary); cursor: pointer;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                Take photo
                <input type="file" accept="image/*" capture="environment" multiple @change="addPhoto($event)" style="position:absolute;opacity:0;width:1px;height:1px;">
            </label>

            {{-- Photo thumbnails --}}
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-3);">
                <template x-for="p in photos" :key="p.id">
                    <div style="position: relative; aspect-ratio: 1; border-radius: var(--radius-md); overflow: hidden; background: var(--surface-tertiary);">
                        <img :src="p.preview" style="width: 100%; height: 100%; object-fit: cover;">

                        {{-- Status overlay --}}
                        <template x-if="p.status !== 'done'">
                            <div style="position:absolute;inset:0;background:rgba(0,0,0,.5);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;">
                                <template x-if="p.status === 'uploading' || p.status === 'retrying'">
                                    <span style="font-size: var(--fs-11); color: #fff;" x-text="p.status === 'retrying' ? 'Retrying…' : 'Uploading…'"></span>
                                </template>
                                <template x-if="p.status === 'failed'">
                                    <span style="font-size: var(--fs-11); color: #fca5a5; text-align:center; padding: 0 4px;" x-text="p.error ?? 'Failed'"></span>
                                </template>
                                <template x-if="p.status === 'failed'">
                                    <button type="button" @click="retryPhoto(p.id)"
                                        style="font-size:var(--fs-11);color:#fff;background:rgba(255,255,255,.2);border:none;border-radius:4px;padding:2px 8px;cursor:pointer;min-height:32px;">
                                        Retry
                                    </button>
                                </template>
                            </div>
                        </template>

                        {{-- Remove button (done only) --}}
                        <template x-if="p.status === 'done'">
                            <button type="button" @click="removePhoto(p.id)"
                                style="position:absolute;top:4px;right:4px;width:28px;height:28px;background:rgba(0,0,0,.6);border:none;border-radius:50%;color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                                ×
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Min-1 gate message --}}
            <p x-show="photos.length > 0 && doneCount === 0" style="font-size: var(--fs-13); color: #ca8a04; text-align: center;">
                Waiting for upload to complete…
            </p>

        </div>

        {{-- Sticky bar: Cancel + Continue --}}
        <div class="tech-sticky-bar" style="display: flex; flex-direction: column; gap: var(--space-3);">
            <button type="button" x-show="canContinue"
                @click="window.location='{{ $briefUrl }}'"
                style="width: 100%; height: 56px; background: var(--bronze-700); color: #fff; font-size: var(--fs-16); font-weight: var(--weight-bold); border: none; border-radius: var(--radius-lg); cursor: pointer;">
                Continue to brief
            </button>
            <p x-show="!canContinue && photos.length === 0"
                style="text-align: center; font-size: var(--fs-13); color: var(--text-tertiary);">
                Add at least one before-photo to continue.
            </p>

            <form method="POST" action="{{ $cancelUrl }}" x-show="canContinue || photos.length === 0">
                @csrf
                <button type="submit"
                    style="width: 100%; height: 44px; background: transparent; color: var(--text-secondary); font-size: var(--fs-14); border: 1px solid var(--border-default); border-radius: var(--radius-md); cursor: pointer;">
                    Cancel — return to job overview
                </button>
            </form>
        </div>

    </div>

</x-layouts.technician>
