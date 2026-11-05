<?php

namespace App\Services\Workspace;

use App\Models\NeighborhoodOfficial;
use App\Models\User;
use Illuminate\Support\Str;

/** Uses only existing administrator-issued grants. Never grants a permission. */
class EmployeeWorkspace
{
    public const OPERATIONAL = ['can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver', 'can_verify'];

    public function serviceIds(User $user, array $abilities = self::OPERATIONAL): array
    {
        if (! $user->is_active || $user->role !== 'employee') {
            return [];
        }
        $abilities = array_values(array_intersect($abilities, [...self::OPERATIONAL, 'can_activate']));
        if ($abilities === []) {
            return [];
        }

        return $user->reviewPermissions()->where(function ($query) use ($abilities) {
            foreach ($abilities as $ability) {
                if (in_array($ability, [...self::OPERATIONAL, 'can_activate'], true)) {
                    $query->orWhere($ability, true);
                }
            }
        })->pluck('government_service_id')->map(fn ($id) => (int) $id)->all();
    }

    public function modules(User $user): array
    {
        if (! $user->is_active || $user->role !== 'employee') {
            return [];
        }
        $grants = $user->reviewPermissions()->get();
        $has = fn (array $abilities) => $grants->contains(fn ($grant) => collect($abilities)->contains(fn ($ability) => (bool) $grant->{$ability}));
        $items = [['key' => 'dashboard', 'label' => 'ملخص عملي', 'route' => 'employee.dashboard', 'pattern' => 'employee.dashboard', 'description' => 'المهام المتاحة ونطاق خدماتك.']];
        $add = function ($key, $label, $route, $pattern, $description) use (&$items) {
            $items[] = compact('key', 'label', 'route', 'pattern', 'description');
        };
        if (NeighborhoodOfficial::authorized($user)) {
            $add('residence', 'شهادات السكن', 'employee.neighborhood-attestations.index', 'employee.neighborhood-attestations.*', 'طلبات المواطنين الموجهة إليك في حيك فقط.');
        }
        if ($user->can('manageCitizenActivation', User::class)) {
            $add('activation', 'تفعيل الحسابات', 'employee.accounts.index', 'employee.accounts.*', 'مراجعة الهوية والصور واتخاذ قرار التفعيل.');
        }
        if ($has(['can_review', 'can_approve', 'can_assign'])) {
            $add('review', 'مراجعة الطلبات', 'employee.applications.index', 'employee.applications.*', 'المراجعة والقرار وفق تفويضك لكل خدمة.');
        }
        if ($has(['can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver'])) {
            $add('tracking', 'متابعة المعاملات', 'employee.tracking.index', 'employee.tracking.*', 'حالات الطلبات ضمن الخدمات المخولة لك.');
        }
        if ($has(self::OPERATIONAL)) {
            $add('citizens', 'ملفات المواطنين', 'employee.citizens.index', 'employee.citizens.*', 'البيانات المرتبطة بخدماتك المسموحة فقط.');
        }
        if ($has(['can_review'])) {
            $add('attachments', 'مراجعة المرفقات', 'employee.attachments.index', 'employee.attachments.*', 'الصور والمستندات الخاصة بخدمات المراجعة.');
            $add('appointments', 'المواعيد', 'employee.appointments.index', 'employee.appointments.*', 'الحجوزات والحضور ضمن خدماتك.');
            $add('slots', 'فترات الحجز', 'employee.appointment-slots.index', 'employee.appointment-slots.*', 'إتاحة الفترات والسعة لخدماتك.');
            $add('payments', 'الرسوم والمدفوعات', 'employee.payments.index', 'employee.payments.*', 'الرسوم والإيصالات المتعلقة بخدماتك.');
        }
        if ($has(['can_verify'])) {
            $add('verification', 'التحقق الإلكتروني', 'employee.verifications.index', 'employee.verifications.*', 'التحقق من الطلبات المخولة لك.');
        }
        if ($has(['can_issue'])) {
            $add('issuance', 'إصدار الوثائق', 'employee.issuance.index', 'employee.issuance.*', 'فحص جاهزية الطلبات وإصدار وثائقها الداخلية.');
        }
        if ($has(['can_deliver'])) {
            $add('delivery', 'تسليم الوثائق', 'employee.deliveries.index', 'employee.deliveries.*', 'تسجيل التسليم ومتابعة الاستلام.');
        }
        if ($user->report_scope === 'department') {
            $add('reports', 'تقارير القسم', 'employee.reports.index', 'employee.reports.*', 'المؤشرات ضمن نطاق القسم المفوض.');
        }

        return $items;
    }

    public function allowsRoute(User $user, string $route): bool
    {
        if (in_array($route, ['employee.dashboard', 'employee.notifications.index', 'employee.notifications.open', 'employee.notifications.read', 'employee.notifications.read-all'], true)) {
            return $user->is_active;
        }
        // Detail routes nested beneath applications belong to their actual capability.
        $key = match (true) {
            str_starts_with($route, 'employee.identity-card.') => 'review',
            str_starts_with($route, 'employee.verifications.') => 'verification',
            str_starts_with($route, 'employee.documents.') => null,
            in_array($route, ['employee.applications.operations', 'employee.applications.appointments.store', 'employee.applications.examinations.store', 'employee.applications.charges.store', 'employee.applications.payments.store'], true) => 'payments',
            default => null,
        };
        $items = $this->modules($user);
        if (str_starts_with($route, 'employee.documents.')) {
            return collect($items)->contains(fn ($item) => in_array($item['key'], ['issuance', 'delivery'], true));
        }
        if ($key) {
            return collect($items)->contains('key',$key);
        }

        return collect($items)->contains(fn ($item) => Str::is($item['pattern'],$route));
    }
}
