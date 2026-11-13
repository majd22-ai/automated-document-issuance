<?php

namespace App\Http\Controllers;

use App\Http\Requests\DemoCivilDocumentRequest;
use App\Http\Requests\DemoCivilPersonRequest;
use App\Models\CitizenServiceRequest;
use App\Models\DemoCivilDocument;
use App\Models\DemoCivilFamily;
use App\Models\DemoCivilFamilyMember;
use App\Models\DemoCivilPerson;
use App\Models\DemoCivilRecord;
use App\Models\DemoCivilRecordParty;
use App\Models\DemoCivilRegistryAudit;
use App\Services\DemoCivilRegistry\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DemoCivilRegistryController extends Controller
{
    private const SECTIONS = [
        'birth' => 'سجل المواليد',
        'marriage' => 'سجل الزواج',
        'divorce' => 'سجل الطلاق',
        'death' => 'سجل الوفيات',
        'identity_card' => 'سجل البطاقات الشخصية',
        'family' => 'سجل الأسرة',
        'correction' => 'سجل التصحيحات والتعديلات',
        'restriction' => 'سجل القيود المدنية',
    ];

    public function search(Request $request, AuditLogger $audit): JsonResponse
    {
        $this->authorizeRead($request);
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'mode' => ['required', Rule::in(['name', 'identifier', 'document'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'request_id' => ['nullable', 'integer', 'exists:citizen_service_requests,id'],
        ]);
        $this->authorizeRequestContext($request, $data['request_id'] ?? null);
        $term = trim($data['q']);
        $page = (int) ($data['page'] ?? 1);
        $people = DemoCivilPerson::query()->where('is_synthetic', true)->whereNull('archived_at')
            ->withCount([
                'records as active_primary_records_count' => fn (Builder $query) => $query->whereNull('archived_at'),
                'parties as active_party_records_count' => fn (Builder $query) => $query->whereHas('record', fn (Builder $record) => $record->whereNull('archived_at')->whereColumn('demo_civil_records.person_id', '!=', 'demo_civil_people.id')),
                'documents as active_documents_count' => fn (Builder $query) => $query->whereNull('archived_at')->where(fn (Builder $document) => $document->whereNull('record_id')->orWhereHas('record', fn (Builder $record) => $record->whereNull('archived_at'))),
                'parties as active_party_documents_count' => fn (Builder $query) => $query->whereHas('record', fn (Builder $record) => $record->whereNull('archived_at')->whereColumn('demo_civil_records.person_id', '!=', 'demo_civil_people.id')->whereHas('documents', fn (Builder $documents) => $documents->whereNull('archived_at'))),
            ])
            ->when($data['mode'] === 'name', function (Builder $query) use ($term) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
                $query->whereRaw("LOWER(full_name) LIKE ? ESCAPE '!'", [$pattern]);
            })
            ->when($data['mode'] === 'identifier', fn (Builder $query) => $query->where('demo_identifier', $term))
            ->when($data['mode'] === 'document', fn (Builder $query) => $query->where(function (Builder $nested) use ($term) {
                $nested->whereHas('documents', fn (Builder $documents) => $documents->where('demo_identifier', $term)->whereNull('archived_at')->where(fn (Builder $document) => $document->whereNull('record_id')->orWhereHas('record', fn (Builder $record) => $record->whereNull('archived_at'))))
                    ->orWhereHas('records', fn (Builder $records) => $records->where('demo_identifier', $term)->whereNull('archived_at'))
                    ->orWhereHas('parties.record', fn (Builder $record) => $record->whereNull('archived_at')->whereHas('documents', fn (Builder $documents) => $documents->where('demo_identifier', $term)->whereNull('archived_at')));
            }))
            ->orderBy('full_name')->skip(($page - 1) * 20)->limit(21)->get();
        $hasMore = $people->count() > 20;
        $results = $people->take(20)->map(fn (DemoCivilPerson $person) => [
            'id' => $person->id,
            'demo_identifier' => $person->demo_identifier,
            'full_name' => $person->full_name,
            'gender' => $person->gender,
            'birth_date' => $person->birth_date->format('Y-m-d'),
            'records_count' => $person->active_primary_records_count + $person->active_party_records_count,
            'documents_count' => $person->active_documents_count + $person->active_party_documents_count,
        ])->values();
        $audit->record($request, 'registry.search', 'success', metadata: ['mode' => $data['mode'], 'result_count' => $results->count(), 'has_more' => $hasMore]);

        return $this->privateJson(['results' => $results, 'has_more' => $hasMore, 'page' => $page]);
    }

    public function show(Request $request, DemoCivilPerson $person, AuditLogger $audit): JsonResponse
    {
        $this->authorizeRead($request);
        $this->authorizeRequestContext($request, $request->query('request_id'));
        Gate::authorize('view', $person);
        $person->load(['familyMemberships.family.members' => fn ($query) => $query->where('demo_civil_people.is_synthetic', true)->whereNull('demo_civil_people.archived_at')->wherePivot('status', 'active')]);
        $counts = DemoCivilRecord::query()->whereNull('archived_at')->where(function (Builder $query) use ($person) {
            $query->where('person_id', $person->id)->orWhereHas('parties', fn (Builder $parties) => $parties->where('person_id', $person->id));
        })->select('record_type', DB::raw('COUNT(*) as aggregate'))->groupBy('record_type')->pluck('aggregate', 'record_type');
        $families = $person->familyMemberships->filter(fn ($membership) => $membership->family && ! $membership->family->archived_at)->map(fn ($membership) => [
            'id' => $membership->family->id,
            'demo_identifier' => $membership->family->demo_identifier,
            'relationship' => $membership->relationship,
            'status' => $membership->status,
            'members' => $membership->family->members->map(fn (DemoCivilPerson $member) => [
                'id' => $member->id,
                'demo_identifier' => $member->demo_identifier,
                'full_name' => $member->full_name,
                'relationship' => $member->pivot->relationship,
                'status' => $member->pivot->status,
            ])->values(),
        ])->values();
        $audit->record($request, 'registry.person.open', personId: $person->id);

        return $this->privateJson([
            'person' => [
                'id' => $person->id,
                'demo_identifier' => $person->demo_identifier,
                'full_name' => $person->full_name,
                'gender' => $person->gender,
                'birth_date' => $person->birth_date->format('Y-m-d'),
                'birth_place' => $person->birth_place,
                'marital_status' => $person->marital_status,
                'synthetic_address' => $person->synthetic_address,
                'photo_url' => $person->photo_path ? $this->photoUrl($request, $person) : null,
                'families' => $families,
                'documents' => $person->documents()->whereNull('record_id')->whereNull('archived_at')->where('is_synthetic', true)
                    ->orderBy('id')->limit(50)->get()->map(fn (DemoCivilDocument $document) => $this->documentSummary($request, $document))->values(),
                'sections' => collect(self::SECTIONS)->map(fn ($label, $key) => [
                    'key' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0),
                ])->values(),
            ],
        ]);
    }

    public function section(Request $request, DemoCivilPerson $person, string $section, AuditLogger $audit): JsonResponse
    {
        $this->authorizeRead($request);
        $this->authorizeRequestContext($request, $request->query('request_id'));
        Gate::authorize('view', $person);
        abort_unless(array_key_exists($section, self::SECTIONS), 404);
        $records = DemoCivilRecord::query()->where('record_type', $section)->whereNull('archived_at')->where(function (Builder $query) use ($person) {
            $query->where('person_id', $person->id)->orWhereHas('parties', fn (Builder $parties) => $parties->where('person_id', $person->id));
        })
            ->with(['parties.person:id,demo_identifier,full_name', 'relatedRecord:id,demo_identifier,record_type', 'documents' => fn ($query) => $query->whereNull('archived_at')])
            ->orderByDesc('occurred_on')->orderByDesc('id')->limit(50)->get()
            ->map(fn (DemoCivilRecord $record) => [
                'id' => $record->id,
                'demo_identifier' => $record->demo_identifier,
                'occurred_on' => $record->occurred_on?->format('Y-m-d'),
                'place' => $record->place,
                'status' => $record->status,
                'details' => $record->details ?? [],
                'related_record_id' => $record->related_record_id,
                'related_record' => $record->relatedRecord ? ['id' => $record->relatedRecord->id, 'demo_identifier' => $record->relatedRecord->demo_identifier, 'type' => $record->relatedRecord->record_type] : null,
                'parties' => $record->parties->map(fn (DemoCivilRecordParty $party) => [
                    'role' => $party->party_role,
                    'person_id' => $party->person?->id,
                    'demo_identifier' => $party->person?->demo_identifier,
                    'full_name' => $party->person?->full_name,
                ])->values(),
                'documents' => $record->documents->map(fn (DemoCivilDocument $document) => $this->documentSummary($request, $document))->values(),
            ])->values();
        $audit->record($request, 'registry.section.open', 'success', personId: $person->id, metadata: ['section' => $section, 'result_count' => $records->count()]);

        return $this->privateJson(['section' => $section, 'label' => self::SECTIONS[$section], 'records' => $records]);
    }

    public function photo(Request $request, DemoCivilPerson $person, AuditLogger $audit): StreamedResponse
    {
        $this->authorizeRead($request);
        $this->authorizeRequestContext($request, $request->query('request_id'));
        Gate::authorize('view', $person);
        abort_unless($person->photo_path && $person->photo_byte_size > 0 && $person->photo_byte_size <= 4 * 1024 * 1024
            && preg_match('~^people/[a-f0-9-]{36}\.(png|jpg)$|^fixtures/portrait\.svg$~D', $person->photo_path), 404);
        $disk = Storage::disk('demo-civil-registry');
        abort_unless($disk->exists($person->photo_path) && $disk->size($person->photo_path) === $person->photo_byte_size, 404);
        $stream = $disk->readStream($person->photo_path);
        abort_unless(is_resource($stream), 404);
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        rewind($stream);
        abort_unless($person->photo_sha256 && hash_equals($person->photo_sha256, hash_final($hash)), 404);
        $mime = str_ends_with($person->photo_path, '.svg') ? 'image/svg+xml' : (str_ends_with($person->photo_path, '.png') ? 'image/png' : 'image/jpeg');
        $audit->record($request, 'registry.photo.view', personId: $person->id);

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, $this->fileHeaders($mime, 'inline', 'photo-'.$person->id.'.'.pathinfo($person->photo_path, PATHINFO_EXTENSION)));
    }

    public function document(Request $request, DemoCivilDocument $document, AuditLogger $audit): StreamedResponse
    {
        $this->authorizeRead($request);
        $this->authorizeRequestContext($request, $request->query('request_id'));
        abort_unless(! $document->archived_at && $document->is_synthetic, 404);
        Gate::authorize('view', $document->person);
        abort_unless(! $document->record || ! $document->record->archived_at, 404);
        abort_unless(in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/svg+xml', 'application/pdf'], true) && $document->intact(), 404);
        $stream = Storage::disk('demo-civil-registry')->readStream($document->storage_path);
        abort_unless(is_resource($stream), 404);
        $disposition = $document->mime_type === 'application/pdf' ? 'attachment' : 'inline';
        $audit->record($request, 'registry.document.view', personId: $document->person_id, recordId: $document->record_id, documentId: $document->id);

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, $this->fileHeaders($document->mime_type, $disposition, 'demo-document-'.$document->id.'.'.$this->extension($document->mime_type)));
    }

    public function adminIndex(Request $request): \Illuminate\Http\Response
    {
        $this->authorizeManage();
        $people = DemoCivilPerson::whereNull('archived_at')->orderBy('demo_identifier')->paginate(10, ['*'], 'people_page');
        $families = DemoCivilFamily::with('head')->whereNull('archived_at')->orderBy('demo_identifier')->paginate(10, ['*'], 'families_page');
        $records = DemoCivilRecord::with(['person', 'parties'])->whereNull('archived_at')->latest('id')->paginate(10, ['*'], 'records_page');
        $documents = DemoCivilDocument::with('person')->whereNull('archived_at')->latest('id')->paginate(10, ['*'], 'documents_page');
        $personOptions = DemoCivilPerson::whereNull('archived_at')->orderBy('demo_identifier')->get(['id', 'demo_identifier', 'full_name']);
        $familyOptions = DemoCivilFamily::whereNull('archived_at')->orderBy('demo_identifier')->get(['id', 'demo_identifier']);
        $recordOptions = DemoCivilRecord::whereNull('archived_at')->orderByDesc('occurred_on')->get(['id', 'demo_identifier']);
        $marriageOptions = DemoCivilRecord::where('record_type', 'marriage')->whereNull('archived_at')->orderByDesc('occurred_on')->get(['id', 'demo_identifier']);

        return response()->view('admin.demo-civil-registry.index', compact('people', 'families', 'records', 'documents', 'personOptions', 'familyOptions', 'recordOptions', 'marriageOptions'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function storePerson(DemoCivilPersonRequest $request, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $person = new DemoCivilPerson;
        $person->forceFill($request->validated())->save();
        $audit->record($request, 'registry.person.create', personId: $person->id);

        return back()->with('status', 'أضيف ملف المواطن التجريبي.');
    }

    public function updatePerson(DemoCivilPersonRequest $request, DemoCivilPerson $person, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(! $person->archived_at && $person->is_synthetic, 404);
        $person->forceFill($request->validated())->save();
        $audit->record($request, 'registry.person.update', personId: $person->id);

        return back()->with('status', 'حُدّث ملف المواطن التجريبي.');
    }

    public function archivePerson(Request $request, DemoCivilPerson $person, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(! $person->archived_at && $person->is_synthetic, 404);
        $person->forceFill(['archived_at' => now()])->save();
        $audit->record($request, 'registry.person.archive', personId: $person->id);

        return back()->with('status', 'أُرشف ملف المواطن التجريبي دون حذف أثره.');
    }

    public function storePhoto(Request $request, DemoCivilPerson $person, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(! $person->archived_at && $person->is_synthetic, 404);
        $data = $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096']]);
        $file = $data['photo'];
        $mime = $file->getMimeType();
        abort_unless(in_array($mime, ['image/jpeg', 'image/png'], true), 422);
        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $path = 'people/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk('demo-civil-registry');
        $disk->put($path, file_get_contents($file->getRealPath()));
        $oldPath = $person->photo_path;
        try {
            $person->forceFill(['photo_path' => $path, 'photo_sha256' => hash_file('sha256', $file->getRealPath()), 'photo_byte_size' => $file->getSize()])->save();
        } catch (\Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
        if ($oldPath && $oldPath !== 'fixtures/portrait.svg' && $oldPath !== $path && preg_match('~^people/[a-f0-9-]{36}\.(png|jpg)$~D', $oldPath)) {
            $disk->delete($oldPath);
        }
        $audit->record($request, 'registry.photo.upload', personId: $person->id);

        return back()->with('status', 'رُفعت الصورة التجريبية إلى التخزين الخاص.');
    }

    public function storeFamily(Request $request, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate([
            'demo_identifier' => ['required', 'regex:/^DEMO-FAM-[0-9]{6}$/D', 'unique:demo_civil_families,demo_identifier'],
            'head_person_id' => ['required', 'integer', 'exists:demo_civil_people,id'],
        ]);
        $head = DemoCivilPerson::whereNull('archived_at')->where('is_synthetic', true)->findOrFail($data['head_person_id']);
        $family = new DemoCivilFamily;
        $family->forceFill(['demo_identifier' => $data['demo_identifier'], 'head_person_id' => $head->id])->save();
        DemoCivilFamilyMember::updateOrCreate(['family_id' => $family->id, 'person_id' => $head->id], ['relationship' => 'head', 'status' => 'active']);
        $audit->record($request, 'registry.family.create', personId: $head->id, metadata: ['family_id' => $family->id]);

        return back()->with('status', 'أُنشئت الأسرة التجريبية وربط رب الأسرة بها.');
    }

    public function updateFamily(Request $request, DemoCivilFamily $family, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(! $family->archived_at && $family->is_synthetic, 404);
        $data = $request->validate([
            'demo_identifier' => ['required', 'regex:/^DEMO-FAM-[0-9]{6}$/D', Rule::unique('demo_civil_families', 'demo_identifier')->ignore($family->id)],
            'head_person_id' => ['required', 'integer', 'exists:demo_civil_people,id'],
        ]);
        $head = DemoCivilPerson::whereNull('archived_at')->where('is_synthetic', true)->findOrFail($data['head_person_id']);
        DB::transaction(function () use ($family, $data, $head) {
            $previousHeadId = $family->head_person_id;
            $family->forceFill(['demo_identifier' => $data['demo_identifier'], 'head_person_id' => $head->id])->save();
            if ($previousHeadId && (int) $previousHeadId !== (int) $head->id) {
                DemoCivilFamilyMember::where('family_id', $family->id)->where('person_id', $previousHeadId)->update(['relationship' => 'other']);
            }
            DemoCivilFamilyMember::updateOrCreate(['family_id' => $family->id, 'person_id' => $head->id], ['relationship' => 'head', 'status' => 'active']);
        });
        $audit->record($request, 'registry.family.update', personId: $head->id, metadata: ['family_id' => $family->id]);

        return back()->with('status', 'حُدّثت الأسرة التجريبية.');
    }

    public function archiveFamily(Request $request, DemoCivilFamily $family, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(! $family->archived_at && $family->is_synthetic, 404);
        $family->forceFill(['archived_at' => now()])->save();
        $family->memberships()->update(['status' => 'archived']);
        $audit->record($request, 'registry.family.archive', metadata: ['family_id' => $family->id]);

        return back()->with('status', 'أُرشفت الأسرة التجريبية.');
    }

    public function storeFamilyMember(Request $request, DemoCivilFamily $family, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(! $family->archived_at && $family->is_synthetic, 404);
        $data = $request->validate([
            'person_id' => ['required', 'integer', 'exists:demo_civil_people,id'],
            'relationship' => ['required', Rule::in(['head', 'spouse', 'child', 'parent', 'sibling', 'other'])],
            'status' => ['required', Rule::in(['active', 'former', 'deceased'])],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $person = DemoCivilPerson::whereNull('archived_at')->where('is_synthetic', true)->findOrFail($data['person_id']);
        if (((int) $family->head_person_id === (int) $person->id && ($data['relationship'] !== 'head' || $data['status'] !== 'active'))
            || ((int) $family->head_person_id !== (int) $person->id && $data['relationship'] === 'head')) {
            throw ValidationException::withMessages(['relationship' => 'غيّر رب الأسرة من نموذج تحديث الأسرة لضمان اتساق العضوية.']);
        }
        DemoCivilFamilyMember::updateOrCreate(['family_id' => $family->id, 'person_id' => $person->id], $data);
        $audit->record($request, 'registry.family.member.add', personId: $person->id, metadata: ['family_id' => $family->id]);

        return back()->with('status', 'رُبط المواطن بالأسرة التجريبية.');
    }

    public function storeRecord(Request $request, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        [$data, $parties] = $this->validatedRecord($request);
        $record = DB::transaction(function () use ($data, $parties) {
            $record = new DemoCivilRecord;
            $record->forceFill($data)->save();
            $this->syncParties($record, $parties);

            return $record;
        });
        $audit->record($request, 'registry.record.create', personId: $record->person_id, recordId: $record->id, metadata: ['type' => $record->record_type]);

        return back()->with('status', 'أُضيفت الواقعة التجريبية.');
    }

    public function updateRecord(Request $request, DemoCivilRecord $record, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(! $record->archived_at && $record->is_synthetic, 404);
        [$data, $parties] = $this->validatedRecord($request, $record);
        DB::transaction(function () use ($record, $data, $parties) {
            $record->forceFill($data)->save();
            $this->syncParties($record, $parties);
        });
        $audit->record($request, 'registry.record.update', personId: $record->person_id, recordId: $record->id, metadata: ['type' => $record->record_type]);

        return back()->with('status', 'حُدّثت الواقعة التجريبية.');
    }

    public function archiveRecord(Request $request, DemoCivilRecord $record, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(! $record->archived_at && $record->is_synthetic, 404);
        $record->forceFill(['archived_at' => now()])->save();
        $audit->record($request, 'registry.record.archive', personId: $record->person_id, recordId: $record->id);

        return back()->with('status', 'أُرشفت الواقعة التجريبية.');
    }

    public function storeDocument(DemoCivilDocumentRequest $request, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validated();
        $person = DemoCivilPerson::whereNull('archived_at')->where('is_synthetic', true)->findOrFail($data['person_id']);
        $record = isset($data['record_id']) ? DemoCivilRecord::where('person_id', $person->id)->whereNull('archived_at')->findOrFail($data['record_id']) : null;
        $file = $data['file'];
        $mime = $file->getMimeType();
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true), 422);
        $path = $this->storePrivateFile($file, 'documents', $mime);
        try {
            $document = new DemoCivilDocument;
            $document->forceFill([
                'person_id' => $person->id,
                'record_id' => $record?->id,
                'demo_identifier' => 'DEMO-DOC-'.strtoupper(Str::random(8)).'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'label' => $data['label'],
                'storage_path' => $path,
                'mime_type' => $mime,
                'byte_size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
            ])->save();
        } catch (\Throwable $exception) {
            Storage::disk('demo-civil-registry')->delete($path);
            throw $exception;
        }
        $audit->record($request, 'registry.document.create', personId: $person->id, recordId: $record?->id, documentId: $document->id);

        return back()->with('status', 'رُفعت الوثيقة التجريبية إلى التخزين الخاص.');
    }

    public function replaceDocument(Request $request, DemoCivilDocument $document, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(! $document->archived_at && $document->is_synthetic, 404);
        abort_unless($document->safeStoragePath(), 404);
        $data = $request->validate(['label' => ['required', 'string', 'max:160'], 'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);
        $file = $data['file'];
        $mime = $file->getMimeType();
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true), 422);
        $path = $this->storePrivateFile($file, 'documents', $mime);
        $oldPath = $document->storage_path;
        $deleteOldUpload = $document->uploadedStoragePath();
        try {
            $document->forceFill(['label' => $data['label'], 'storage_path' => $path, 'mime_type' => $mime, 'byte_size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())])->save();
        } catch (\Throwable $exception) {
            Storage::disk('demo-civil-registry')->delete($path);
            throw $exception;
        }
        if ($deleteOldUpload) {
            Storage::disk('demo-civil-registry')->delete($oldPath);
        }
        $audit->record($request, 'registry.document.replace', personId: $document->person_id, recordId: $document->record_id, documentId: $document->id);

        return back()->with('status', 'استُبدلت الوثيقة التجريبية وحُذف الملف السابق.');
    }

    public function archiveDocument(Request $request, DemoCivilDocument $document, AuditLogger $audit): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(! $document->archived_at && $document->is_synthetic, 404);
        abort_unless($document->safeStoragePath(), 404);
        $path = $document->storage_path;
        $document->forceFill(['archived_at' => now()])->save();
        $audit->record($request, 'registry.document.archive', personId: $document->person_id, recordId: $document->record_id, documentId: $document->id);
        if ($document->uploadedStoragePath()) {
            Storage::disk('demo-civil-registry')->delete($path);
        }

        return back()->with('status', 'أُرشفت الوثيقة وحُذف ملفها من التخزين الخاص.');
    }

    public function audits(Request $request): \Illuminate\Http\Response
    {
        $this->authorizeManage();
        $audits = DemoCivilRegistryAudit::with(['actor:id,name'])->latest('id')->paginate(30);

        return response()->view('admin.demo-civil-registry.audits', compact('audits'))->header('Cache-Control', 'no-store, private');
    }

    private function authorizeRead(Request $request): void
    {
        abort_unless(config('demo_civil_registry.enabled') === true, 404);
        Gate::authorize('viewAny', DemoCivilPerson::class);
    }

    private function authorizeRequestContext(Request $request, mixed $requestId): void
    {
        if ($requestId === null || $requestId === '') {
            return;
        }
        $id = filter_var($requestId, FILTER_VALIDATE_INT);
        abort_unless($id, 422);
        $application = CitizenServiceRequest::findOrFail($id);
        if ($request->user()->role === 'employee') {
            Gate::authorize('viewReview', $application);
        }
        $request->attributes->set('demo_registry_request_id', $application->id);
    }

    private function authorizeManage(): void
    {
        abort_unless(config('demo_civil_registry.enabled') === true, 404);
        Gate::authorize('manage', DemoCivilPerson::class);
    }

    private function privateJson(array $payload): JsonResponse
    {
        return response()->json($payload)->header('Cache-Control', 'private, no-store');
    }

    private function photoUrl(Request $request, DemoCivilPerson $person): string
    {
        $route = $request->user()->role === 'admin' ? 'admin.demo-civil-registry.people.photo' : 'employee.demo-civil-registry.people.photo';
        $url = route($route, $person);

        return $request->attributes->get('demo_registry_request_id') ? $url.'?request_id='.$request->attributes->get('demo_registry_request_id') : $url;
    }

    private function documentSummary(Request $request, DemoCivilDocument $document): array
    {
        $route = $request->user()->role === 'admin' ? 'admin.demo-civil-registry.documents.show' : 'employee.demo-civil-registry.documents.show';
        $url = route($route, $document);
        if ($request->attributes->get('demo_registry_request_id')) {
            $url .= '?request_id='.$request->attributes->get('demo_registry_request_id');
        }

        return ['id' => $document->id, 'demo_identifier' => $document->demo_identifier, 'label' => $document->label, 'mime_type' => $document->mime_type, 'url' => $url];
    }

    private function fileHeaders(string $mime, string $disposition, string $filename): array
    {
        return [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Referrer-Policy' => 'no-referrer',
        ];
    }

    private function extension(string $mime): string
    {
        return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/svg+xml' => 'svg', 'application/pdf' => 'pdf'][$mime] ?? 'bin';
    }

    private function storePrivateFile(\Illuminate\Http\UploadedFile $file, string $directory, string $mime): string
    {
        $extension = $this->extension($mime);
        $path = $directory.'/'.Str::uuid().'.'.$extension;
        Storage::disk('demo-civil-registry')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    private function validatedRecord(Request $request, ?DemoCivilRecord $record = null): array
    {
        $data = $request->validate([
            'record_type' => ['required', Rule::in(DemoCivilRecord::TYPES)],
            'demo_identifier' => ['required', 'string', 'regex:/^DEMO-[A-Z0-9-]{3,54}$/D', Rule::unique('demo_civil_records', 'demo_identifier')->ignore($record?->id)],
            'person_id' => ['required', 'integer', 'exists:demo_civil_people,id'],
            'family_id' => ['nullable', 'integer', 'exists:demo_civil_families,id'],
            'related_record_id' => ['nullable', 'integer', 'exists:demo_civil_records,id'],
            'occurred_on' => ['nullable', 'date', 'before_or_equal:today'],
            'place' => ['nullable', 'string', 'max:180'],
            'status' => ['required', Rule::in(['active', 'dissolved', 'cancelled', 'corrected', 'pending', 'released'])],
            'details_json' => ['nullable', 'string', 'max:8000'],
            'parties_json' => ['required', 'json', 'max:4000'],
        ]);
        $person = DemoCivilPerson::whereNull('archived_at')->where('is_synthetic', true)->findOrFail($data['person_id']);
        $family = isset($data['family_id']) ? DemoCivilFamily::whereNull('archived_at')->where('is_synthetic', true)->findOrFail($data['family_id']) : null;
        $relatedRecord = isset($data['related_record_id']) ? DemoCivilRecord::whereNull('archived_at')->where('is_synthetic', true)->findOrFail($data['related_record_id']) : null;
        $details = [];
        if (filled($data['details_json'] ?? null)) {
            try {
                $details = json_decode($data['details_json'], true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages(['details_json' => 'بيانات السجل يجب أن تكون JSON صالحًا.']);
            }
            if (! is_array($details) || array_is_list($details)) {
                throw ValidationException::withMessages(['details_json' => 'أدخل كائن JSON يحتوي حقول السجل.']);
            }
        }
        try {
            $parties = json_decode($data['parties_json'], true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['parties_json' => 'بيانات الأطراف يجب أن تكون JSON صالحًا.']);
        }
        if (! is_array($parties) || ! array_is_list($parties) || count($parties) > 5) {
            throw ValidationException::withMessages(['parties_json' => 'أدخل قائمة أطراف صالحة لا تتجاوز خمسة أشخاص.']);
        }
        $allowedRoles = [
            'birth' => ['child', 'father', 'mother'], 'marriage' => ['husband', 'wife'], 'divorce' => ['husband', 'wife'],
            'death' => ['deceased'], 'identity_card' => ['holder'], 'family' => ['head'],
            'correction' => ['subject'], 'restriction' => ['subject'],
        ][$data['record_type']];
        $cleanParties = [];
        foreach ($parties as $party) {
            if (! is_array($party) || ! isset($party['person_id'], $party['role']) || ! in_array($party['role'], $allowedRoles, true)) {
                throw ValidationException::withMessages(['parties_json' => 'يوجد طرف أو دور غير صالح لهذا النوع من السجلات.']);
            }
            $partyPerson = DemoCivilPerson::whereNull('archived_at')->where('is_synthetic', true)->find($party['person_id']);
            if (! $partyPerson) {
                throw ValidationException::withMessages(['parties_json' => 'يجب أن تكون جميع الأطراف مواطنين تجريبيين نشطين.']);
            }
            $cleanParties[] = ['person_id' => $partyPerson->id, 'party_role' => $party['role']];
        }
        $roles = array_column($cleanParties, 'party_role');
        $requiredRoles = match ($data['record_type']) {
            'birth' => ['child'], 'marriage', 'divorce' => ['husband', 'wife'],
            'death' => ['deceased'], 'identity_card', 'family', 'correction', 'restriction' => [$data['record_type'] === 'identity_card' ? 'holder' : ($data['record_type'] === 'family' ? 'head' : 'subject')],
        };
        if (array_diff($requiredRoles, $roles) || count($roles) !== count(array_unique($roles))) {
            throw ValidationException::withMessages(['parties_json' => 'الأدوار المطلوبة لهذا السجل مفقودة أو مكررة.']);
        }
        $requiredDetails = [
            'birth' => ['child_name', 'gender', 'father_name', 'mother_name', 'birth_certificate_number', 'registered_on'],
            'marriage' => ['contract_number', 'wife_identifier'],
            'divorce' => ['divorce_type', 'document_number'],
            'death' => ['certificate_number'],
            'identity_card' => ['card_number', 'issued_on', 'expires_on', 'issuer'],
            'family' => ['family_number'],
            'correction' => ['request_number', 'field', 'previous_value', 'new_value', 'reason'],
            'restriction' => ['restriction_type', 'description', 'authority'],
        ][$data['record_type']];
        foreach ($requiredDetails as $detailKey) {
            if (! isset($details[$detailKey]) || ! is_scalar($details[$detailKey]) || trim((string) $details[$detailKey]) === '') {
                throw ValidationException::withMessages(['details_json' => 'الحقل التجريبي "'.$detailKey.'" مطلوب لهذا النوع من السجلات.']);
            }
        }
        foreach (['registered_on', 'issued_on', 'expires_on'] as $dateKey) {
            if (isset($details[$dateKey]) && (! is_string($details[$dateKey]) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $details[$dateKey]) || ! checkdate((int) substr($details[$dateKey], 5, 2), (int) substr($details[$dateKey], 8, 2), (int) substr($details[$dateKey], 0, 4)))) {
                throw ValidationException::withMessages(['details_json' => 'أحد تواريخ بيانات السجل غير صالح.']);
            }
        }
        $partyIds = array_column($cleanParties, 'person_id');
        $subjectRole = match ($data['record_type']) {
            'birth' => 'child', 'marriage', 'divorce' => null, 'death' => 'deceased', 'identity_card' => 'holder', 'family' => 'head', default => 'subject',
        };
        if (($subjectRole && (collect($cleanParties)->firstWhere('party_role', $subjectRole)['person_id'] ?? null) !== $person->id)
            || (in_array($data['record_type'], ['marriage', 'divorce'], true) && ! in_array($person->id, $partyIds, true))) {
            throw ValidationException::withMessages(['person_id' => 'الشخص الأساسي يجب أن يكون طرفاً في الواقعة.']);
        }
        if ($data['record_type'] === 'birth') {
            if (! $data['occurred_on'] || $data['occurred_on'] !== $person->birth_date->format('Y-m-d') || ($details['registered_on'] < $data['occurred_on'])) {
                throw ValidationException::withMessages(['occurred_on' => 'تاريخ واقعة الميلاد يجب أن يطابق ميلاد الشخص، وتاريخ التسجيل لا يسبقه.']);
            }
            if (count(array_unique($partyIds)) !== count($partyIds)) {
                throw ValidationException::withMessages(['parties_json' => 'يجب أن يكون المولود والأب والأم أشخاصاً تجريبيين مختلفين.']);
            }
            foreach ($cleanParties as $party) {
                if ($party['party_role'] !== 'child' && DemoCivilPerson::findOrFail($party['person_id'])->birth_date->copy()->addYears(18)->format('Y-m-d') > $data['occurred_on']) {
                    throw ValidationException::withMessages(['parties_json' => 'عمر الوالد التجريبي لا يتوافق مع تاريخ ميلاد المولود.']);
                }
            }
        }
        if ($data['record_type'] === 'death' && (! $data['occurred_on'] || $data['occurred_on'] < $person->birth_date->format('Y-m-d'))) {
            throw ValidationException::withMessages(['occurred_on' => 'تاريخ الوفاة مطلوب ولا يمكن أن يسبق تاريخ الميلاد.']);
        }
        if (in_array($data['record_type'], ['marriage', 'divorce'], true) && (count($cleanParties) !== 2 || count(array_unique($partyIds)) !== 2)) {
            throw ValidationException::withMessages(['parties_json' => 'الزواج والطلاق يتطلبان طرفين تجريبيين مختلفين.']);
        }
        if ($data['record_type'] === 'marriage') {
            if (! $data['occurred_on']) {
                throw ValidationException::withMessages(['occurred_on' => 'تاريخ واقعة الزواج مطلوب.']);
            }
            $wife = collect($cleanParties)->firstWhere('party_role', 'wife');
            $wifePerson = DemoCivilPerson::find($wife['person_id']);
            if ($details['wife_identifier'] !== $wifePerson->demo_identifier) {
                throw ValidationException::withMessages(['details_json' => 'معرف الزوجة يجب أن يطابق طرف الواقعة.']);
            }
            foreach ($cleanParties as $party) {
                $partyPerson = DemoCivilPerson::find($party['person_id']);
                if ($partyPerson->birth_date->copy()->addYears(18)->format('Y-m-d') > $data['occurred_on']) {
                    throw ValidationException::withMessages(['parties_json' => 'لا يمكن تسجيل واقعة زواج قبل بلوغ الطرفين 18 سنة في البيانات التجريبية.']);
                }
            }
        }
        if ($data['record_type'] === 'identity_card'
            && ($details['issued_on'] < $person->birth_date->format('Y-m-d') || $details['expires_on'] <= $details['issued_on'])) {
            throw ValidationException::withMessages(['details_json' => 'تواريخ البطاقة التجريبية غير منطقية.']);
        }
        if ($data['record_type'] === 'family' && ! $family) {
            throw ValidationException::withMessages(['family_id' => 'سجل الأسرة يتطلب أسرة تجريبية نشطة.']);
        }
        if ($data['record_type'] === 'family' && ! $family->memberships()->where('person_id', $person->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['person_id' => 'الشخص الأساسي يجب أن يكون عضواً نشطاً في الأسرة المحددة.']);
        }
        if ($data['record_type'] === 'divorce') {
            if (! $relatedRecord || $relatedRecord->record_type !== 'marriage') {
                throw ValidationException::withMessages(['related_record_id' => 'يجب ربط الطلاق بواقعة زواج تجريبية سابقة.']);
            }
            $marriagePeople = $relatedRecord->parties()->pluck('person_id')->all();
            if (count(array_intersect($partyIds, $marriagePeople)) < 2 || ! $data['occurred_on'] || ($relatedRecord->occurred_on && $data['occurred_on'] < $relatedRecord->occurred_on->format('Y-m-d'))) {
                throw ValidationException::withMessages(['related_record_id' => 'أطراف الطلاق أو تاريخه لا يتوافقان مع واقعة الزواج.']);
            }
            if ($relatedRecord->status === 'dissolved' && (! $record || $record->record_type !== 'divorce' || $record->related_record_id !== $relatedRecord->id)) {
                throw ValidationException::withMessages(['related_record_id' => 'واقعة الزواج المرتبطة مسجلة من قبل كمنتهية.']);
            }
        }
        $payload = [
            'record_type' => $data['record_type'], 'demo_identifier' => $data['demo_identifier'], 'person_id' => $person->id,
            'family_id' => $family?->id, 'related_record_id' => $relatedRecord?->id, 'occurred_on' => $data['occurred_on'] ?? null,
            'place' => $data['place'] ?? null, 'status' => $data['status'], 'details' => $details,
        ];

        return [$payload, $cleanParties];
    }

    private function syncParties(DemoCivilRecord $record, array $parties): void
    {
        $record->parties()->delete();
        foreach ($parties as $party) {
            (new DemoCivilRecordParty)->forceFill(['record_id' => $record->id, ...$party])->save();
        }
    }
}
