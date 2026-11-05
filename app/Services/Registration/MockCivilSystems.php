<?php

namespace App\Services\Registration;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MockCivilSystems
{
    public static function enabled(): bool
    {
        return app()->environment('local', 'testing') && config('citizen_mock.enabled') === true;
    }

    public function lookup(array $data): array
    {
        abort_unless(self::enabled(), 404);
        $normalize = fn ($v) => preg_replace('/\s+/u', ' ', trim((string) $v));
        if ($data['identity_path'] === 'has_identity') {
            $person = DB::table('mock_civil_persons')->where('national_id', $data['national_id'])->first();
            if (! $person || $normalize($person->name) !== $normalize($data['name']) || ! DB::table('mock_identity_cards')->where('person_id', $person->id)->where('number', $data['national_id'])->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['national_id' => 'تعذر مطابقة الاسم والرقم الوطني مع السجل والبطاقة في أنظمة المحاكاة.']);
            }

            return ['person_id' => $person->id, 'registry_result' => 'MATCH'];
        }
        $people = DB::table('mock_civil_persons')->where('birth_date', $data['birth_date'])->where('birth_place', $data['birth_place'])->where('family_role', 'child')->get()->filter(fn ($p) => $normalize($p->name) === $normalize($data['name']) && $normalize($p->father_name) === $normalize($data['father_name']) && $normalize($p->mother_name) === $normalize($data['mother_name']));
        if ($people->count() !== 1) {
            return ['person_id' => null, 'registry_result' => 'NOT_FOUND'];
        }
        $person = $people->first();
        $family = $person->family_id ? DB::table('mock_civil_families')->find($person->family_id) : null;
        $valid = $family && DB::table('mock_birth_records')->where('person_id', $person->id)->exists();
        foreach (['father', 'mother'] as $parent) {
            $query = DB::table('mock_civil_persons')->where('family_id', $person->family_id)->where('family_role', $parent);
            if (filled($data[$parent.'_national_id'] ?? null)) {
                $query->where('national_id', $data[$parent.'_national_id']);
            }
            $valid = $valid && $query->exists();
        }
        if (filled($data['family_reference'] ?? null)) {
            $valid = $valid && $family?->reference === $data['family_reference'];
        }

        return ['person_id' => $person->id, 'registry_result' => $valid && $person->complete ? 'MATCH' : 'REVIEW'];
    }
}
