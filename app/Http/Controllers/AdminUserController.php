<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '';
        $users = User::query()->select(['id', 'name', 'username', 'email', 'role', 'is_active'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%');
            }))->latest('id')->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(User::ROLES)], 'is_active' => ['required', 'boolean'],
        ], ['role.in' => 'الدور المحدد غير صالح.', 'is_active.boolean' => 'حالة الحساب غير صالحة.']);
        DB::transaction(function () use ($request, $user, $data) {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->role === 'admin' && $actor->is_active, 403);
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->is($actor) && ($data['role'] !== 'admin' || ! $request->boolean('is_active'))) {
                throw ValidationException::withMessages(['role' => 'لا يمكنك تعطيل حسابك الإداري أو إزالة دور المدير منه.']);
            }
            $user->forceFill(['role' => $data['role'], 'is_active' => $request->boolean('is_active')])->save();
        }, 3);

        return redirect()->route('admin.users.index')->with('status', 'تم تحديث دور المستخدم وحالة حسابه.');
    }
}
