<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9]{7,15}$/'],
            'role' => ['missing'], 'is_active' => ['missing'],
            'id' => ['missing'], 'user_id' => ['missing'], 'username' => ['missing'],
            'national_id' => ['missing'], 'email_verified_at' => ['missing'], 'password' => ['missing'],
        ], [
            'name.required' => 'الاسم الكامل مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'أدخل بريدًا إلكترونيًا صحيحًا.',
            'email.unique' => 'هذا البريد الإلكتروني مسجّل لحساب آخر.',
            'phone.regex' => 'أدخل رقم هاتف صحيحًا من 7 إلى 15 رقمًا.',
            '*.missing' => 'لا يمكن تعديل هذا الحقل من الملف الشخصي.',
        ]);
        $user->fill(\Illuminate\Support\Arr::only($data, ['name', 'email', 'phone']));
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return redirect()->route('profile.edit')->with('status', 'تم تحديث بياناتك بنجاح.');
    }
}
