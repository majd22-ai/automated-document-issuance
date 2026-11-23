<?php

namespace App\Http\Controllers;

use App\Services\MockCivilRegistry;
use App\Services\Oversight\AuditTrail;
use App\Services\Registration\MockCivilSystems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class MockCivilRegistryController extends Controller
{
    public function index(Request $request, MockCivilRegistry $registry, AuditTrail $audit): Response|JsonResponse
    {
        $this->authorizeAccess($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'account' => ['nullable', 'in:linked,unlinked'],
            'record' => ['nullable', 'in:complete,incomplete'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        unset($filters['page']);
        if ($request->header('X-Operations-Tools') === 'registry-suggestions') {
            $matches = $registry->nameSuggestions($filters);
            $audit->record('mock_civil_registry.list', 'mock_civil_person', null, $request->user());

            return response()->json($matches)
                ->header('Cache-Control', 'no-store, private')
                ->header('Vary', 'X-Operations-Tools');
        }
        $people = $registry->people($filters);
        $audit->record('mock_civil_registry.list', 'mock_civil_person', null, $request->user());

        return $this->page($request, 'admin.mock-civil-registry.index', compact('people', 'filters'));
    }

    public function show(Request $request, int $person, MockCivilRegistry $registry, AuditTrail $audit): Response
    {
        $this->authorizeAccess($request);

        $profile = $registry->profile($person, $request->user());
        $audit->record('mock_civil_registry.view', 'mock_civil_person', $person, $request->user());

        return $this->page($request, 'admin.mock-civil-registry.show', $profile);
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()->is_active && MockCivilSystems::enabled(), 403);
        foreach (MockCivilRegistry::TABLES as $table) {
            abort_unless(Schema::hasTable($table), 503, 'لم تكتمل تهيئة السجل المدني التجريبي.');
        }
    }

    private function page(Request $request, string $view, array $data): Response
    {
        $toolsFragment = $request->header('X-Operations-Tools') === 'registry';

        return response()->view($view, $data + compact('toolsFragment'))
            ->header('Cache-Control', 'no-store, private')
            ->header('Vary', 'X-Operations-Tools')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
