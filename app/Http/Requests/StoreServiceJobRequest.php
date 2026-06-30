<?php

namespace App\Http\Requests;

use App\Enums\EarlyStartWindow;
use App\Enums\JobType;
use App\Models\Asset;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ServiceJob::class);
    }

    public function rules(): array
    {
        return [
            'store_id'           => ['required', 'integer', Rule::exists('stores', 'id')],
            'job_reference'      => ['required', 'string', 'max:80', Rule::unique('service_jobs', 'job_reference')],
            'job_name'           => ['required', 'string', 'max:255'],
            'job_description'    => ['required', 'string', 'max:5000'],
            'job_type'           => ['required', Rule::enum(JobType::class)],
            'scheduled_date'     => ['nullable', 'date_format:Y-m-d'],
            'scheduled_time'     => ['nullable', 'date_format:H:i', 'required_with:scheduled_date'],
            'early_start_window' => ['required', Rule::enum(EarlyStartWindow::class)],
            'parent_job_id'      => ['nullable', 'integer', Rule::exists('service_jobs', 'id')],
            'client_email'       => ['nullable', 'email', 'max:255'],
            'client_name'        => ['nullable', 'string', 'max:255'],
            // Affected assets — all must belong to the chosen store (validated in withValidator)
            'asset_ids'   => ['nullable', 'array'],
            'asset_ids.*' => ['integer', Rule::exists('assets', 'id')],
            // Assigned technicians
            'technician_ids'   => ['nullable', 'array'],
            'technician_ids.*' => ['integer', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validateStoreClientScope($v);
            $this->validateAssetsInStore($v);
            $this->validateTechniciansRole($v);
            $this->validateParentHierarchy($v);
        });
    }

    /**
     * Ensure the chosen store belongs to the PM's permitted clients and
     * derive client_id server-side (never trusted from the request).
     */
    private function validateStoreClientScope(Validator $v): void
    {
        $storeId = $this->input('store_id');
        if (! $storeId) {
            return;
        }

        $store = Store::find($storeId);
        if (! $store) {
            return; // handled by exists rule above
        }

        $permitted = $this->user()->permittedClientIds();
        if ($permitted !== null && ! in_array($store->client_id, $permitted, strict: true)) {
            $v->errors()->add('store_id', 'You are not authorised to create a job for this store.');
        }
    }

    /**
     * Ensure every submitted asset belongs to the chosen store (US-08.2).
     */
    private function validateAssetsInStore(Validator $v): void
    {
        $assetIds = $this->input('asset_ids', []);
        $storeId  = $this->input('store_id');

        if (empty($assetIds) || ! $storeId) {
            return;
        }

        $validCount = Asset::whereIn('id', $assetIds)
            ->where('store_id', $storeId)
            ->count();

        if ($validCount !== count($assetIds)) {
            $v->errors()->add('asset_ids', 'One or more selected assets do not belong to the chosen store.');
        }
    }

    /**
     * Ensure assigned users hold the technician role (US-08.4).
     */
    private function validateTechniciansRole(Validator $v): void
    {
        $techIds = $this->input('technician_ids', []);
        if (empty($techIds)) {
            return;
        }

        $validCount = User::whereIn('id', $techIds)
            ->where('role', 'technician')
            ->count();

        if ($validCount !== count($techIds)) {
            $v->errors()->add('technician_ids', 'One or more assigned users are not technicians.');
        }
    }

    /**
     * Enforce hierarchy rules for parent_job_id (US-08.5).
     */
    private function validateParentHierarchy(Validator $v): void
    {
        $parentId = $this->input('parent_job_id');
        if (! $parentId) {
            return;
        }

        $parent = ServiceJob::find($parentId);
        if (! $parent) {
            return;
        }

        // Parent must be in same client scope
        $permitted = $this->user()->permittedClientIds();
        if ($permitted !== null && ! in_array($parent->client_id, $permitted, strict: true)) {
            $v->errors()->add('parent_job_id', 'Parent job is not in your permitted scope.');

            return;
        }

        // Cross-client children are rejected (single-client rule)
        $storeId = $this->input('store_id');
        if ($storeId) {
            $store = Store::find($storeId);
            if ($store && $store->client_id !== $parent->client_id) {
                $v->errors()->add('parent_job_id', 'A sub-job must belong to the same client as its parent campaign.');

                return;
            }
        }

        // Max depth — Level 2 cannot have children
        if ($parent->job_level >= 2) {
            $v->errors()->add('parent_job_id', 'A remediation job cannot have child jobs (maximum depth is 2).');

            return;
        }

        // Level 1 (sub-job) can only have one remediation child
        if ($parent->job_level === 1 && $parent->hasRemediation()) {
            $v->errors()->add('parent_job_id', 'This sub-job already has a remediation child. Only one remediation is permitted per sub-job.');
        }
    }

    public function messages(): array
    {
        return [
            'job_reference.unique'         => 'Job reference is already in use.',
            'job_type.enum'                => 'Please select a valid job type.',
            'early_start_window.enum'      => 'Please select a valid early-start window.',
            'scheduled_time.required_with' => 'Scheduled time is required when a date is set.',
        ];
    }
}
