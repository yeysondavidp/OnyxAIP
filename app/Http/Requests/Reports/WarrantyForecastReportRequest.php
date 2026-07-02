<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\Reports\Concerns\ValidatesDateRange;
use App\Models\ReportExport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WarrantyForecastReportRequest extends FormRequest
{
    use ValidatesDateRange;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ReportExport::class);
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to'   => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validateDateRange($v);

            $clientId = $this->input('client_id');
            if ($clientId && ! $this->user()->can('generateForClient', [ReportExport::class, (int) $clientId])) {
                $v->errors()->add('client_id', 'You are not authorised to run reports for this client.');
            }
        });
    }
}
