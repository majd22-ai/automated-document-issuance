<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentDelivery extends Model
{
    public const LABELS = ['awaiting_choice' => 'بانتظار اختيار طريقة الاستلام', 'ready' => 'جاهزة للاستلام أو التجهيز', 'preparing' => 'قيد التجهيز', 'out_for_delivery' => 'خرجت للتوصيل الداخلي', 'delivered' => 'تم التسليم', 'failed' => 'تعذر التسليم', 'returned' => 'أعيدت إلى جهة الإصدار'];

    protected $guarded = ['*'];

    protected $hidden = ['address', 'proof_reference'];

    protected function casts(): array
    {
        return ['address' => 'encrypted', 'delivered_at' => 'immutable_datetime'];
    }

    public function document()
    {
        return $this->belongsTo(CitizenDocument::class, 'citizen_document_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? 'غير محددة';
    }
}
