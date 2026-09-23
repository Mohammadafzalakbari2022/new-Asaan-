<?php

namespace Cartxis\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryStaffController extends Controller
{
    /**
     * List delivery staff accounts.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $staff = User::where('role', 'delivery')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $staff->getCollection()->transform(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
            ];
        });

        return Inertia::render('Admin/Sales/DeliveryStaff/Index', [
            'staff' => $staff,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Create a delivery staff account.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'role' => 'delivery',
            'is_active' => $validated['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);

        return back()->with('success', "Delivery person {$user->name} created.");
    }

    /**
     * Update a delivery staff account.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== 'delivery') {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', 'Delivery person updated.');
    }

    /**
     * Reset a delivery staff password.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== 'delivery') {
            abort(404);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated.');
    }

    /**
     * Delete a delivery staff account.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->role !== 'delivery') {
            abort(404);
        }

        $user->delete();

        return back()->with('success', 'Delivery person removed.');
    }
}