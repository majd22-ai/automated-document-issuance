<?php

namespace App\Http\Controllers;

use App\Models\NeighborhoodOfficial;
use App\Models\ResidenceDistrict;
use App\Models\User;
use App\Services\DemoNeighborhoods;
use App\Services\Oversight\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NeighborhoodOfficialController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'official_id' => ['nullable', 'integer', 'min:1']]);
        $search = Str::squish($filters['q'] ?? '');
        $selectedOfficial = $filters['official_id'] ?? null;
        $directory = $this->matchingNames($search)->with(['district.governorate', 'user']);
        $officials = $directory->when($selectedOfficial, fn ($query) => $query->whereKey($selectedOfficial))
            ->latest('id')->paginate(20)->appends(['q' => $search, 'official_id' => $selectedOfficial])->fragment('official-search');
        $employees = User::where('role', 'employee')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return response()->view('admin.neighborhood-officials', compact('officials', 'employees', 'search', 'selectedOfficial'))->header('Cache-Control', 'no-store, private');
    }

    public function suggestions(Request $request)
    {
        $search = Str::squish($request->validate(['q' => ['nullable', 'string', 'max:150']])['q'] ?? '');
        $matches = $search === '' ? [] : $this->matchingNames($search)->with('district.governorate')
            ->orderByRaw("CASE WHEN full_name LIKE ? ESCAPE '!' THEN 0 ELSE 1 END", [$this->escapeLike($search).'%'])
            ->orderBy('full_name')->orderBy('id')->limit(12)->get()
            ->map(fn ($official) => [
                'id' => $official->id,
                'name' => $official->full_name,
                'location' => implode(' — ', [$official->district->governorate->name, $official->district->name, $official->neighborhood]),
            ]);

        return response()->json($matches)->header('Cache-Control', 'no-store, private');
    }

    private function matchingNames(string $search): Builder
    {
        $directory = NeighborhoodOfficial::query();
        if ($search !== '') {
            foreach (explode(' ', $search) as $word) {
                $directory->whereRaw("full_name LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($word).'%']);
            }
        }

        return $directory;
    }

    private function escapeLike(string $value): string
    {
        return strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }

    public function generate(Request $request, DemoNeighborhoods $demo)
    {
        abort_unless(app()->environment(['local', 'testing']) && $request->user()->is_active, 403);
        $counts = $demo->generate($request->user());

        return back()->with('status', "أُضيف {$counts['created']} حيًا وعاقلًا تجريبيًا. السجلات الموجودة سابقًا {$counts['existing']}.");
    }

    public function demoPassword(Request $request, NeighborhoodOfficial $official)
    {
        abort_unless(app()->environment(['local', 'testing']) && $request->user()->is_active, 403);
        $account = $official->user;
        abort_unless($account && DemoNeighborhoods::isDemoAccount($account)
            && NeighborhoodOfficial::residenceOnly($account), 404);
        $password = Str::password(18);
        DB::transaction(function () use ($account, $request, $password) {
            $row = User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $row->forceFill(['password' => $password, 'auth_version' => $row->auth_version + 1, 'remember_token' => null])->save();
            app(AuditTrail::class)->record('demo_neighborhood_password_reset', User::class, $row->id, $request->user());
        });

        return back()->withFragment('demo-official-credentials')
            ->with('demoNeighborhoodCredentials', ['username' => $account->username, 'password' => $password]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150', 'regex:/^\S+(?:\s+\S+){3,}$/u'],
            'national_id' => ['required', 'string', 'regex:/^[0-9]{6,20}$/D'],
            'phone' => ['required', 'string', 'regex:/^(?:\+967|967)?7[0-9]{8}$/D'],
            'governorate_id' => ['required', 'integer', 'exists:residence_governorates,id'],
            'district_id' => ['required', 'integer', 'exists:residence_districts,id'],
            'neighborhood' => ['required', 'string', 'max:150'],
        ], ['*.required' => 'هذا الحقل مطلوب.', 'full_name.regex' => 'أدخل الاسم الرباعي.', 'national_id.regex' => 'أدخل الرقم الوطني من 6 إلى 20 رقمًا.', 'phone.regex' => 'أدخل رقم جوال يمني صحيحًا.', '*.exists' => 'اختر قيمة مسجلة.']);
        DB::transaction(function () use ($data, $request) {
            $district = ResidenceDistrict::whereKey($data['district_id'])->lockForUpdate()->firstOrFail();
            if ($district->governorate_id !== (int) $data['governorate_id']) {
                throw ValidationException::withMessages(['district_id' => 'المديرية لا تتبع المحافظة المختارة.']);
            }
            if (NeighborhoodOfficial::where('national_id_hash', hash('sha256', $data['national_id']))->exists()) {
                throw ValidationException::withMessages(['national_id' => 'هذا الرقم الوطني مسجل لعاقل بالفعل.']);
            }
            $official = new NeighborhoodOfficial;
            $official->forceFill(collect($data)->except('governorate_id')->all() + ['is_active' => true])->save();
            app(AuditTrail::class)->record('neighborhood_official_created', NeighborhoodOfficial::class, $official->id, $request->user(), changes: ['changed_fields' => ['full_name', 'national_id', 'phone', 'district_id', 'neighborhood']]);
        });

        return redirect()->route('admin.neighborhood-officials.index')->with('status', 'تم تسجيل عاقل الحارة وإتاحته ضمن مديريته. لا يمثل التسجيل تحققًا حكوميًا من هويته.');
    }

    public function update(Request $request, NeighborhoodOfficial $official)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean'], 'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('neighborhood_officials', 'user_id')->ignore($official->id)], 'can_attest' => ['sometimes', 'boolean']]);
        if (array_key_exists('user_id', $data)) {
            $employee = User::find($data['user_id']);
            if (($data['user_id'] && (! $employee || $employee->role !== 'employee' || ! $employee->is_active)) || (($data['can_attest'] ?? false) && ! $employee)) {
                throw ValidationException::withMessages(['user_id' => 'اختر حساب موظف مفعّل قبل منح صلاحية شهادة السكن.']);
            }
        }
        DB::transaction(function () use ($request, $official, $data) {
            $row = NeighborhoodOfficial::whereKey($official->id)->lockForUpdate()->firstOrFail();
            $row->forceFill($data)->save();
            app(AuditTrail::class)->record('neighborhood_official_status_changed', NeighborhoodOfficial::class, $row->id, $request->user(), changes: ['is_active' => $row->is_active, 'changed_fields' => array_keys($data)]);
        });

        return back()->with('status', 'تم تحديث حالة العاقل مع الاحتفاظ بسجلاته السابقة.');
    }

    public function officials(Request $request, ResidenceDistrict $district)
    {
        abort_unless($request->user()->is_active, 403);

        $rows = $district->officials()->available()->orderBy('neighborhood')->orderBy('id')->get();
        if ($district->code === DemoNeighborhoods::SABEEN) {
            $order = array_flip(DemoNeighborhoods::SABEEN_NEIGHBORHOODS);
            $rows = $rows->sortBy(fn ($official) => $order[$official->neighborhood] ?? count($order))->values();
        }

        return response()->json($rows->map(fn ($official) => ['id' => $official->id, 'full_name' => $official->full_name, 'neighborhood' => $official->neighborhood, 'phone' => $official->phone]))->header('Cache-Control', 'no-store, private');
    }
}
