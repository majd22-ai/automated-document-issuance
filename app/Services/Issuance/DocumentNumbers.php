<?php

namespace App\Services\Issuance;

use Illuminate\Support\Str;

class DocumentNumbers
{
    public function next(array $rules): string
    {
        return implode('-', array_filter(['INT', $rules['number_prefix'] ?? null, ($rules['include_year'] ?? false) ? now()->format('Y') : null, (string) Str::ulid()]));
    }
}
