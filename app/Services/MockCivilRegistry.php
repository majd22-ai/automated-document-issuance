<?php

namespace App\Services;

use App\Models\CitizenDocument;
use App\Models\CitizenServiceRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MockCivilRegistry
{
    public const TABLES = [
        ...self::CIVIL_TABLES, 'users',
        'citizen_service_requests', 'citizen_documents', 'mock_face_attempts',
    ];

    public const CIVIL_TABLES = [
        'mock_civil_persons', 'mock_civil_families', 'mock_identity_cards', 'mock_birth_records', 'mock_civil_documents',
    ];

    private const NAME_REPLACEMENTS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي',
        'َ' => '', 'ً' => '', 'ُ' => '', 'ٌ' => '', 'ِ' => '', 'ٍ' => '', 'ّ' => '', 'ْ' => '', 'ـ' => '',
        'ٔ' => '', 'ٕ' => '',
    ];

    public function people(array $filters): LengthAwarePaginator
    {
        $query = $this->filteredPeople($filters);
        if (isset($filters['q'])) {
            $query->where(function (Builder $query) use ($filters) {
                $this->matchName($query, $filters['q'])
                    ->orWhere('person.national_id', $filters['q'])
                    ->orWhere('family.reference', $filters['q']);
            });
        }
        return $query->orderBy('person.id')->paginate(20)->appends($filters);
    }

    public function nameSuggestions(array $filters): Collection
    {
        $name = strtr($filters['q'] ?? '', self::NAME_REPLACEMENTS);
        if (mb_strlen($name) < 2) {
            return collect();
        }

        return $this->matchName($this->filteredPeople($filters), $name)
            ->select('person.id', 'person.name')
            ->orderBy('person.name')->orderBy('person.id')->get();
    }

    public function simulationRecords(string $term, bool $byNumber): Collection
    {
        $query = $this->civilPeopleQuery();
        if ($byNumber) {
            $query->where('person.national_id', $term);
        } else {
            $this->matchName($query, $term, exact: true);
        }

        return $query->orderBy('person.id')->get()->map(fn ($person) => $this->civilProfile($person));
    }

    public function simulationNames(string $term): Collection
    {
        return $this->matchName($this->civilPeopleQuery(), $term)
            ->select('person.name')->distinct()->orderBy('person.name')->pluck('person.name');
    }

    private function matchName(Builder $query, string $name, bool $exact = false): Builder
    {
        $name = strtr($name, self::NAME_REPLACEMENTS);
        if ($name === '') {
            return $query->whereRaw('1 = 0');
        }

        $column = 'person.name';
        $bindings = [];
        foreach (self::NAME_REPLACEMENTS as $from => $to) {
            $column = "REPLACE($column, ?, ?)";
            array_push($bindings, $from, $to);
        }
        $literal = strtr($name, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $bindings[] = $exact ? $literal : '%'.$literal.'%';

        return $query->whereRaw("$column LIKE ? ESCAPE '!'", $bindings);
    }

    private function filteredPeople(array $filters): Builder
    {
        $query = $this->peopleQuery();
        match ($filters['account'] ?? null) {
            'linked' => $query->whereNotNull('account.id'),
            'unlinked' => $query->whereNull('account.id'),
            default => null,
        };
        if (isset($filters['record'])) {
            $query->where('person.complete', $filters['record'] === 'complete');
        }

        return $query;
    }

    public function profile(int $id, User $actor): array
    {
        $person = $this->peopleQuery()->where('person.id', $id)->first();
        abort_unless($person, 404);

        $requests = collect();
        $officialDocuments = collect();
        $identityStatus = null;
        $faceStatus = null;

        if ($person->linked) {
            $account = User::where('mock_person_id', $id)->first([
                'id', 'role', 'is_active', 'registration_status', 'activation_source',
                'national_id', 'identity_verified_at', 'account_activated_at',
            ]);

            if ($account) {
                $gate = Gate::forUser($actor);
                $requests = CitizenServiceRequest::where('user_id', $account->id)->latest('id')
                    ->get(['id', 'user_id', 'government_service_id', 'reference', 'service_code', 'status'])
                    ->filter(fn (CitizenServiceRequest $request) => $gate->allows('track', $request));
                $officialDocuments = CitizenDocument::where('user_id', $account->id)->latest('id')
                    ->get(['id', 'user_id', 'government_service_id', 'document_type', 'document_number', 'status'])
                    ->filter(fn (CitizenDocument $document) => $gate->allows('view', $document));

                if ($gate->allows('activateCitizen', $account)) {
                    $identityStatus = $account->accountStatusLabel();
                    $faceStatus = match (DB::table('mock_face_attempts')->where('user_id', $account->id)->latest('id')->value('result')) {
                        'MATCH' => 'تمت المطابقة تجريبيًا',
                        'REVIEW' => 'تحتاج إلى مراجعة',
                        'NO_MATCH', 'NO_REFERENCE_IMAGE', 'IMAGE_QUALITY_FAILED', 'LIVENESS_FAILED' => 'لم تكتمل المطابقة',
                        default => 'لا توجد نتيجة مطابقة مسجلة',
                    };
                }
            }
        }

        return $this->civilProfile($person) + compact('requests', 'officialDocuments', 'identityStatus', 'faceStatus');
    }

    private function civilProfile(object $person): array
    {
        $members = $person->family_id === null ? collect() : DB::table('mock_civil_persons')
            ->where('family_id', $person->family_id)->orderBy('id')->get(['id', 'name', 'family_role']);
        $card = DB::table('mock_identity_cards')->where('person_id', $person->id)->first(['number', 'status']);
        $birth = DB::table('mock_birth_records')->where('person_id', $person->id)->first(['number']);
        $civilDocuments = DB::table('mock_civil_documents')->where('person_id', $person->id)->orderByDesc('id')
            ->get(['document_type', 'document_number', 'status', 'issued_place', 'issued_at']);

        return compact('person', 'members', 'card', 'birth', 'civilDocuments');
    }

    private function peopleQuery(): Builder
    {
        return $this->civilPeopleQuery()
            ->leftJoin('users as account', 'account.mock_person_id', '=', 'person.id')
            ->selectRaw('CASE WHEN account.id IS NULL THEN 0 ELSE 1 END AS linked');
    }

    private function civilPeopleQuery(): Builder
    {
        return DB::table('mock_civil_persons as person')
            ->leftJoin('mock_civil_families as family', 'family.id', '=', 'person.family_id')
            ->select('person.id', 'person.name', 'person.national_id', 'person.birth_date', 'person.birth_place',
                'person.family_id', 'person.family_role', 'person.father_name', 'person.mother_name', 'person.complete',
                'family.reference as family_reference');
    }
}
