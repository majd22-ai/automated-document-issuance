<?php

namespace Tests\Support;

use App\Models\IdentityCardForm;
use App\Models\NeighborhoodOfficial;
use App\Models\ResidenceDistrict;
use App\Models\User;
use App\Services\Identity\InitialIdentityReview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait InitialIdentityEvidence
{
    private function completeInitialEvidence(IdentityCardForm $form, User $staff): void
    {
        config(['citizen_mock.enabled' => true]);
        $officialUser = User::factory()->create(['role' => 'employee']);
        $district = ResidenceDistrict::with('governorate')->firstOrFail();
        $official = (new NeighborhoodOfficial)->forceFill(['district_id' => $district->id, 'user_id' => $officialUser->id,
            'full_name' => $officialUser->name, 'national_id' => (string) random_int(100000000000, 999999999999),
            'phone' => '+967777123456', 'neighborhood' => 'Synthetic neighborhood', 'is_active' => true, 'can_attest' => true]);
        $official->save();
        $form->forceFill(['data' => array_replace($form->data ?? [], ['neighborhood' => $official->neighborhood,
            'residence_district' => $district->name, 'residence_governorate' => $district->governorate->name])])->save();
        $form->attestation->forceFill(['citizen_name' => $form->data['applicant_name'] ?? $form->citizen->name,
            'neighborhood_official_id' => $official->id, 'decided_by' => $officialUser->id,
            'recipient_scope' => ['employee_id' => $officialUser->id, 'district_id' => $district->id,
                'governorate_id' => $district->governorate_id, 'neighborhood' => $official->neighborhood]])->save();
        foreach ($form->witnesses as $witness) {
            $personId = DB::table('mock_civil_persons')->insertGetId(['fixture_key' => (string) Str::uuid(),
                'name' => $witness->name, 'birth_date' => '1990-01-01', 'birth_place' => 'Synthetic',
                'family_role' => 'adult', 'complete' => true, 'phone' => '777123456', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('mock_civil_documents')->updateOrInsert(['document_type' => $witness->document_type, 'document_number' => $witness->document_number],
                ['person_id' => $personId, 'issued_place' => $witness->issued_place, 'issued_at' => $witness->issued_at->format('Y-m-d'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $file = UploadedFile::fake()->image('witness.png', 600, 600);
            $path = 'witness-selfies/'.Str::uuid().'.png';
            Storage::disk('attachments')->put($path, file_get_contents($file->getRealPath()));
            $witness->forceFill(['selfie_path' => $path, 'selfie_sha256' => hash_file('sha256', $file->getRealPath()),
                'selfie_size' => $file->getSize(), 'selfie_mime' => 'image/png'])->save();
        }
        $review = app(InitialIdentityReview::class);
        foreach (['photo', 'witness_1', 'witness_2', 'attestation'] as $key) {
            $task = $review->tasks($form)[$key];
            $review->record($staff, $form->application, ['task' => $key, 'outcome' => 'approved',
                'fingerprint' => $task['fingerprint'], 'decision_token' => (string) Str::uuid(), 'confirmed' => '1',
                'consent_method' => 'signed_statement', 'consent_reference' => 'SYNTHETIC-STATEMENT-'.$key]);
        }
    }
}
