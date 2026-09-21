<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bot_public_key' => ['required', 'string'],
            'conversation_id' => ['required', 'integer'],
            'conversation_token' => ['required', 'string', 'size:64'],
            'chat_message_id' => ['required', 'integer'],
            'vote' => ['required', 'in:up,down'],
            'reason' => ['nullable', 'string', 'max:100'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
