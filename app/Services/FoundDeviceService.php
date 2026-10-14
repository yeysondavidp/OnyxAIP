<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Jobs\WriteAuditLog;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Scopes\VerifiedAssetScope;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Devices a technician finds on site (EPIC-21): registered as pending_verification by the
 * technician (US-21.1), then verified, rejected or merged by a PM (US-21.3).
 */
class FoundDeviceService
{
    /** Asset-code type segment, matching the register's convention (PAN-PLY-0001, PAN-SCR-0001). */
    private const TYPE_CODES = [
        'media_player'   => 'PLY',
        'digital_screen' => 'SCR',
        'infrastructure' => 'INF',
        'lightbox'       => 'LBX',
        'window_fixture' => 'FIX',
    ];

    /** Fields the technician may give that have no base column until the PM verifies. */
    public const DISCOVERY_KEYS = ['mac_address', 'screen_size_inches', 'orientation'];

    public function __construct(
        private readonly JobAssetService $jobAssets,
        private readonly AssetTransitionService $transitions,
        private readonly JobAttachmentService $attachments,
    ) {}

    /** Another device of the same client with this serial or MAC — verified or still pending. */
    public function possibleDuplicate(int $clientId, ?string $serial, ?string $mac): ?Asset
    {
        if (($serial ?? '') === '' && ($mac ?? '') === '') {
            return null;
        }

        return Asset::query()
            ->withoutGlobalScope(VerifiedAssetScope::class)
            ->with('store:id,store_name')
            ->where('client_id', $clientId)
            ->where(fn ($q) => $q
                ->when(($serial ?? '') !== '', fn ($w) => $w->orWhere('serial_number', $serial))
                ->when(($mac ?? '') !== '', fn ($w) => $w
                    ->orWhereHas('playerDetail', fn ($p) => $p->where('mac_address', $mac))
                    ->orWhere('discovery_data->mac_address', $mac)))
            ->first();
    }

    /**
     * Register a device the technician is looking at, pending PM verification (US-21.1).
     *
     * @param  array<string, mixed>  $data  validated technician input
     */
    public function register(ServiceJob $job, TechnicianProfile $technician, array $data, UploadedFile $photo): Asset
    {
        $type   = AssetType::from((string) $data['asset_type']);
        $client = Client::findOrFail($job->client_id);
        $path   = (string) Storage::disk('local')->putFile('asset-discovery/'.$job->id, $photo);

        $location = trim((string) ($data['location_notes'] ?? ''));
        $name     = trim((string) ($data['asset_name'] ?? '')) ?: $type->label().($location !== '' ? ' — '.$location : '');

        $asset = $this->withFreshCode($client, $type, fn (string $code) => DB::transaction(function () use ($code, $job, $type, $data, $name, $location, $technician, $path): Asset {
            $asset = new Asset([
                'asset_code'     => $code,
                'asset_type'     => $type->value,
                'client_id'      => $job->client_id,
                'store_id'       => $job->store_id,
                'asset_name'     => mb_substr($name, 0, 255),
                'manufacturer'   => trim((string) ($data['manufacturer'] ?? '')) ?: 'Unknown',
                'model'          => trim((string) ($data['model'] ?? '')) ?: 'Unknown',
                'serial_number'  => $data['serial_number'] ?? null,
                'asset_status'   => AssetStatus::PendingVerification->value,
                'location_notes' => $location !== '' ? $location : null,
            ]);
            $asset->forceFill([
                'found_on_job_id'                => $job->id,
                'found_by_technician_profile_id' => $technician->id,
                'discovery_data'                 => array_filter(array_intersect_key($data, array_flip(self::DISCOVERY_KEYS)), fn ($v) => $v !== null && $v !== ''),
                'discovery_photo_path'           => $path,
            ])->save();

            $job->assets()->attach($asset->id, ['status_before' => AssetStatus::PendingVerification->value]);

            return $asset;
        }));

        return $asset;
    }

    /**
     * PM verification (US-21.3): save the full, form-validated details, then move the device out
     * of pending to the status the PM chose.
     *
     * @param  array<string, mixed>  $validated  validated with UpdateAssetRequest rules
     */
    public function verify(Asset $device, User $pm, array $validated, AssetStatus $status): void
    {
        DB::transaction(function () use ($device, $pm, $validated, $status): void {
            [$base, $detail]      = Asset::splitValidated($validated);
            $base['asset_status'] = AssetStatus::PendingVerification->value;

            $device->updateWithDetail($base, $detail);
            $device->forceFill(['verified_at' => now(), 'verified_by_user_id' => $pm->id])->save();

            $job = $device->found_on_job_id === null ? '' : ' on '.ServiceJob::whereKey($device->found_on_job_id)->value('job_reference');
            $this->transitions->transitionTo($device, $status, actor: $pm, reason: 'Verified device found on site'.$job);
        });
    }

    /** PM rejects a found device: it leaves the register entirely (US-21.3). */
    public function reject(Asset $device, User $pm, string $reason): void
    {
        $this->audit($pm, 'device.rejected', $device, ['reason' => $reason]);
        $this->forget($device);
    }

    /**
     * PM says the found device is one already registered: that asset joins the job (when it is at
     * the job's store), the photo becomes a job attachment, and the duplicate is removed.
     */
    public function merge(Asset $device, Asset $into, User $pm): void
    {
        DB::transaction(function () use ($device, $into, $pm): void {
            $job = $device->found_on_job_id === null ? null : ServiceJob::find($device->found_on_job_id);

            if ($job !== null && $into->store_id === $job->store_id) {
                $this->jobAssets->attach($job, [$into->id]);
            }

            if ($job !== null && $device->discovery_photo_path !== null && Storage::disk('local')->exists($device->discovery_photo_path)) {
                $this->attachments->store($job, new File(Storage::disk('local')->path($device->discovery_photo_path)), 'Found on site — '.$into->asset_code.'.'.pathinfo($device->discovery_photo_path, PATHINFO_EXTENSION));
            }

            $this->audit($pm, 'device.merged', $device, ['into' => $into->asset_code]);
            $this->forget($device);
        });
    }

    private function forget(Asset $device): void
    {
        foreach (array_filter([$device->discovery_photo_path, $device->getAttribute('qr_code_path')]) as $path) {
            Storage::disk('local')->delete((string) $path);
        }

        $device->delete();
    }

    /**
     * Next free code for the client and type, retried once if another device took it meanwhile
     * (the unique index on asset_code is the real guard).
     *
     * @param  callable(string): Asset  $create
     */
    private function withFreshCode(Client $client, AssetType $type, callable $create): Asset
    {
        try {
            return $create($this->nextCode($client, $type));
        } catch (UniqueConstraintViolationException) {
            return $create($this->nextCode($client, $type));
        }
    }

    private function nextCode(Client $client, AssetType $type): string
    {
        $prefix = $client->client_code.'-'.self::TYPE_CODES[$type->value].'-';

        $highest = Asset::withoutGlobalScopes()
            ->where('asset_code', 'like', $prefix.'%')
            ->pluck('asset_code')
            ->map(fn (string $code) => preg_match('/-(\d+)$/', $code, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return $prefix.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $after */
    private function audit(User $pm, string $action, Asset $device, array $after): void
    {
        WriteAuditLog::dispatch(
            userId: $pm->id,
            userRole: $pm->role->value,
            action: $action,
            auditableType: Asset::class,
            auditableId: $device->id,
            before: ['asset_code' => $device->asset_code, 'serial_number' => $device->serial_number],
            after: $after,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );
    }
}
