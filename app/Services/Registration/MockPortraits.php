<?php

namespace App\Services\Registration;

use Illuminate\Support\Facades\Storage;

class MockPortraits
{
    public function path(object $person): ?string
    {
        // Display portraits remain separate from the deliberately missing biometric reference scenario.
        if (array_key_exists($person->fixture_key ?? '', config('mock_people.names', []))) {
            $path = 'mock-registry/portraits/'.$person->fixture_key.'.jpg';
            if (Storage::disk('attachments')->exists($path)) return $path;
        }
        return $person->reference_photo ?? null;
    }
}
