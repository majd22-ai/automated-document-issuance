<?php

namespace App\Http\Controllers;

use App\Services\ResidenceLocations;

class ResidenceLocationController extends Controller
{
    public function show(string $country, ResidenceLocations $locations)
    {
        return response()->json(['country' => $country, 'governorates' => $locations->governorates($country)])
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
