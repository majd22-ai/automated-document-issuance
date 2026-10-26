<?php

namespace App\Services\Registration;

use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CitizenIdentifiers
{
    /** All registration, staff username and official citizen identifier writes share this lock. */
    public function serialized(callable $work): mixed
    {
        try {
            return Cache::lock('citizen-identifiers:write', 30)->block(5, $work);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['identifier' => 'تجري مراجعة معرّفات الحسابات. أعد المحاولة بعد قليل.']);
        }
    }

    public function conflicts(string $identifier, ?int $ignore = null): bool
    {
        return User::when($ignore, fn ($q) => $q->whereKeyNot($ignore))->where(fn ($q) => $q->where('username', $identifier)->orWhere('national_id', $identifier))->exists();
    }
}
