<?php

namespace App\Services\Registration;

use Illuminate\Support\Facades\Storage;

class MockPortraits
{
    public function viewableImage(object $person): ?array
    {
        $path = $this->path($person);
        $disk = Storage::disk('attachments');
        if (! $path || ! str_starts_with($path, 'mock-registry/') || str_contains($path, '..') || ! $disk->exists($path)) {
            return null;
        }

        $absolute = $disk->path($path);
        $size = @getimagesize($absolute);
        if (! $size || $size[0] < 600 || $size[1] < 600
            || ! in_array($size['mime'], ['image/png', 'image/jpeg'], true) || filesize($absolute) > 2 * 1024 * 1024) {
            return null;
        }

        return ['path' => $absolute, 'mime' => $size['mime']];
    }

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
