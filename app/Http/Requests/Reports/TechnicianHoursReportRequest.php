<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\Reports\Concerns\ValidatesDateRange;
use App\Models\ReportExport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TechnicianHoursReportRequest extends FormRequest
{
    use ValidatesDateRange;

    public function authorize(): bool
    {
        return $this->user()->can('generateTechnicianHours', ReportExport::class);
    }

    public function rules(): array
    {
        return [
            'technician_profile_id' => ['nullable', 'integer', Rule::exists('technician_profiles', 'id')],
            'date_from'             => ['required', 'date_format:Y-m-d'],
            'date_to'               => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->validateDateRange($v));
    }
}
