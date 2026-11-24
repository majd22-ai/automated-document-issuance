<?php

namespace App\Http\Controllers;

class SimulationCenterController extends Controller
{
    public const DEMO_TABLES = ['demo_civil_people', 'demo_civil_families', 'demo_civil_family_members', 'demo_civil_records', 'demo_civil_record_parties', 'demo_civil_documents', 'demo_civil_registry_audits'];

    public function index()
    {
        return redirect()->route('admin.dashboard', ['tools' => 'home']);
    }
}
