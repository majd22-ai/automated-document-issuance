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
            $timestamps = ['created_at' => now(), 'updated_at' => now()];
            $familyIds = [];
            foreach ($people as $person) {
                $reference = $person['family_reference'];
                if ($reference !== null && ! isset($familyIds[$reference])) {
                    $families = DB::table('mock_civil_families');
                    $familyIds[$reference] = (clone $families)->where('reference', $reference)->value('id')
                        ?? $families->insertGetId(['reference' => $reference] + $timestamps);
                }

                $peopleTable = DB::table('mock_civil_persons');
                $personId = (clone $peopleTable)->where('fixture_key', $person['fixture_key'])->value('id')
                    ?? $peopleTable->insertGetId(
                        Arr::except($person, ['family_reference', 'card', 'birth', 'documents'])
                            + ['family_id' => $familyIds[$reference] ?? null] + $timestamps
                    );

                foreach (['card' => 'mock_identity_cards', 'birth' => 'mock_birth_records'] as $key => $table) {
                    if ($person[$key] !== null && ! DB::table($table)->where('person_id', $personId)->exists()) {
                        DB::table($table)->insert($person[$key] + ['person_id' => $personId] + $timestamps);
                    }
                }

                foreach ($person['documents'] as $document) {
                    $identity = Arr::only($document, ['document_type', 'document_number']);
                    $owner = DB::table('mock_civil_documents')->where($identity)->value('person_id');
                    if ($owner === null) {
                        DB::table('mock_civil_documents')->insert($document + ['person_id' => $personId] + $timestamps);
                    } elseif ((int) $owner !== (int) $personId) {
                        throw new RuntimeException('A shared mock document is linked to a different local person.');
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Preserve shared records that may already be linked to local accounts.
    }
};
