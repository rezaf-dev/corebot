<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStaffRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\StaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/Staff/Index', [
            'staff' => User::query()->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('tenant_id', $request->user()->tenant_id)->where('role', 'agent'))->with('tenant:id,name')->orderBy('name')->paginate(30, ['id', 'name', 'email', 'tenant_id', 'role', 'is_active']),
            'tenants' => $request->user()->isSuperAdmin() ? Tenant::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function store(SaveStaffRequest $request): RedirectResponse
    {
        $staff = User::query()->create([...$request->validated(), 'password' => Str::random(64)]);

        return $this->sendSetup($staff);
    }

    public function update(SaveStaffRequest $request, User $staff, StaffAccess $access): RedirectResponse
    {
        $access->ensureCanManage($request->user(), $staff);
        $access->update($staff, $request->validated());

        return back()->with('success', 'Staff account updated.');
    }

    public function setup(Request $request, User $staff, StaffAccess $access): RedirectResponse
    {
        $access->ensureCanManage($request->user(), $staff);
        abort_unless($staff->is_active, 422);

        return $this->sendSetup($staff);
    }

    private function sendSetup(User $staff): RedirectResponse
    {
        try {
            $status = Password::sendResetLink(['email' => $staff->email]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Account saved, but the password setup email could not be sent. Try sending it again.');
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Account saved. Password setup email sent.')
            : back()->with('error', 'Account saved. '.trans($status));
    }
}
