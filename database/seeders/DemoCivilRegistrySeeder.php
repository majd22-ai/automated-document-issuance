<?php

namespace Database\Seeders;

use App\Models\DemoCivilDocument;
use App\Models\DemoCivilFamily;
use App\Models\DemoCivilFamilyMember;
use App\Models\DemoCivilPerson;
use App\Models\DemoCivilRecord;
use App\Models\DemoCivilRecordParty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoCivilRegistrySeeder extends Seeder
{
    private const COUNT = 100;

    public function run(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        abort_unless(
            app()->environment('testing')
                && config('demo_civil_registry.allow_seed') === true
                && $connection === 'sqlite'
                && ($database === ':memory:' || str_ends_with($database, '_testing')),
            403,
            'Synthetic demo registry seeding is allowed only on an explicitly enabled isolated SQLite test database.'
        );

        $disk = Storage::disk('demo-civil-registry');
        $portrait = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320"><rect width="320" height="320" fill="#e7edf0"/><circle cx="160" cy="112" r="58" fill="#77878d"/><path d="M52 310c8-77 48-112 108-112s100 35 108 112" fill="#77878d"/><text x="160" y="292" text-anchor="middle" font-family="sans-serif" font-size="17" fill="#fff">SYNTHETIC DEMO</text></svg>';
        $documentImage = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400" viewBox="0 0 640 400"><rect width="640" height="400" fill="#f1f4f4"/><rect x="24" y="24" width="592" height="352" rx="8" fill="none" stroke="#59747d" stroke-width="4"/><text x="320" y="176" text-anchor="middle" font-family="sans-serif" font-size="30" fill="#34525b">DEMO DOCUMENT</text><text x="320" y="225" text-anchor="middle" font-family="sans-serif" font-size="20" fill="#34525b">NOT AN OFFICIAL RECORD</text></svg>';
        $disk->put('fixtures/portrait.svg', $portrait);
        $disk->put('fixtures/document.svg', $documentImage);
        $portraitHash = hash('sha256', $portrait);
        $portraitSize = strlen($portrait);
        $documentHash = hash('sha256', $documentImage);
        $documentSize = strlen($documentImage);

        DB::transaction(function () use ($portraitHash, $portraitSize, $documentHash, $documentSize) {
            $people = [];
            $families = [];
            $parents = [];
            for ($familyNumber = 1; $familyNumber <= 20; $familyNumber++) {
                $familyKey = sprintf('family-%02d', $familyNumber);
                $family = DemoCivilFamily::updateOrCreate(['fixture_key' => $familyKey], [
                    'demo_identifier' => sprintf('DEMO-FAM-%06d', $familyNumber),
                    'archived_at' => null,
                    'is_synthetic' => true,
                ]);
                $families[$familyNumber] = $family;

                for ($member = 0; $member < 5; $member++) {
                    $sequence = (($familyNumber - 1) * 5) + $member + 1;
                    $fixtureKey = sprintf('person-%03d', $sequence);
                    $isParent = $member < 2;
                    $birthYear = $isParent ? 1970 + (($familyNumber + $member) % 18) : 2012 + (($familyNumber + $member) % 8);
                    $birthDate = sprintf('%04d-%02d-%02d', $birthYear, (($sequence % 12) + 1), (($sequence % 25) + 1));
                    $person = DemoCivilPerson::updateOrCreate(['fixture_key' => $fixtureKey], [
                        'demo_identifier' => sprintf('DEMO-CIV-%06d', $sequence),
                        'full_name' => sprintf('مواطن تجريبي رقم %03d', $sequence),
                        'gender' => $member % 2 === 0 ? 'male' : 'female',
                        'birth_date' => $birthDate,
                        'birth_place' => sprintf('منطقة اختبار %02d', (($familyNumber - 1) % 10) + 1),
                        'marital_status' => $isParent ? ($familyNumber === 1 ? 'divorced' : 'married') : 'single',
                        'synthetic_address' => sprintf('حي تجريبي %02d، شارع %03d', $familyNumber, $sequence),
                        'photo_path' => 'fixtures/portrait.svg',
                        'photo_sha256' => $portraitHash,
                        'photo_byte_size' => $portraitSize,
                        'is_synthetic' => true,
                        'archived_at' => null,
                    ]);
                    $people[$sequence] = $person;
                    DemoCivilFamilyMember::updateOrCreate(
                        ['family_id' => $family->id, 'person_id' => $person->id],
                        ['relationship' => $member === 0 ? 'head' : ($member === 1 ? 'spouse' : 'child'), 'status' => 'active', 'joined_on' => $birthDate],
                    );
                    if ($member < 2) {
                        $parents[$familyNumber][$member] = $person;
                    }
                }
                $family->forceFill(['head_person_id' => $parents[$familyNumber][0]->id])->save();
            }

            foreach ($people as $sequence => $person) {
                $familyNumber = intdiv($sequence - 1, 5) + 1;
                $member = ($sequence - 1) % 5;
                $parent = $parents[$familyNumber];
                $birth = $this->record('birth-'.$sequence, 'birth', $person, $families[$familyNumber], null, $person->birth_date, $person->birth_place, 'active', [
                    'child_name' => $person->full_name,
                    'gender' => $person->gender,
                    'father_name' => $member < 2 ? 'أب اصطناعي غير مدرج في العينة' : $parent[0]->full_name,
                    'mother_name' => $member < 2 ? 'أم اصطناعية غير مدرجة في العينة' : $parent[1]->full_name,
                    'birth_certificate_number' => sprintf('DEMO-BIRTH-CERT-%06d', $sequence),
                    'registered_on' => $person->birth_date->format('Y-m-d'),
                ], $member < 2
                    ? [['person_id' => $person->id, 'party_role' => 'child']]
                    : [['person_id' => $person->id, 'party_role' => 'child'], ['person_id' => $parent[0]->id, 'party_role' => 'father'], ['person_id' => $parent[1]->id, 'party_role' => 'mother']]);
                $this->document($sequence, $person, $birth, 'شهادة ميلاد تجريبية', $documentHash, $documentSize);

                if ($member < 2) {
                    $card = $this->record('card-'.$sequence, 'identity_card', $person, $families[$familyNumber], null, $person->birth_date->copy()->addYears(18), sprintf('مركز بطاقات تجريبي %02d', $familyNumber), 'active', [
                        'card_number' => sprintf('DEMO-ID-%06d', $sequence),
                        'issued_on' => $person->birth_date->copy()->addYears(18)->format('Y-m-d'),
                        'expires_on' => $person->birth_date->copy()->addYears(68)->format('Y-m-d'),
                        'issuer' => 'مركز إصدار تجريبي',
                    ], [['person_id' => $person->id, 'party_role' => 'holder']]);
                    $this->document($sequence + 100, $person, $card, 'بطاقة شخصية تجريبية', $documentHash, $documentSize);
                }

                if ($member === 0) {
                    $familyRecord = $this->record('family-'.$familyNumber, 'family', $person, $families[$familyNumber], null, null, null, 'active', [
                        'family_number' => $families[$familyNumber]->demo_identifier,
                    ], [['person_id' => $person->id, 'party_role' => 'head']]);
                    $this->document($sequence + 200, $person, $familyRecord, 'ملخص أسرة تجريبي', $documentHash, $documentSize);
                }
            }

            $marriages = [];
            for ($familyNumber = 1; $familyNumber <= 20; $familyNumber++) {
                $husband = $parents[$familyNumber][0];
                $wife = $parents[$familyNumber][1];
                $marriageDate = $husband->birth_date->copy()->addYears(22);
                $marriage = $this->record('marriage-'.$familyNumber, 'marriage', $husband, $families[$familyNumber], null, $marriageDate, sprintf('قاعة تجريبية %02d', $familyNumber), $familyNumber === 1 ? 'dissolved' : 'active', [
                    'contract_number' => sprintf('DEMO-MARR-%06d', $familyNumber),
                    'wife_identifier' => $wife->demo_identifier,
                ], [['person_id' => $husband->id, 'party_role' => 'husband'], ['person_id' => $wife->id, 'party_role' => 'wife']]);
                $marriages[$familyNumber] = $marriage;
                $this->document(300 + $familyNumber, $husband, $marriage, 'عقد زواج تجريبي', $documentHash, $documentSize);
            }
            $divorce = $this->record('divorce-1', 'divorce', $parents[1][0], $families[1], $marriages[1], '2024-01-01', 'مكتب وقائع تجريبي', 'active', [
                'divorce_type' => 'first',
                'document_number' => 'DEMO-DIV-000001',
            ], [['person_id' => $parents[1][0]->id, 'party_role' => 'husband'], ['person_id' => $parents[1][1]->id, 'party_role' => 'wife']]);
            $this->document(401, $parents[1][0], $divorce, 'وثيقة طلاق تجريبية', $documentHash, $documentSize);

            foreach ([6] as $sequence) {
                $person = $people[$sequence];
                $death = $this->record('death-'.$sequence, 'death', $person, $families[intdiv($sequence - 1, 5) + 1], null, '2024-06-01', 'مرفق صحي تجريبي', 'active', [
                    'certificate_number' => sprintf('DEMO-DEATH-%06d', $sequence),
                    'cause' => 'سبب افتراضي لأغراض الاختبار',
                ], [['person_id' => $person->id, 'party_role' => 'deceased']]);
                $person->familyMemberships()->update(['status' => 'deceased']);
                $widow = $people[$sequence + 1];
                $widow->forceFill(['marital_status' => 'widowed'])->save();
                $widow->familyMemberships()->update(['relationship' => 'head', 'status' => 'active']);
                $families[2]->forceFill(['head_person_id' => $widow->id])->save();
                $marriages[2]->forceFill(['status' => 'dissolved'])->save();
                $familyRecord = DemoCivilRecord::where('fixture_key', 'family-2')->firstOrFail();
                $familyRecord->forceFill(['person_id' => $widow->id])->save();
                $familyRecord->parties()->update(['person_id' => $widow->id]);
                $familyRecord->documents()->update(['person_id' => $widow->id]);
                $person->records()->where('record_type', 'identity_card')->update(['status' => 'cancelled']);
                $this->document(500 + $sequence, $person, $death, 'شهادة وفاة تجريبية', $documentHash, $documentSize);
            }

            for ($sequence = 1; $sequence <= 12; $sequence++) {
                $person = $people[$sequence];
                $correction = $this->record('correction-'.$sequence, 'correction', $person, null, null, '2022-01-01', null, 'corrected', [
                    'request_number' => sprintf('DEMO-CORR-%06d', $sequence),
                    'field' => 'synthetic_address',
                    'previous_value' => 'عنوان تجريبي سابق',
                    'new_value' => $person->synthetic_address,
                    'reason' => 'بيانات توضيحية اصطناعية',
                ], [['person_id' => $person->id, 'party_role' => 'subject']]);
                $this->document(600 + $sequence, $person, $correction, 'مستند تصحيح تجريبي', $documentHash, $documentSize);
            }

            for ($sequence = 1; $sequence <= 8; $sequence++) {
                $person = $people[$sequence + 40];
                $restriction = $this->record('restriction-'.$sequence, 'restriction', $person, null, null, '2022-01-01', null, 'released', [
                    'restriction_type' => 'قيد اختباري مرفوع',
                    'description' => 'قيد افتراضي غير رسمي لأغراض اختبار الواجهة',
                    'authority' => 'جهة تجريبية غير حكومية',
                ], [['person_id' => $person->id, 'party_role' => 'subject']]);
                $this->document(700 + $sequence, $person, $restriction, 'مستند قيد تجريبي', $documentHash, $documentSize);
            }
        });
    }

    private function record(string $fixture, string $type, DemoCivilPerson $person, ?DemoCivilFamily $family, ?DemoCivilRecord $related, mixed $date, ?string $place, string $status, array $details, array $parties): DemoCivilRecord
    {
        $record = DemoCivilRecord::updateOrCreate(['fixture_key' => $fixture], [
            'record_type' => $type,
            'demo_identifier' => 'DEMO-'.strtoupper(str_replace('_', '-', $type)).'-'.strtoupper(str_replace('-', '', $fixture)),
            'person_id' => $person->id,
            'family_id' => $family?->id,
            'related_record_id' => $related?->id,
            'occurred_on' => $date,
            'place' => $place,
            'status' => $status,
            'details' => $details,
            'is_synthetic' => true,
            'archived_at' => null,
        ]);
        $record->parties()->delete();
        foreach ($parties as $party) {
            (new DemoCivilRecordParty)->forceFill(['record_id' => $record->id, ...$party])->save();
        }

        return $record;
    }

    private function document(int $key, DemoCivilPerson $person, DemoCivilRecord $record, string $label, string $hash, int $size): void
    {
        DemoCivilDocument::updateOrCreate(['fixture_key' => 'document-'.$key], [
            'person_id' => $person->id,
            'record_id' => $record->id,
            'demo_identifier' => sprintf('DEMO-DOC-%06d', $key),
            'label' => $label,
            'storage_path' => 'fixtures/document.svg',
            'mime_type' => 'image/svg+xml',
            'byte_size' => $size,
            'sha256' => $hash,
            'is_synthetic' => true,
            'archived_at' => null,
        ]);
    }
}
