<?php

namespace App\Http\Requests\Reports;

use App\Enums\Country;
use App\Models\ReportExport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OpenFaultsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ReportExport::class);
    }

    public function rules(): array
    {
        // State/region values span every country (US-03.5) — this filter has
        // no accompanying Country field, so it accepts any known value.
        $stateValues = collect(Country::cases())->flatMap->regions()->map->value->all();

        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'state'     => ['nullable', Rule::in($stateValues)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $clientId = $this->input('client_id');
            if ($clientId && ! $this->user()->can('generateForClient', [ReportExport::class, (int) $clientId])) {
                $v->errors()->add('client_id', 'You are not authorised to run reports for this client.');
            }
        });
    }
}
