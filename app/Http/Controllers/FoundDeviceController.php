<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\ContentChangeFrequency;
use App\Enums\LightType;
use App\Enums\Orientation;
use App\Enums\PlayerType;
use App\Enums\TotemSuppliedBy;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Services\FoundDeviceService;
use App\Support\FormRequestRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** PM verification queue for devices technicians found on site (US-21.3, EPIC-21). */
class FoundDeviceController extends Controller
{
    /** Statuses a PM can give a verified device. */
    private const VERIFIED_STATUSES = [AssetStatus::Active, AssetStatus::Faulty, AssetStatus::Offline, AssetStatus::Decommissioned];

    public function __construct(private readonly FoundDeviceService $devices) {}

    public function index(): View
    {
        $this->authorize('viewAny', Asset::class);

        $devices = Asset::pendingDevices()
            ->with(['store:id,store_name,store_code', 'foundOnJob:id,job_reference', 'foundBy:id,name'])
            ->latest()
            ->get();

        return view('found-devices.index', compact('devices'));
    }

    public function show(Asset $device): View
    {
        $this->authorize('update', $device);

        $device->load(['store:id,store_name,store_code', 'foundOnJob:id,job_reference', 'foundBy:id,name']);

        return view('found-devices.show', [
            'device'                   => $device,
            'found'                    => $device->discovery_data ?? [],
            'statuses'                 => self::VERIFIED_STATUSES,
            'orientations'             => Orientation::cases(),
            'totemSuppliedByOptions'   => TotemSuppliedBy::cases(),
            'playerTypes'              => PlayerType::cases(),
            'lightTypes'               => LightType::cases(),
            'contentChangeFrequencies' => ContentChangeFrequency::cases(),
        ]);
    }

    public function photo(Asset $device): StreamedResponse
    {
        $this->authorize('view', $device);
        abort_if($device->discovery_photo_path === null || ! Storage::disk('local')->exists($device->discovery_photo_path), 404);

        return Storage::disk('local')->response($device->discovery_photo_path);
    }

    /** @throws ValidationException */
    public function verify(Request $request, Asset $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $status = AssetStatus::from((string) $request->validate([
            'status' => ['required', Rule::in(array_map(fn (AssetStatus $s) => $s->value, self::VERIFIED_STATUSES))],
        ])['status']);

        // Same rules as the asset form; type, client and store come from the device, never the input.
        $validated = FormRequestRules::validate(UpdateAssetRequest::class, array_merge(
            $request->except(['_token', '_method', 'status', 'wifi_password', 'admin_password']),
            [
                'asset_type'   => $device->asset_type->value,
                'client_id'    => $device->client_id,
                'store_id'     => $device->store_id,
                'asset_status' => $status->value,
            ],
        ), ['asset' => $device]);

        $this->devices->verify($device, $request->user(), $validated, $status);

        return redirect()->route('assets.show', $device->id)->with('success', "{$device->asset_code} is verified and now part of the register.");
    }

    public function reject(Request $request, Asset $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $reason = (string) $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], ['reason.required' => 'Say why this device is being rejected.'])['reason'];

        $this->devices->reject($device, $request->user(), $reason);

        return redirect()->route('found-devices.index')->with('success', "{$device->asset_code} was rejected and removed.");
    }

    public function merge(Request $request, Asset $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $code = (string) $request->validate([
            'into_asset_code' => ['required', 'string', 'max:40'],
        ], ['into_asset_code.required' => 'Enter the code of the device it already is, e.g. PAN-PLY-0001.'])['into_asset_code'];

        $into = Asset::where('client_id', $device->client_id)->where('asset_code', $code)->first();

        if ($into === null) {
            throw ValidationException::withMessages(['into_asset_code' => "No registered device {$code} for this client."]);
        }

        $this->authorize('update', $into);
        $this->devices->merge($device, $into, $request->user());

        return redirect()->route('found-devices.index')->with('success', "Merged into {$into->asset_code}.");
    }
}
