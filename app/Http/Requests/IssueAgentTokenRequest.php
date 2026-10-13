<?php

namespace App\Http\Requests;

use App\Enums\AgentAbility;
use App\Models\User;
use App\Services\AgentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueAgentTokenRequest extends FormRequest
{
    public const EXPIRY_OPTIONS = [7, 30, 60, AgentAccessService::MAX_EXPIRY_DAYS];

    public function authorize(): bool
    {
        return $this->user()->can('manageAgents', User::class);
    }

    public function rules(): array
    {
        return [
            'abilities'       => ['required', 'array', 'min:1'],
            'abilities.*'     => ['distinct', Rule::enum(AgentAbility::class)],
            'expires_in_days' => ['required', 'integer', Rule::in(self::EXPIRY_OPTIONS)],
        ];
    }

    public function messages(): array
    {
        return [
            'abilities.required' => 'Choose at least one thing this agent is allowed to do.',
            'abilities.min'      => 'Choose at least one thing this agent is allowed to do.',
            'expires_in_days.in' => 'Choose one of the listed expiry periods.',
        ];
    }

    /** @return list<AgentAbility> */
    public function abilities(): array
    {
        return array_values(array_map(fn (string $v) => AgentAbility::from($v), $this->validated('abilities')));
    }
}
