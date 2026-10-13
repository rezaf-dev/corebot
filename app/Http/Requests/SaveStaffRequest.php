<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $staff = $this->route('staff');

        return $actor->hasWorkspaceAccess() && ($actor->isSuperAdmin() || ($actor->isTenantAdmin() && (! $staff || ($staff->role === 'agent' && $staff->tenant_id === $actor->tenant_id))));
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()->isSuperAdmin()) {
            $this->merge(['role' => 'agent', 'tenant_id' => $this->user()->tenant_id]);
        } elseif ($this->input('role') === 'super_admin') {
            $this->merge(['tenant_id' => null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($this->route('staff')?->id)],
            'role' => ['required', Rule::in(['super_admin', 'tenant_admin', 'agent'])],
            'tenant_id' => [Rule::requiredIf($this->input('role') !== 'super_admin'), 'nullable', 'integer', 'exists:tenants,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
