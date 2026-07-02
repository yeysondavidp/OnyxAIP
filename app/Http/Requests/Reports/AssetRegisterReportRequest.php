<?php

namespace App\Http\Requests\Reports;

use App\Enums\AssetType;
use App\Enums\AustralianState;
use App\Models\ReportExport;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssetRegisterReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ReportExport::class);
    }

    public function rules(): array
    {
        return [
            'client_id'   => ['required', 'integer', Rule::exists('clients', 'id')],
            'store_id'    => ['nullable', 'integer', Rule::exists('stores', 'id')],
            'state'       => ['nullable', Rule::enum(AustralianState::class)],
            'asset_type'  => ['nullable', Rule::enum(AssetType::class)],
            'report_kind' => ['required', Rule::in(['register_csv', 'register_pdf', 'status_summary_csv'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $clientId = $this->input('client_id');
            if ($clientId && ! $this->user()->can('generateForClient', [ReportExport::class, (int) $clientId])) {
                $v->errors()->add('client_id', 'You are not authorised to run reports for this client.');
            }

            $storeId = $this->input('store_id');
            if ($storeId && $clientId) {
                $store = Store::find($storeId);
                if ($store && $store->client_id !== (int) $clientId) {
                    $v->errors()->add('store_id', 'The selected store does not belong to this client.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'Select a client to run this report for.',
        ];
    }
}
