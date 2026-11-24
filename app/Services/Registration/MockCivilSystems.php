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
        $people = DB::table('mock_civil_persons')->where('birth_date', $data['birth_date'])->where('birth_place', $data['birth_place'])->whereIn('family_role', ['child', 'son', 'daughter'])->get()->filter(fn ($p) => $normalize($p->name) === $normalize($data['name']) && $normalize($p->father_name) === $normalize($data['father_name']) && $normalize($p->mother_name) === $normalize($data['mother_name']));
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

    public function reviewBirthEvidence(array $registration, ?string $birthNumber, ?string $name = null): array
    {
        abort_unless(self::enabled(), 404);
        $familyId = null;
        foreach (['father', 'mother'] as $role) {
            $nationalId = $registration[$role.'_national_id'] ?? null;
            if (! filled($nationalId)) {
                continue;
            }
            $parentFamily = DB::table('mock_civil_persons')->where('national_id', $nationalId)
                ->where('family_role', $role)->value('family_id');
            if (! $parentFamily && $name !== null) {
                continue;
            }
            if (! $parentFamily || ($familyId !== null && $familyId !== $parentFamily)) {
                return ['status' => 'parent_mismatch'];
            }
            $familyId = $parentFamily;
        }
        if ($name !== null) {
            return $familyId === null ? ['status' => 'parent_mismatch'] : $this->searchFamilyBirthRecords($familyId, $name, $registration);
        }
        if (($familyId === null && $birthNumber === null) || empty($registration['birth_date']) || empty($registration['birth_place'])) {
            return ['status' => 'insufficient'];
        }

        $candidates = DB::table('mock_civil_persons as person')
            ->join('mock_birth_records as birth', 'birth.person_id', '=', 'person.id')
            ->whereIn('person.family_role', ['child', 'son', 'daughter'])
            ->where('person.birth_date', $registration['birth_date'])
            ->where('person.birth_place', $registration['birth_place'])
            ->when($familyId, fn ($query) => $query->where('person.family_id', $familyId))
            ->when($birthNumber !== null, fn ($query) => $query->where('birth.number', $birthNumber))
            ->get(['person.id', 'person.name']);
        if ($candidates->isEmpty()) {
            return ['status' => 'not_found'];
        }

        $normalize = fn (string $value) => preg_replace('/\s+/u', ' ', trim($value));
        $submittedName = $normalize($registration['name'] ?? '');
        $sameName = $candidates->filter(fn ($candidate) => $normalize($candidate->name) === $submittedName);
        $person = $sameName->count() === 1 ? $sameName->first() : ($candidates->count() === 1 ? $candidates->first() : null);
        if (! $person) {
            return ['status' => 'ambiguous'];
        }

        $result = $this->lookup(['identity_path' => 'no_identity'] + $registration + ['name' => '', 'father_name' => '', 'mother_name' => '']);

        return [
            'status' => $result['registry_result'] === 'MATCH' && (int) $result['person_id'] === (int) $person->id ? 'match' : 'review',
            'name' => $person->name,
        ];
    }

    private function searchFamilyBirthRecords(int $familyId, string $name, array $registration): array
    {
        $normalize = static fn (string $value) => strtr(preg_replace('/[\x{064B}-\x{065F}ـ]/u', '', trim($value)), [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي',
        ]);
        $term = $normalize($name);
        if (mb_strlen($term) < 2) {
            return ['status' => 'insufficient_name'];
        }
        $parents = DB::table('mock_civil_persons')->where('family_id', $familyId)
            ->whereIn('family_role', ['father', 'mother'])->get(['family_role', 'name', 'national_id'])->keyBy('family_role');
        $reference = DB::table('mock_civil_families')->where('id', $familyId)->value('reference');
        $confirmedId = $this->confirmedBirthRecord($registration);
        $records = DB::table('mock_civil_persons as person')
            ->join('mock_birth_records as birth', 'birth.person_id', '=', 'person.id')
            ->where('person.family_id', $familyId)->whereIn('person.family_role', ['child', 'son', 'daughter'])
            ->orderBy('person.name')->orderBy('person.id')
            ->get(['person.id', 'person.name', 'person.birth_date', 'person.birth_place', 'birth.number'])
            ->filter(fn ($person) => mb_stripos($normalize($person->name), $term) !== false)
            ->values()->map(function ($person) use ($registration, $parents, $reference, $confirmedId) {
                $values = [
                    'name' => $person->name, 'birth_date' => $person->birth_date, 'birth_place' => $person->birth_place,
                    'father_name' => $parents->get('father')?->name, 'father_national_id' => $parents->get('father')?->national_id,
                    'mother_name' => $parents->get('mother')?->name, 'mother_national_id' => $parents->get('mother')?->national_id,
                    'family_reference' => $reference,
                ];

                return [
                    'birth_number' => $person->number,
                    'matches_request' => $confirmedId === (int) $person->id,
                    'fields' => collect($values)->map(fn ($value, $key) => [
                        'registry' => $value, 'submitted' => $registration[$key] ?? null,
                    ]),
                ];
            });

        return ['status' => $records->isEmpty() ? 'not_found' : 'results', 'records' => $records];
    }

    public function confirmedBirthRecord(array $registration): ?int
    {
        foreach (['name', 'birth_date', 'birth_place', 'father_name', 'mother_name', 'father_national_id', 'mother_national_id'] as $field) {
            if (blank($registration[$field] ?? null)) {
                return null;
            }
        }
        $result = $this->lookup(['identity_path' => 'no_identity'] + $registration);

        return $result['registry_result'] === 'MATCH' ? (int) $result['person_id'] : null;
    }

    public function witnessDocument(string $documentType, string $documentNumber, ?int $excludePersonId = null): ?array
    {
        abort_unless(self::enabled(), 404);
        $document = DB::table('mock_civil_documents')
            ->join('mock_civil_persons', 'mock_civil_persons.id', '=', 'mock_civil_documents.person_id')
            ->where('mock_civil_documents.document_type', $documentType)
            ->where('mock_civil_documents.document_number', trim($documentNumber))
            ->where('mock_civil_documents.status', 'active')
            ->where('mock_civil_persons.complete', true)
            ->when($excludePersonId, fn ($query) => $query->where('mock_civil_persons.id', '!=', $excludePersonId))
            ->first([
                'mock_civil_persons.name',
                'mock_civil_persons.phone',
                'mock_civil_documents.issued_place',
                'mock_civil_documents.issued_at',
            ]);

        return $document ? [
            'name' => $document->name,
            'phone' => $document->phone,
            'issued_place' => $document->issued_place,
            'issued_at' => $document->issued_at,
        ] : null;
    }
}
