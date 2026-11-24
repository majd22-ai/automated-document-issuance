<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $path = database_path('fixtures/mock-civil-registry-2026-11-24.json');
        $people = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($people) {
            foreach ($people as $person) {
                if ($person['family_reference'] === null) {
                    continue;
                }
                DB::table('mock_civil_persons')
                    ->where('fixture_key', $person['fixture_key'])
                    ->whereIn('family_id', DB::table('mock_civil_families')->select('id')->where('reference', $person['family_reference']))
                    ->update(Arr::only($person, ['name', 'family_role', 'father_name', 'mother_name']));
            }
        });
    }

    public function down(): void
    {
        // Preserve corrected family names and relationships.
    }
};
