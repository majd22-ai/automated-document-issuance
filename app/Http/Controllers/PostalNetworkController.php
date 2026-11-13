<?php

namespace App\Http\Controllers;

use App\Models\PostalCollector;
use App\Models\PostalOffice;
use App\Rules\PostalPasswordLength;
use App\Services\Oversight\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PostalNetworkController extends Controller
{
    public function index()
    {
        return view('admin.postal-network', [
            'offices' => PostalOffice::withCount('collectors')->orderBy('code')->get(),
            'collectors' => PostalCollector::with('office')->orderBy('id')->paginate(20),
        ]);
    }

    public function saveOffice(Request $request, ?int $officeId = null)
    {
        $office = $officeId ? PostalOffice::findOrFail($officeId) : new PostalOffice;
        abort_if($office->exists && ! $office->is_demo, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:30', 'regex:/^SANA-[0-9]{3,6}$/', Rule::unique('postal_offices', 'code')->ignore($office->id)],
            'area' => ['required', 'string', 'max:160'], 'address' => ['required', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        DB::transaction(function () use ($request, $office, $data) {
            if ($office->exists) {
                $office = PostalOffice::whereKey($office->id)->lockForUpdate()->firstOrFail();
                abort_unless($office->is_demo, 403);
            }
            $office->fill($data)->forceFill(['is_demo' => true])->save();
            app(AuditTrail::class)->record('postal.office.saved', 'postal_office', $office->id, $request->user(), 'success', ['changed_fields' => array_keys($data)]);
        });

        return redirect()->route('admin.postal-network.index')->with('status', 'تم حفظ المكتب التجريبي.');
    }

    public function saveCollector(Request $request, ?int $collectorId = null)
    {
        $collector = $collectorId ? PostalCollector::findOrFail($collectorId) : new PostalCollector;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'username' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\.\-]+$/', Rule::unique('postal_collectors', 'username')->ignore($collector->id)],
            'postal_office_id' => ['required', 'integer', Rule::exists('postal_offices', 'id')->where('is_demo', true)],
            'is_active' => ['required', 'boolean'], 'can_collect' => ['required', 'boolean'],
            'password' => [$collector->exists ? 'nullable' : 'required', 'string', 'max:72', new PostalPasswordLength, 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($request, $collector, $data) {
            if ($collector->exists) {
                $collector = PostalCollector::whereKey($collector->id)->lockForUpdate()->firstOrFail();
            }
            $collector->fill($data)->save();
            app(AuditTrail::class)->record('postal.collector.saved', 'postal_collector', $collector->id, $request->user(), 'success', ['changed_fields' => array_keys($data)]);
        });

        return redirect()->route('admin.postal-network.index')->with('status', 'تم حفظ حساب محصل البريد التجريبي.');
    }
}
