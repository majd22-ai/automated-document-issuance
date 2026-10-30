<?php

namespace App\Http\Controllers;

use App\Models\NeighborhoodOfficial;
use App\Models\ResidenceDistrict;
use App\Services\Oversight\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NeighborhoodOfficialController extends Controller
{
    public function index()
    {
        $officials = NeighborhoodOfficial::with('district.governorate')->latest('id')->paginate(20);

        return response()->view('admin.neighborhood-officials', compact('officials'))->header('Cache-Control', 'no-store, private');
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
            $district = ResidenceDistrict::findOrFail($data['district_id']);
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
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $official, $data) {
            $row = NeighborhoodOfficial::whereKey($official->id)->lockForUpdate()->firstOrFail();
            $row->forceFill($data)->save();
            app(AuditTrail::class)->record('neighborhood_official_status_changed', NeighborhoodOfficial::class, $row->id, $request->user(), changes: ['is_active' => $row->is_active]);
        });

        return back()->with('status', 'تم تحديث حالة العاقل مع الاحتفاظ بسجلاته السابقة.');
    }

    public function officials(Request $request, ResidenceDistrict $district)
    {
        abort_unless($request->user()->is_active, 403);

        return response()->json($district->officials()->where('is_active', true)->orderBy('full_name')->get(['id', 'full_name', 'neighborhood']))->header('Cache-Control', 'no-store, private');
    }
}
