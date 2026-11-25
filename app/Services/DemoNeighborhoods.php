<?php

namespace App\Services;

use App\Models\NeighborhoodOfficial;
use App\Models\ResidenceDistrict;
use App\Models\User;
use App\Services\Oversight\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoNeighborhoods
{
    public const SABEEN = 'YE1305';
    public const GOVERNORATES = ['YE11', 'YE15', 'YE23'];
    public const EMAIL_SUFFIX = '@neighborhood.demo.invalid';
    public const SABEEN_NEIGHBORHOODS = [
        'حي السبعين', 'حي القادسية', 'حي القلفان', 'حي بير عبيد', 'حي الجرداء',
        'حي السواد الشمالي', 'حي شرق حدين', 'حي بيت معياد', 'حي حدين الغربية',
        'حي آزال السبعين', 'حي آزال حدة', 'حي السبعين آزال', 'حي فج عطان',
        'حي العفيف (بيت العفيف)', 'حي قرية حدة',
    ];

    public static function includes(string $districtCode): bool
    {
        return $districtCode === self::SABEEN || in_array(substr($districtCode, 0, 4), self::GOVERNORATES, true);
    }

    public static function isDemoAccount(User $user): bool
    {
        return Str::endsWith($user->email, self::EMAIL_SUFFIX);
    }

    public function generate(User $actor): array
    {
        return DB::transaction(function () use ($actor) {
            $counts = ['created' => 0, 'existing' => 0];
            $initialPassword = Hash::make(Str::random(48));
            $districts = ResidenceDistrict::where(fn ($query) => $query->where('code', self::SABEEN)
                ->orWhereHas('governorate', fn ($governorate) => $governorate->whereIn('code', self::GOVERNORATES)))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($districts as $district) {
                $neighborhoods = $district->code === self::SABEEN
                    ? self::SABEEN_NEIGHBORHOODS : ['حي النور', 'حي السلام', 'حي الروضة'];
                foreach ($neighborhoods as $slot => $neighborhood) {
                    if ($district->officials()->where('neighborhood', $neighborhood)->exists()) {
                        $counts['existing']++;
                        continue;
                    }
                    $number = (int) substr($district->code, 2) * 100 + $slot + 1;
                    $username = 'demo-aqil-'.strtolower($district->code).'-'.($slot + 1);
                    $name = $this->name($number);
                    $user = User::firstOrNew(['email' => $username.self::EMAIL_SUFFIX]);
                    if (! $user->exists) {
                        $user->forceFill([
                            'username' => $username, 'name' => $name, 'role' => 'employee',
                            'password' => $initialPassword, 'is_active' => true,
                        ])->save();
                    }
                    $official = new NeighborhoodOfficial;
                    $official->forceFill([
                        'district_id' => $district->id, 'neighborhood' => $neighborhood, 'full_name' => $name,
                        'national_id' => '99990'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                        'phone' => (string) (700000000 + $number), 'user_id' => $user->id,
                        'is_active' => true, 'can_attest' => true,
                    ])->save();
                    $counts['created']++;
                }
            }
            app(AuditTrail::class)->record('demo_neighborhoods_generated', NeighborhoodOfficial::class, null, $actor,
                changes: ['action' => 'generate', 'status' => $counts]);

            return $counts;
        });
    }

    private function name(int $number): string
    {
        $parts = [
            ['محمد', 'علي', 'أحمد', 'صالح', 'محمود', 'يحيى', 'حسن'],
            ['عبد الله', 'ناصر', 'حسين', 'قاسم', 'إبراهيم'],
            ['عمر', 'سالم', 'عبد الرحمن', 'حميد', 'منصور', 'سعيد'],
            ['الهاشمي', 'العامري', 'الحكيمي', 'الشيباني', 'العنسي', 'الزبيري', 'الحداد'],
        ];

        return implode(' ', array_map(function ($choices) use (&$number) {
            $choice = $choices[$number % count($choices)];
            $number = intdiv($number, count($choices));

            return $choice;
        }, $parts));
    }
}
