<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Oversight\AuditTrail;
use App\Services\Registration\CitizenIdentifiers;
use App\Services\Registration\MockCivilSystems;
use App\Services\Registration\MockIdentityVerification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MockActivationController extends Controller
{
    public function correct(Request $r)
    {
        abort_unless(MockCivilSystems::enabled() && $r->user()->identity_path === 'no_identity' && $r->user()->activation_source === 'linked_local_mock', 403);
        $data = $r->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^\s*[\p{Arabic}\p{L}]+(?:\s+[\p{Arabic}\p{L}]+){3,}\s*$/u'],
            'birth_date' => 'required|date|before_or_equal:today',
            'birth_place' => 'required|string|max:150',
            'father_name' => ['required', 'string', 'max:100', 'regex:/^\s*[\p{Arabic}\p{L}]+(?:\s+[\p{Arabic}\p{L}]+){3,}\s*$/u'],
            'mother_name' => ['required', 'string', 'max:100', 'regex:/^\s*[\p{Arabic}\p{L}]+(?:\s+[\p{Arabic}\p{L}]+){3,}\s*$/u'],
            'father_national_id' => ['required', 'regex:/^[0-9]{5,20}$/D'],
            'mother_national_id' => ['required', 'regex:/^[0-9]{5,20}$/D'],
            'family_reference' => 'nullable|string|max:100',
            'additional_information' => 'nullable|string|max:1000',
        ], [
            '*.required' => 'هذا الحقل مطلوب.',
            'name.regex' => 'يجب إدخال الاسم الرباعي كاملًا (أربعة مقاطع على الأقل).',
            'father_name.regex' => 'يجب إدخال الاسم الرباعي للأب كاملًا (أربعة مقاطع على الأقل).',
            'mother_name.regex' => 'يجب إدخال الاسم الرباعي للأم كاملًا (أربعة مقاطع على الأقل).',
            'father_national_id.required' => 'الرقم الوطني للأب مطلوب.',
            'mother_national_id.required' => 'الرقم الوطني للأم مطلوب.',
            'father_national_id.regex' => 'أدخل الرقم الوطني للأب من 5 إلى 20 رقمًا.',
            'mother_national_id.regex' => 'أدخل الرقم الوطني للأم من 5 إلى 20 رقمًا.',
        ]);
        try {
            app(CitizenIdentifiers::class)->serialized(fn () => DB::transaction(function () use ($r, $data) {
                $user = User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
                $row = $user->activationRequest()->lockForUpdate()->firstOrFail();
                abort_unless($row->workflow === 'linked_mock' && in_array($row->status, ['requires_correction', 'rejected'], true), 409);
                $result = app(MockCivilSystems::class)->lookup($data + ['identity_path' => 'no_identity']);
                if ($result['person_id'] && User::where('mock_person_id', $result['person_id'])->whereKeyNot($user->id)->exists()) {
                    throw ValidationException::withMessages(['name' => 'السجل مرتبط بحساب قائم.']);
                }
                $user->forceFill(['mock_person_id' => $result['person_id'], 'registration_status' => 'under_review'])->save();
                app(MockIdentityVerification::class)->queue($user, $result['registry_result'], 'استكمال بيانات القيد من المواطن.', array_replace($row->evidence ?? [], ['registration' => $data]));
                app(AuditTrail::class)->record('citizen_account.mock_corrected', 'user', $user->id, $user, changes: ['status' => 'under_review']);
            }));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'السجل مرتبط بحساب قائم؛ لم يتم نقل ملكيته.']);
        }

        return back()->with('status', 'أعيد إرسال البيانات للمراجعة دون تغيير بيانات السجل المدني الأصلي.');
    }

    public function capture(Request $r, MockIdentityVerification $flow)
    {
        abort_unless(MockCivilSystems::enabled(), 404);
        $data = $r->validate(['selfie' => 'required|image|mimes:jpeg,jpg,png|max:2048|dimensions:min_width=300,min_height=300', 'operation_token' => 'required|uuid', 'camera_acknowledged' => 'accepted'], ['selfie.required' => 'التقط صورة الوجه.', 'selfie.dimensions' => 'الصورة لا تقل عن 300 × 300.', 'selfie.max' => 'حجم الصورة لا يتجاوز 2 ميجابايت.', 'camera_acknowledged.accepted' => 'أكد أنك التقطت الصورة بنفسك.']);
        $result = $flow->capture($r->user(), $data['selfie'], $data['operation_token']);

        $citizen = $r->user()->fresh();
        $message = match ($citizen->registration_status) {
            'active' => 'تم استلام الصورة وتفعيل حسابك تجريبيًا. يمكنك الآن الانتقال إلى لوحتك.',
            'under_review' => 'تم استلام الصورة وإحالة طلب تفعيل حسابك إلى الموظف المختص. انتظر إشعار نتيجة المراجعة.',
            default => 'تم استلام الصورة، ويلزم إعادة التقاطها لاستكمال التفعيل. راجع تعليمات التصوير ثم حاول مجددًا.',
        };

        return redirect()->route('citizen.account.status')->with('status', $message.' أُضيف إشعار بحالة الحساب إلى إشعاراتك داخل المنصة.');
    }

    public function checkRegistry(Request $request, User $citizen, MockCivilSystems $registry)
    {
        abort_unless(MockCivilSystems::enabled(), 404);
        Gate::authorize('activateCitizen', $citizen);
        $activation = $citizen->activationRequest;
        $registration = data_get($activation?->evidence, 'registration');
        abort_unless($citizen->identity_path === 'no_identity' && $activation?->workflow === 'linked_mock'
            && is_array($registration), 404);
        $data = $request->validate([
            'birth_number' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'min:2', 'max:100'],
        ]);
        $result = $registry->reviewBirthEvidence($registration, $data['birth_number'] ?? null, $data['name'] ?? null);
        app(AuditTrail::class)->record('citizen_account.mock_registry_checked', 'activation_request', $activation->id, $request->user(), changes: ['status' => $result['status']]);

        return response()->json($result)->header('Cache-Control', 'no-store, private');
    }

    public function review(Request $r, User $citizen, MockIdentityVerification $flow)
    {
        Gate::authorize('activateCitizen', $citizen);
        $data = $r->validate(['decision' => 'required|in:approved,rejected,retake_photo,requires_correction,referred', 'reason' => 'required|string|min:5|max:1500', 'revision' => 'required|integer|min:1']);
        try {
            $flow->review($r->user(), $citizen, $data);
        } catch (ValidationException $error) {
            throw $error->redirectTo(route('employee.accounts.show', $citizen));
        }

        return back()->with('status', 'حُفظ القرار وأُشعر المواطن.');
    }

    public function image(Request $r, User $citizen, string $kind)
    {
        abort_unless(MockCivilSystems::enabled(), 404);
        if ($r->user()->id !== $citizen->id) {
            Gate::authorize('activateCitizen', $citizen);
        } else {
            abort_unless($r->user()->is_active && $r->user()->role === 'citizen', 403);
        }
        abort_unless(in_array($kind, ['selfie', 'reference', 'birth'], true), 404);
        $path = match ($kind) {
            'reference' => DB::table('mock_civil_persons')->where('id', $citizen->mock_person_id)->value('reference_photo'),
            'birth' => data_get($citizen->activationRequest?->evidence, 'birth.path'),
            default => DB::table('mock_face_attempts')->where('user_id', $citizen->id)->latest('id')->value('image_path'),
        };
        abort_unless($path && Storage::disk('attachments')->exists($path), 404);

        return Storage::disk('attachments')->response($path, null, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function systems(Request $r)
    {
        abort_unless(MockCivilSystems::enabled() && $r->user()->is_active && $r->user()->role === 'admin', 403);

        return redirect()->route('admin.dashboard', ['tools' => 'mock']);
    }

    public function registryPortrait(Request $request, int $person)
    {
        abort_unless(MockCivilSystems::enabled() && $request->user()->is_active && $request->user()->role === 'admin', 403);
        $record = DB::table('mock_civil_persons')->find($person);
        abort_unless($record, 404);
        $image = app(\App\Services\Registration\MockPortraits::class)->viewableImage($record);
        abort_unless($image, 404);

        return response()->file($image['path'], ['Content-Type' => $image['mime'], 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
