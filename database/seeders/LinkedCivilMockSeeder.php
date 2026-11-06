<?php

namespace Database\Seeders;

use App\Services\Registration\MockCivilSystems;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LinkedCivilMockSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(MockCivilSystems::enabled(), 403, 'Local simulation only');
        DB::transaction(function () {
            DB::table('mock_civil_families')->updateOrInsert(['reference' => 'MOCK-FAMILY-001'], ['updated_at' => now(), 'created_at' => now()]);
            $family = DB::table('mock_civil_families')->where('reference', 'MOCK-FAMILY-001')->value('id');
            // An illustration, not a citizen photograph or biometric reference.
            $photo = 'mock-registry/reference-illustration.png';
            if (! Storage::disk('attachments')->exists($photo)) {
                if (function_exists('imagecreatetruecolor')) {
                    $im = imagecreatetruecolor(600, 600);
                    $white = imagecolorallocate($im, 255, 255, 255);
                    $gray = imagecolorallocate($im, 90, 90, 90);
                    imagefill($im, 0, 0, $white);
                    imagefilledellipse($im, 300, 210, 140, 160, $gray);
                    imagefilledellipse($im, 300, 450, 300, 230, $gray);
                    imagestring($im, 5, 210, 30, 'SYNTHETIC MOCK', $gray);
                    ob_start();
                    imagepng($im);
                    $bytes = ob_get_clean();
                    imagedestroy($im);
                } else {
                    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
                }
                Storage::disk('attachments')->put($photo, $bytes);
            }
            foreach ([
                ['father', 'أب محاكاة أسرة تجريبية', '99010000901', 'MATCH', true, true],
                ['mother', 'أم محاكاة أسرة تجريبية', '99010000902', 'MATCH', true, true],
                ['match', 'مواطن محاكاة ناجح', '99010000001', 'MATCH', true, true],
                ['review', 'مواطن محاكاة مراجعة', '99010000002', 'REVIEW', true, true],
                ['no-photo', 'مواطن محاكاة بلا صورة', '99010000003', 'NO_REFERENCE_IMAGE', true, true],
                ['no-match', 'مواطن محاكاة غير مطابق', '99010000004', 'NO_MATCH', true, true],
                ['quality', 'مواطن محاكاة جودة', '99010000005', 'IMAGE_QUALITY_FAILED', true, true],
                ['liveness', 'مواطن محاكاة حيوية', '99010000006', 'MATCH', false, true],
                ['child', 'طفل محاكاة أسري كامل', null, 'REVIEW', true, true],
                ['incomplete', 'طفل محاكاة بيانات ناقصة', null, 'REVIEW', true, false],
            ] as [$key, $name, $national, $scenario, $live, $complete]) {
                DB::table('mock_civil_persons')->updateOrInsert(['fixture_key' => $key], ['name' => $name, 'national_id' => $national, 'birth_date' => '2005-02-03', 'birth_place' => 'منطقة اختبار', 'family_id' => $family, 'family_role' => in_array($key, ['father', 'mother']) ? $key : 'child', 'father_name' => 'أب محاكاة أسرة تجريبية', 'mother_name' => 'أم محاكاة أسرة تجريبية', 'reference_photo' => $key === 'no-photo' ? null : $photo, 'face_scenario' => $scenario, 'liveness_pass' => $live, 'complete' => $complete, 'created_at' => now(), 'updated_at' => now()]);
                $id = DB::table('mock_civil_persons')->where('fixture_key', $key)->value('id');
                DB::table('mock_birth_records')->updateOrInsert(['person_id' => $id], ['number' => 'MOCK-BIRTH-'.$id, 'created_at' => now(), 'updated_at' => now()]);
                if ($national) {
                    DB::table('mock_identity_cards')->updateOrInsert(['person_id' => $id], ['number' => $national, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }
}
