<?php

namespace App\Http\Requests;

use App\Enums\EarlyStartWindow;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', PlatformSetting::class);
    }

    public function rules(): array
    {
        return [
            'sla_at_risk_threshold_pct'  => ['required', 'integer', 'between:1,99'],
            'default_early_start_window' => ['required', Rule::enum(EarlyStartWindow::class)],
            'warranty_alert_days'        => ['required', 'array', 'min:1'],
            'warranty_alert_days.*'      => ['integer', Rule::in([30, 60, 90])],
            'technician_reminder_hours'  => ['required', 'integer', 'between:1,168'],
        ];
    }

    public function messages(): array
    {
        return [
            'warranty_alert_days.min' => 'Select at least one warranty-alert lead time.',
        ];
    }
}
