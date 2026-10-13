<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->input('action') === 'claim' ? 'claim' : 'update', $this->route('conversation'));
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['claim', 'assign', 'resolve', 'reopen', 'return_to_ai'])],
            'assigned_to' => ['required_if:action,assign', 'nullable', 'integer'],
        ];
    }
}
