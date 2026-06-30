<?php

namespace App\Http\Requests;

use App\Enums\EarlyStartWindow;
use App\Enums\JobType;
use App\Models\Asset;
use App\Models\ServiceJob;
use App\Models\TechnicianProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ServiceJob $job */
        $job = $this->route('job');

        return $this->user()->can('update', $job);
    }

    public function rules(): array
    {
        /** @var ServiceJob $job */
        $job = $this->route('job');

        return [
            'job_reference' => ['required', 'string', 'max:80',
                Rule::unique('service_jobs', 'job_reference')->ignore($job->id),
            ],
            'job_name'                 => ['required', 'string', 'max:255'],
            'job_description'          => ['required', 'string', 'max:5000'],
            'job_type'                 => ['required', Rule::enum(JobType::class)],
            'scheduled_date'           => ['nullable', 'date_format:Y-m-d'],
            'scheduled_time'           => ['nullable', 'date_format:H:i', 'required_with:scheduled_date'],
            'early_start_window'       => ['required', Rule::enum(EarlyStartWindow::class)],
            'client_email'             => ['nullable', 'email', 'max:255'],
            'client_name'              => ['nullable', 'string', 'max:255'],
            'asset_ids'                => ['nullable', 'array'],
            'asset_ids.*'              => ['integer', Rule::exists('assets', 'id')],
            'technician_profile_ids'   => ['nullable', 'array'],
            'technician_profile_ids.*' => ['integer', Rule::exists('technician_profiles', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validateAssetsInStore($v);
            $this->validateTechniciansActive($v);
        });
    }

    private function validateAssetsInStore(Validator $v): void
    {
        $assetIds = $this->input('asset_ids', []);
        /** @var ServiceJob $job */
        $job = $this->route('job');

        if (empty($assetIds)) {
            return;
        }

        $validCount = Asset::whereIn('id', $assetIds)
            ->where('store_id', $job->store_id)
            ->count();

        if ($validCount !== count($assetIds)) {
            $v->errors()->add('asset_ids', 'One or more selected assets do not belong to this job\'s store.');
        }
    }

    private function validateTechniciansActive(Validator $v): void
    {
        $profileIds = $this->input('technician_profile_ids', []);
        if (empty($profileIds)) {
            return;
        }

        $validCount = TechnicianProfile::whereIn('id', $profileIds)
            ->where('is_active', true)
            ->count();

        if ($validCount !== count($profileIds)) {
            $v->errors()->add('technician_profile_ids', 'One or more selected technicians are not active or do not exist.');
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
