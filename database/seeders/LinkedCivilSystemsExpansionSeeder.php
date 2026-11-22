<?php

namespace Database\Seeders;

use App\Services\Registration\MockCivilSystems;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LinkedCivilSystemsExpansionSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(
            MockCivilSystems::enabled(),
            403,
            'Local simulation only'
        );

        $families = [
            [
                'code' => '002',
                'reference' => 'MOCK-FAMILY-002',
                'father' => 'عبد الرحمن صالح قاسم السالمي',
                'mother' => 'منى أحمد يحيى القباطي',
                'place' => 'صنعاء - منطقة الاختبار',
                'members' => [
                    ['father', 'عبد الرحمن صالح قاسم السالمي', '99020000001', '1980-04-12', 'PASS', true],
                    ['mother', 'منى أحمد يحيى القباطي', '99020000002', '1983-08-20', 'PASS', true],
                    ['child1', 'سامي عبد الرحمن صالح السالمي', '99020000003', '2004-06-15', 'MATCH', true],
                    ['child2', 'ريم عبد الرحمن صالح السالمي', null, '2010-09-11', 'REVIEW', true],
                    ['child3', 'مازن عبد الرحمن صالح السالمي', null, '2014-02-08', 'NO_REFERENCE_IMAGE', true],
                ],
            ],
            [
                'code' => '003',
                'reference' => 'MOCK-FAMILY-003',
                'father' => 'يحيى محمد علي الحكيمي',
                'mother' => 'أمل حسن صالح المتوكل',
                'place' => 'تعز - منطقة الاختبار',
                'members' => [
                    ['father', 'يحيى محمد علي الحكيمي', '99020000004', '1978-03-10', 'PASS', true],
                    ['mother', 'أمل حسن صالح المتوكل', '99020000005', '1982-07-19', 'MATCH', true],
                    ['child1', 'محمد يحيى محمد الحكيمي', '99020000006', '2003-05-22', 'NO_MATCH', true],
                    ['child2', 'سارة يحيى محمد الحكيمي', null, '2011-10-14', 'IMAGE_QUALITY_FAILED', true],
                    ['child3', 'أنس يحيى محمد الحكيمي', null, '2015-01-17', 'MATCH', false],
                ],
            ],
            [
                'code' => '004',
                'reference' => 'MOCK-FAMILY-004',
                'father' => 'فؤاد عبد الله أحمد الديلمي',
                'mother' => 'نجلاء قاسم محمد الآنسي',
                'place' => 'إب - منطقة الاختبار',
                'members' => [
                    ['father', 'فؤاد عبد الله أحمد الديلمي', '99020000007', '1979-11-05', 'MATCH', true],
                    ['mother', 'نجلاء قاسم محمد الآنسي', '99020000008', '1984-02-16', 'REVIEW', true],
                    ['child1', 'خالد فؤاد عبد الله الديلمي', '99020000009', '2005-04-09', 'PASS', true],
                    ['child2', 'ليان فؤاد عبد الله الديلمي', null, '2012-06-13', 'MATCH', true],
                    ['child3', 'رامي فؤاد عبد الله الديلمي', null, '2016-12-21', 'REVIEW', true],
                ],
            ],
            [
                'code' => '005',
                'reference' => 'MOCK-FAMILY-005',
                'father' => 'مازن علي ناصر الحرازي',
                'mother' => 'هدى محمد صالح الشامي',
                'place' => 'الحديدة - منطقة الاختبار',
                'members' => [
                    ['father', 'مازن علي ناصر الحرازي', '99020000010', '1981-09-03', 'PASS', true],
                    ['mother', 'هدى محمد صالح الشامي', '99020000011', '1985-05-27', 'MATCH', true],
                    ['child1', 'ناصر مازن علي الحرازي', '99020000012', '2004-08-18', 'REVIEW', true],
                    ['child2', 'مريم مازن علي الحرازي', null, '2013-03-25', 'PASS', true],
                    ['child3', 'حسن مازن علي الحرازي', null, '2017-07-30', 'MATCH', true],
                ],
            ],
        ];

        $photoPath = 'mock-registry/reference-illustration.png';

        DB::transaction(function () use ($families, $photoPath) {
            foreach ($families as $familyData) {
                DB::table('mock_civil_families')->updateOrInsert(
                    ['reference' => $familyData['reference']],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $familyId = DB::table('mock_civil_families')
                    ->where('reference', $familyData['reference'])
                    ->value('id');

                foreach ($familyData['members'] as $member) {
                    [$key, $name, $nationalId, $birthDate, $scenario, $live] = $member;

                    $fixtureKey = 'exp-'.$familyData['code'].'-'.$key;

                    $role = in_array($key, ['father', 'mother'], true)
                        ? $key
                        : 'child';

                    $referencePhoto = $scenario === 'NO_REFERENCE_IMAGE'
                        ? null
                        : (
                            Storage::disk('attachments')->exists($photoPath)
                                ? $photoPath
                                : null
                        );

                    DB::table('mock_civil_persons')->updateOrInsert(
                        ['fixture_key' => $fixtureKey],
                        [
                            'name' => $name,
                            'national_id' => $nationalId,
                            'birth_date' => $birthDate,
                            'birth_place' => $familyData['place'],
                            'family_id' => $familyId,
                            'family_role' => $role,
                            'father_name' => $familyData['father'],
                            'mother_name' => $familyData['mother'],
                            'reference_photo' => $referencePhoto,
                            'face_scenario' => $scenario,
                            'liveness_pass' => $live,
                            'complete' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $personId = DB::table('mock_civil_persons')
                        ->where('fixture_key', $fixtureKey)
                        ->value('id');

                    DB::table('mock_civil_persons')
                        ->where('id', $personId)
                        ->update([
                            'phone' => '7771'.str_pad(
                                (string) $personId,
                                6,
                                '0',
                                STR_PAD_LEFT
                            ),
                        ]);

                    // Birth record for every family member.
                    DB::table('mock_birth_records')->updateOrInsert(
                        ['person_id' => $personId],
                        [
                            'number' => 'MOCK-BIRTH-EXP-'.$familyData['code'].'-'.$key,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    if ($nationalId) {
                        DB::table('mock_identity_cards')->updateOrInsert(
                            ['person_id' => $personId],
                            [
                                'number' => $nationalId,
                                'status' => 'active',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );

                        $this->saveDocument(
                            $personId,
                            'بطاقة شخصية',
                            $nationalId
                        );

                        $this->saveDocument(
                            $personId,
                            'جواز سفر',
                            'MOCK-EXP-PASSPORT-'.$familyData['code'].'-'.$key
                        );
                    }

                    // One family card linked to the family head.
                    if ($key === 'father') {
                        $this->saveDocument(
                            $personId,
                            'بطاقة عائلية',
                            'MOCK-EXP-FAMILY-CARD-'.$familyData['code']
                        );
                    }
                }
            }
        });
    }

    private function saveDocument(
        int $personId,
        string $type,
        string $number
    ): void {
        DB::table('mock_civil_documents')->updateOrInsert(
            [
                'document_type' => $type,
                'document_number' => $number,
            ],
            [
                'person_id' => $personId,
                'status' => 'active',
                'issued_place' => 'مركز إصدار تجريبي',
                'issued_at' => '2022-01-15',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
