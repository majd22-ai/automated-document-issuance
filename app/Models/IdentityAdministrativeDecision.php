<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityAdministrativeDecision extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['internal_note', 'decision_token'];

    public const ACTIONS = ['approved' => 'اعتماد المرحلة الإدارية التجريبية', 'returned_to_staff' => 'إعادة الطلب للموظف', 'correction_required' => 'طلب تصحيح البيانات', 'rejected' => 'رفض الطلب مع السبب', 'requeued' => 'أعاد الموظف الطلب للمراجعة الإدارية'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'form_revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Administrative decision history is immutable.'));
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function form()
    {
        return $this->belongsTo(IdentityCardForm::class, 'identity_card_form_id');
    }
}
