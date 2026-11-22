<?php
/**
 * Seed 8 test citizens: 4 with identity cards + 4 without
 * Run: php scripts/seed_test_citizens.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$password = Hash::make('Password@123');

// =====================================================
// 4 مواطنون لديهم بطاقة شخصية (mock_civil_persons + mock_identity_cards)
// =====================================================
$withCard = [
    ['name'=>'أحمد محمد عبدالله الحمادي',  'national_id'=>'10100000011','email'=>'ahmed.hamadi@test.ye',    'phone'=>'+96771100011'],
    ['name'=>'خالد عبدالرحمن سالم العمري',  'national_id'=>'10100000022','email'=>'khaled.omari@test.ye',   'phone'=>'+96771100022'],
    ['name'=>'علي حسن محمد الزبيدي',        'national_id'=>'10100000033','email'=>'ali.zubaidi@test.ye',    'phone'=>'+96771100033'],
    ['name'=>'عمر يحيى إبراهيم الصنعاني',  'national_id'=>'10100000044','email'=>'omar.sanaani@test.ye',   'phone'=>'+96771100044'],
];

// =====================================================
// 4 مواطنون ليس لديهم بطاقة (سجلوا بشهادة ميلاد)
// =====================================================
$noCard = [
    ['name'=>'صالح عبدالله فارس المقدمي',   'email'=>'saleh.mqadami@test.ye',  'phone'=>'+96771100055', 'birth_cert'=>'BC-TEST-0055'],
    ['name'=>'فيصل محمد أحمد الكوكباني',    'email'=>'faisal.kawkabani@test.ye','phone'=>'+96771100066', 'birth_cert'=>'BC-TEST-0066'],
    ['name'=>'ماجد عبدالعزيز حسن الذماري',  'email'=>'majed.dhamari@test.ye',  'phone'=>'+96771100077', 'birth_cert'=>'BC-TEST-0077'],
    ['name'=>'طارق يوسف سعيد الأكوع',       'email'=>'tarek.akwa@test.ye',     'phone'=>'+96771100088', 'birth_cert'=>'BC-TEST-0088'],
];

DB::transaction(function () use ($withCard, $noCard, $password) {

    foreach ($withCard as $i => $data) {
        // 1. mock_civil_persons
        $personId = DB::table('mock_civil_persons')->insertGetId([
            'fixture_key' => 'test-person-card-'.($i+1),
            'name'        => $data['name'],
            'national_id' => $data['national_id'],
            'birth_date'  => '1990-01-0'.($i+1),
            'birth_place' => 'مستشفى الثورة',
            'family_role' => 'head',
            'phone'       => substr($data['phone'], 4),
            'face_scenario' => 'PASS',
            'liveness_pass' => 1,
            'complete'    => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // 2. mock_identity_cards
        DB::table('mock_identity_cards')->insert([
            'person_id'  => $personId,
            'number'     => $data['national_id'],
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. user
        $userId = DB::table('users')->insertGetId([
            'name'                => $data['name'],
            'email'               => $data['email'],
            'phone'               => $data['phone'],
            'national_id'         => $data['national_id'],
            'password'            => $password,
            'role'                => 'citizen',
            'is_active'           => 1,
            'registration_status' => 'active',
            'identity_path'       => 'has_identity',
            'mock_person_id'      => $personId,
            'account_activated_at'  => now(),
            'identity_verified_at'  => now(),
            'email_verified_at'     => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        echo "✅ [لديه بطاقة] {$data['name']} | الرقم الوطني: {$data['national_id']} | الإيميل: {$data['email']} | كلمة المرور: Password@123\n";
    }

    foreach ($noCard as $i => $data) {
        $userId = DB::table('users')->insertGetId([
            'name'                     => $data['name'],
            'email'                    => $data['email'],
            'phone'                    => $data['phone'],
            'national_id'              => null,
            'password'                 => $password,
            'role'                     => 'citizen',
            'is_active'                => 1,
            'registration_status'      => 'initial_account',
            'identity_path'            => 'birth_certificate',
            'birth_certificate_number' => $data['birth_cert'],
            'email_verified_at'        => now(),
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        echo "✅ [بدون بطاقة] {$data['name']} | شهادة الميلاد: {$data['birth_cert']} | الإيميل: {$data['email']} | كلمة المرور: Password@123\n";
    }
});

echo "\n✅ تم إضافة 8 مواطنين بنجاح.\n";
