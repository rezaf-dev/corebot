<?php

namespace App\Http\Requests;

use App\Models\ChatConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkConversationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() && $this->user()->hasWorkspaceAccess();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $conversationExists = Rule::exists(ChatConversation::class, 'id');
        if (! $this->user()->isSuperAdmin()) {
            $conversationExists->where('tenant_id', $this->user()->tenant_id);
        }

        return [
            'action' => ['required', Rule::in(['resolve', 'delete'])],
            'conversation_ids' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'conversation_ids.*' => ['required', 'integer', 'min:1', 'distinct', $conversationExists],
            'filters' => ['sometimes', 'array:tab,search,bot_id,tenant_id'],
            'filters.tab' => ['sometimes', Rule::in(['needs_reply', 'mine', 'unassigned', 'all', 'resolved', 'leads', 'escalated'])],
            'filters.search' => ['nullable', 'string', 'max:200'],
            'filters.bot_id' => ['nullable', 'integer'],
            'filters.tenant_id' => ['nullable', 'integer'],
        ];
    }
}
