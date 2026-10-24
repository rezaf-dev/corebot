<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        return back()->with('success', 'Tenant updated.');
    }

    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        DB::transaction(function () use ($tenant): void {
            $tenant->users()->where('role', '!=', 'super_admin')->update(['is_active' => false]);
            $tenant->bots()->each(fn ($bot) => $bot->delete());
            $tenant->delete();
        });

        Storage::deleteDirectory("tenants/{$tenant->id}");

        return back()->with('success', 'Tenant deleted.');
    }

    public function index(): Response
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        return Inertia::render('Tenants/Index', [
            'tenants' => Tenant::query()->withCount(['users', 'bots'])->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'role' => 'tenant_admin',
        ]);

        return back()->with('success', 'Tenant created.');
    }
}
