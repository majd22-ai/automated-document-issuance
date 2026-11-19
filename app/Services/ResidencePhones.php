<?php

namespace App\Services;

class ResidencePhones
{
    public function errors(array $data, array $witnesses = []): array
    {
        $countryName = $data['residence_country'] ?? '';
        $country = app(ResidenceLocations::class)->countryCode(is_string($countryName) ? $countryName : '') ?? 'YE';
        if ($country !== 'YE') {
            return [];
        }
        $phones = ['data.phone' => $data['phone'] ?? null, 'data.alternate_phone' => $data['alternate_phone'] ?? null];
        foreach ($witnesses as $index => $witness) {
            if (! is_array($witness)) {
                continue;
            }
            $phones['witnesses.'.$index.'.phone'] = $witness['phone'] ?? null;
        }
        $errors = [];
        foreach ($phones as $key => $phone) {
            if (! is_string($phone) || $phone === '') {
                continue;
            }
            $local = preg_replace('/^(?:\+967|00967)/', '', $phone);
            if (strlen($local) > 9 || (str_starts_with($phone, '+') && ! str_starts_with($phone, '+967'))) {
                $errors[$key] = 'رقم الهاتف اليمني يجب ألا يتجاوز 9 أرقام بعد رمز الدولة +967.';
            }
        }
        return $errors;
    }
}
