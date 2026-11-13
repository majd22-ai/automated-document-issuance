<?php

namespace Database\Seeders;

use App\Models\PostalCollector;
use App\Models\PostalOffice;
use App\Rules\PostalPasswordLength;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class PostalNetworkSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('postal_network.enabled') || ! config('postal_network.allow_seed')) {
            throw new RuntimeException('Postal demo seeding requires local/testing, feature enabled and explicit seed opt-in.');
        }

        $fixtures = [
            ['SANA-001', 'مكتب البريد المركزي – صنعاء', 'وسط صنعاء', 'موقع تجريبي في وسط أمانة العاصمة؛ ليس عنوان فرع رسمي مؤكداً', 'postal_central_demo'],
            ['SANA-002', 'مكتب بريد التحرير', 'التحرير', 'عنوان تعليمي في منطقة التحرير، أمانة العاصمة', 'postal_tahrir_demo'],
            ['SANA-003', 'مكتب بريد حدة – فرع تجريبي', 'حدة', 'عنوان تعليمي في منطقة حدة، أمانة العاصمة', 'postal_hadda_demo'],
        ];

        // Validate all required credentials before making any database changes.
        foreach ($fixtures as [$code]) {
            if (! PostalCollector::where('fixture_key', $code)->exists()) {
                $validator = Validator::make(['password' => config('postal_network.passwords.'.$code)],
                    ['password' => ['required', 'string', 'max:72', new PostalPasswordLength, Password::min(12)->mixedCase()->numbers()->symbols()]]);
                if ($validator->fails()) {
                    throw new RuntimeException('Missing or weak environment credential for postal fixture '.$code.'.');
                }
            }
        }

        DB::transaction(function () use ($fixtures) {
            foreach ($fixtures as [$code, $name, $area, $address, $username]) {
                $office = PostalOffice::where('code', $code)->first();
                $collector = PostalCollector::where('fixture_key', $code)->first();
                if (($office && ! $office->is_demo)
                    || (! $collector && PostalCollector::where('username', $username)->exists())) {
                    throw new RuntimeException('Postal fixture conflicts with an existing record; inspect manually.');
                }
                $office ??= PostalOffice::create(compact('name', 'code', 'area', 'address') + ['status' => 'active']);
                if (! $collector) {
                    $collector = new PostalCollector([
                        'postal_office_id' => $office->id, 'name' => 'محصل تجريبي — '.$area,
                        'username' => $username, 'password' => config('postal_network.passwords.'.$code),
                        'is_active' => true, 'can_collect' => true,
                    ]);
                    $collector->forceFill(['fixture_key' => $code])->save();
                }
                // Existing names, office assignments, credentials and activation states are never reset.
            }
        });
    }
}
