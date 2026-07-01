<?php

namespace App\Http\Requests;

use App\Enums\MonitoringCoverage;
use App\Models\SlaProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSlaProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SlaProfile::class);
    }

    public function rules(): array
    {
        return [
            'name'                           => ['required', 'string', 'max:255'],
            'acknowledgement_hours'          => ['required', 'integer', 'min:1', 'max:8760'],
            'onsite_response_metro_hours'    => ['required', 'integer', 'min:1', 'max:8760'],
            'onsite_response_regional_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'resolution_hours'               => ['required', 'integer', 'min:1', 'max:8760'],
            'monitoring_coverage'            => ['required', Rule::enum(MonitoringCoverage::class)],
            'is_active'                      => ['sometimes', 'boolean'],
        ];
    }
}
