<?php

namespace App\Http\Controllers;

use App\Services\Registration\MockCivilSystems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $civilRecord = $user->role === 'citizen' && $user->mock_person_id && MockCivilSystems::enabled()
            ? DB::table('mock_civil_persons')->where('id', $user->mock_person_id)
                ->first(['name', 'national_id', 'birth_date', 'birth_place', 'father_name', 'mother_name', 'phone'])
            : null;

        return response()->view('profile.edit', compact('user', 'civilRecord'))->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->role === 'citizen', 403, 'بيانات الملف الشخصي للمواطن للعرض فقط.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9]{7,15}$/'],
            'role' => ['missing'], 'is_active' => ['missing'],
            'id' => ['missing'], 'user_id' => ['missing'], 'username' => ['missing'],
            'national_id' => ['missing'], 'email_verified_at' => ['missing'], 'password' => ['missing'],
            'registration_status' => ['missing'], 'identity_path' => ['missing'], 'phone_verified_at' => ['missing'],
            'account_activated_at' => ['missing'], 'identity_verified_at' => ['missing'],
        ], [
            'name.required' => 'الاسم الكامل مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'أدخل بريدًا إلكترونيًا صحيحًا.',
            'email.unique' => 'هذا البريد الإلكتروني مسجّل لحساب آخر.',
            'phone.regex' => 'أدخل رقم هاتف صحيحًا من 7 إلى 15 رقمًا.',
            '*.missing' => 'لا يمكن تعديل هذا الحقل من الملف الشخصي.',
        ]);
        $user->fill(Arr::only($data, ['name', 'email', 'phone']));
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        if ($user->isDirty('phone')) {
            $user->phone_verified_at = null;
        }
        $user->save();

        return redirect()->route('profile.edit')->with('status', 'تم تحديث بياناتك بنجاح.');
    }
}
