<?php

namespace App\Services;

use App\Models\ResidenceGovernorate;
use App\Models\ResidenceDistrict;

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
                    'label' => $row->code === 'YE13' ? 'أمانة العاصمة (مدينة صنعاء)' : $row->name,
                    'districts' => $row->districts->map(fn ($district) => ['id' => (string) $district->id, 'name' => $district->name])->all()])->all();
        }
        $governorates = json_decode(file_get_contents(database_path('data/residence-divisions/'.$country.'.json')), true, flags: JSON_THROW_ON_ERROR);
        if ($country === 'SA') {
            $labels = [
                'SA.02' => 'الباحة', 'SA.05' => 'المدينة المنورة', 'SA.06' => 'المنطقة الشرقية',
                'SA.08' => 'القصيم', 'SA.10' => 'الرياض', 'SA.11' => 'عسير',
                'SA.13' => 'حائل', 'SA.14' => 'مكة المكرمة', 'SA.15' => 'الحدود الشمالية',
                'SA.16' => 'نجران', 'SA.17' => 'جازان', 'SA.19' => 'تبوك', 'SA.20' => 'الجوف',
            ];
            foreach ($governorates as &$governorate) {
                $governorate['label'] = $labels[$governorate['id']] ?? $governorate['name'];
            }
            unset($governorate);
        }

        return $governorates;
    }

    public function errors(array $data): array
    {
        $code = $this->countryCode($data['residence_country'] ?? null);
        $officialId = $data['residence_official_id'] ?? null;
        if (filled($officialId) && $code !== 'YE') {
            return ['data.neighborhood' => 'اختر حيًا تابعًا لعنوان الإقامة المحدد.'];
        }
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
        $district = collect($selected['districts'])->firstWhere('name', $data['residence_district'] ?? null);
        if ($selected['districts'] && ! $district) {
            return ['data.residence_district' => 'اختر مديرية أو تقسيمًا إداريًا تابعًا للمحافظة المحددة.'];
        }
        if (filled($officialId)) {
            $official = $district ? ResidenceDistrict::find($district['id'])?->officials()->available()
                ->where('neighborhood', $data['neighborhood'] ?? null)
                ->when(filled($officialId), fn ($query) => $query->whereKey($officialId))->first() : null;
            if (! $official || $official->neighborhood !== ($data['neighborhood'] ?? null)) {
                return ['data.neighborhood' => 'اختر حيًا وعاقلًا مفعّلًا من قائمة المديرية المحددة.'];
            }
        }
        return [];
    }
}
