<?php

namespace App\Http\Requests;

use App\Enums\EarlyStartWindow;
use App\Enums\EmailTemplateSlot;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /** PM-facing notification types a PM can toggle — the technician slots are not preferences. */
    public const PM_NOTIFICATION_SLOTS = [
        EmailTemplateSlot::PmJobStatusChanged,
        EmailTemplateSlot::PmAssetStatusChanged,
        EmailTemplateSlot::PmNewFaultReported,
        EmailTemplateSlot::PmSlaWarning,
        EmailTemplateSlot::PmSlaBreached,
        EmailTemplateSlot::PmWarrantyExpiryApproaching,
    ];

    public function authorize(): bool
    {
        return $this->user()->can('update', PlatformSetting::class);
    }

    public function rules(): array
    {
        $pmSlotValues = array_map(fn (EmailTemplateSlot $s) => $s->value, self::PM_NOTIFICATION_SLOTS);

        return [
            'sla_at_risk_threshold_pct'    => ['required', 'integer', 'between:1,99'],
            'default_early_start_window'   => ['required', Rule::enum(EarlyStartWindow::class)],
            'warranty_alert_days'          => ['required', 'array', 'min:1'],
            'warranty_alert_days.*'        => ['integer', Rule::in([30, 60, 90])],
            'technician_reminder_hours'    => ['required', 'integer', 'between:1,168'],
            'link_expiry_warning_hours'    => ['required', 'integer', 'between:1,72'],
            'enabled_notification_types'   => ['sometimes', 'array'],
            'enabled_notification_types.*' => ['string', Rule::in($pmSlotValues)],
        ];
    }

    public function messages(): array
    {
        return [
            'warranty_alert_days.min' => 'Select at least one warranty-alert lead time.',
        ];
    }
}
