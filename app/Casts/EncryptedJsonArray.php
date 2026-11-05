<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use JsonException;

class EncryptedJsonArray implements CastsAttributes
{
    private const PREFIX = 'enc:v1:';

    public function get($model, string $key, $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if ($decoded === null && $value === 'null') {
            return null;
        }
        if (is_string($decoded) && str_starts_with($decoded, self::PREFIX)) {
            $decoded = json_decode(Crypt::decryptString(substr($decoded, strlen(self::PREFIX))), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : throw new JsonException('Encrypted request data is not an array.');
        }

        return is_array($decoded) ? $decoded : throw new JsonException('Legacy request data is not an array.');
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            throw new InvalidArgumentException('Request data must be an array.');
        }

        return json_encode(self::PREFIX.Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR);
    }
}
