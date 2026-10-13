<?php

namespace App\Http\Requests;

class StoreAgentRequest extends IssueAgentTokenRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name'      => ['required', 'string', 'max:100'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'name.required'    => 'Give the agent a name, for example "Claude (agent)".',
            'client_id.exists' => 'That client no longer exists. Choose another or leave it on all clients.',
        ]);
    }
}
