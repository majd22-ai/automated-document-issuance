<?php

namespace App\Http\Controllers;

use App\Models\BirthCertificate;
use App\Models\BirthCertificateDraft;
use App\Models\CitizenDocument;
use App\Models\GovernmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BirthCertificateController extends Controller
{
    public function saveDraft(Request $request)
    {
        $service = GovernmentService::where('code', 'birth-certificate-first-issue')->firstOrFail();
        abort_unless($service->is_active && $service->rules_approved, 404);
        $validated = $request->validate([
            'data' => ['sometimes', 'array:child_name,child_gender,birth_date,birth_place,father_name,father_national_id,mother_name,mother_national_id,birth_record_number,hospital_name,birth_record_date'],
            'data.child_name' => ['nullable', 'string', 'max:200'],
            'data.child_gender' => ['nullable', Rule::in(['ذكر', 'أنثى'])],
            'data.birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'data.birth_place' => ['nullable', 'string', 'max:200'],
            'data.father_name' => ['nullable', 'string', 'max:200'],
            'data.father_national_id' => ['nullable', 'string', 'max:50'],
            'data.mother_name' => ['nullable', 'string', 'max:200'],
            'data.mother_national_id' => ['nullable', 'string', 'max:50'],
            'data.birth_record_number' => ['nullable', 'string', 'max:100'],
            'data.hospital_name' => ['nullable', 'string', 'max:200'],
            'data.birth_record_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $draft = BirthCertificateDraft::where('user_id', $request->user()->id)
            ->where('government_service_id', $service->id)->first() ?? new BirthCertificateDraft;
        $draft->user_id = $request->user()->id;
        $draft->government_service_id = $service->id;
        $draft->id ??= (string) Str::uuid();
        $draft->payload = ['data' => $validated['data'] ?? [], 'notes' => $validated['notes'] ?? null];
        $draft->save();

        return redirect()->route('citizen.applications.create', ['service' => $service->code])
            ->with('status', 'تم حفظ مسودة طلب شهادة الميلاد بأمان، ولم يُقدَّم الطلب بعد.');
    }

    public function showPublic(string $token)
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        $certificate = BirthCertificate::where('verification_token_hash', hash('sha256', $token))->first();
        abort_unless($certificate && hash_equals($certificate->verification_token, $token), 404);

        return response()->view('public.birth-certificates.verify', compact('certificate'))
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function download(Request $request, string $id)
    {
        $document = $request->user()->role === 'citizen'
            ? $request->user()->documents()->where('document_type_code', 'birth_certificate')->findOrFail($id)
            : CitizenDocument::where('document_type_code', 'birth_certificate')->findOrFail($id);
        Gate::authorize('download', $document);
        $certificate = $document->birthCertificate;
        abort_unless($certificate, 404);
        $contents = $certificate->pdfContents();
        abort_if($contents === null, 409, 'ملف الشهادة غير متاح أو تعذر التحقق من سلامته.');

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="birth-certificate-'.$certificate->certificate_number.'.pdf"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
