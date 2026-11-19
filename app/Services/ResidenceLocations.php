<?php

namespace App\Services;

use App\Models\ResidenceGovernorate;

class ResidenceLocations
{
    public function countries(): array
    {
        return json_decode(file_get_contents(database_path('data/residence-countries.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function countryCode(?string $name): ?string
    {
        $name = trim($name ?? '');
        if ($name === 'الجمهورية اليمنية') {
            return 'YE';
        }
        if (in_array($name, ['المملكة العربية السعودية', 'السعودية'], true)) {
            return 'SA';
        }
        foreach ($this->countries() as $country) {
            if (in_array($name, [$country['name'], $country['english_name'], $country['code']], true)) {
                return $country['code'];
            }
        }
        return null;
    }

    public function governorates(string $country): array
    {
        abort_unless(preg_match('/^[A-Z]{2}$/D', $country) && in_array($country, array_column($this->countries(), 'code'), true), 404);
        if ($country === 'YE') {
            return ResidenceGovernorate::with(['districts' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get()
                ->map(fn ($row) => ['id' => (string) $row->id, 'name' => $row->name,
                    'districts' => $row->districts->map(fn ($district) => ['id' => (string) $district->id, 'name' => $district->name])->all()])->all();
        }
        return json_decode(file_get_contents(database_path('data/residence-divisions/'.$country.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function errors(array $data): array
    {
        $code = $this->countryCode($data['residence_country'] ?? null);
        // Legacy free-text country values remain editable; new country options are canonical Arabic names.
        if (! $code) {
            return [];
        }
        $governorates = $this->governorates($code);
        if (! $governorates) {
            return [];
        }
        $selected = collect($governorates)->firstWhere('name', $data['residence_governorate'] ?? null);
        if (! $selected) {
            return ['data.residence_governorate' => 'اختر محافظة أو منطقة تابعة لدولة الإقامة المحددة.'];
        }
        if ($selected['districts'] && ! in_array($data['residence_district'] ?? null, array_column($selected['districts'], 'name'), true)) {
            return ['data.residence_district' => 'اختر مديرية أو تقسيمًا إداريًا تابعًا للمحافظة المحددة.'];
        }
        return [];
    }
}
