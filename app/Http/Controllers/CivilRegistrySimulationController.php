<?php

namespace App\Http\Controllers;

use App\Services\Integrations\LocalCivilRegistryProvider;
use App\Services\MockCivilRegistry;
use App\Services\Oversight\AuditTrail;
use App\Services\Registration\MockCivilSystems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CivilRegistrySimulationController extends Controller
{
    public function preview(Request $request, LocalCivilRegistryProvider $provider, MockCivilRegistry $registry)
    {
        abort_unless(LocalCivilRegistryProvider::enabled(), 404);
        abort_unless(LocalCivilRegistryProvider::canSimulate($request->user()), 403);
        $result = ['outcome' => null];
        if ($request->isMethod('post')) {
            if ($request->has('query') || $request->has('suggest')) {
                $input = $request->validate([
                    'query' => ['required', 'string', 'min:2', 'max:150'],
                    'suggest' => ['sometimes', 'boolean'],
                ]);
                $result = $this->findRecord($input['query'], $request->boolean('suggest'), $registry, $provider);
                if (! $request->boolean('suggest')) {
                    app(AuditTrail::class)->record('mock_civil_registry.simulate_lookup', 'mock_civil_person', null, $request->user(), changes: ['status' => $result['outcome']]);
                }
            } else {
                $input = $request->validate([
                    'national_id' => ['required', 'string', 'regex:/^[0-9]{11}$/'],
                    'name' => ['required', 'string', 'max:150'],
                ], [
                    'national_id.regex' => 'أدخل رقم الاختبار المكوّن من 11 رقمًا.',
                    'national_id.required' => 'أدخل الرقم الوطني التجريبي.',
                    'name.required' => 'أدخل الاسم التجريبي.',
                ]);
                $result['outcome'] = $provider->simulate($input['national_id'], $input['name']);
            }
        }

        if ($request->expectsJson()) {
            return response()->json($result)->header('Cache-Control', 'no-store, private');
        }

        return redirect()->route($request->user()->role.'.dashboard', ['tools' => 'civil-check'])
            ->with('registrySimulationOutcome', $result['outcome'] ?? null)
            ->header('Cache-Control', 'no-store, private');
    }

    private function findRecord(string $input, bool $suggest, MockCivilRegistry $registry, LocalCivilRegistryProvider $provider): array
    {
        $term = preg_replace('/\s+/u', ' ', trim($input));
        $term = strtr($term, array_combine(preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
        $byNumber = ctype_digit($term);
        $available = MockCivilSystems::enabled() && ! array_diff(MockCivilRegistry::CIVIL_TABLES, Schema::getTableListing(null, false));
        $fixtures = config('civil_registry_demo.records', []);
        if ($suggest) {
            if ($byNumber) {
                return ['suggestions' => []];
            }
            $names = $available ? $registry->simulationNames($term) : collect();
            $testNames = collect($fixtures)->pluck('name')->filter(fn ($name) => mb_stripos($name, $term) !== false);

            return ['suggestions' => $names->merge($testNames)->unique()->values()];
        }
        $records = $available ? $registry->simulationRecords($term, $byNumber) : collect();
        if ($records->isNotEmpty()) {
            return ['outcome' => 'match', 'civil_records' => $records];
        }

        foreach ($fixtures as $number => $record) {
            if ((string) $number === $term || $record['name'] === $term) {
                return ['outcome' => $provider->simulate((string) $number, $record['name']),
                    'fixture' => ['national_id' => (string) $number, 'name' => $record['name']]];
            }
        }

        return ['outcome' => 'not_found'];
    }
}
