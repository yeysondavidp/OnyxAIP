<?php

namespace App\Http\Requests\Reports;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\ReportExport;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceHistoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ReportExport::class);
    }

    public function rules(): array
    {
        return [
            'report_kind' => ['required', Rule::in(['asset_pdf', 'store_pdf', 'store_csv'])],
            'asset_id'    => ['required_if:report_kind,asset_pdf', 'nullable', 'integer', Rule::exists('assets', 'id')],
            'store_id'    => ['required_if:report_kind,store_pdf,store_csv', 'nullable', 'integer', Rule::exists('stores', 'id')],
            'asset_type'  => ['nullable', Rule::enum(AssetType::class)],
            'date_from'   => ['nullable', 'date_format:Y-m-d'],
            'date_to'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $assetId = $this->input('asset_id');
            if ($this->input('report_kind') === 'asset_pdf' && $assetId) {
                $asset = Asset::find($assetId);
                if ($asset && ! $this->user()->can('view', $asset)) {
                    $v->errors()->add('asset_id', 'You are not authorised to view this asset.');
                }
            }

            $storeId = $this->input('store_id');
            if (in_array($this->input('report_kind'), ['store_pdf', 'store_csv'], true) && $storeId) {
                $store = Store::find($storeId);
                if ($store && ! $this->user()->can('view', $store)) {
                    $v->errors()->add('store_id', 'You are not authorised to view this store.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
