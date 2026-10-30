<?php

namespace App\Http\Controllers;

use App\Services\Integrations\LocalCivilRegistryProvider;
use Illuminate\Http\Request;

class CivilRegistrySimulationController extends Controller
{
    public function preview(Request $request, LocalCivilRegistryProvider $provider)
    {
        abort_unless(LocalCivilRegistryProvider::enabled(), 404);
        $outcome = null;
        $input = [];
        if ($request->isMethod('post')) {
            $input = $request->validate([
                'national_id' => ['required', 'string', 'regex:/^[0-9]{11}$/'],
                'name' => ['required', 'string', 'max:150'],
            ], [
                'national_id.regex' => 'أدخل رقم الاختبار المكوّن من 11 رقمًا.',
                'national_id.required' => 'أدخل الرقم الوطني التجريبي.',
                'name.required' => 'أدخل الاسم التجريبي.',
            ]);
            $outcome = $provider->simulate($input['national_id'], $input['name']);
        }

        if ($request->expectsJson()) {
            return response()->json(['outcome' => $outcome])->header('Cache-Control', 'no-store, private');
        }

        return response()->view('verifications.civil-registry-demo', [
            'outcome' => $outcome, 'input' => $input,
            'records' => config('civil_registry_demo.records', []),
        ])->header('Cache-Control', 'no-store, private');
    }
}
