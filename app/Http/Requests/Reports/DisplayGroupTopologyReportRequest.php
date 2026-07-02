<?php

namespace App\Http\Requests\Reports;

use App\Models\ReportExport;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DisplayGroupTopologyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ReportExport::class);
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $storeId = $this->input('store_id');
            if (! $storeId) {
                return;
            }

            $store = Store::find($storeId);
            if ($store && ! $this->user()->can('view', $store)) {
                $v->errors()->add('store_id', 'You are not authorised to view this store.');
            }
        });
    }
}
