<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case NotRequired = 'not_required';
    case NotConfigured = 'not_configured';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Mismatch = 'mismatch';
    case Unavailable = 'unavailable';
    case Failed = 'failed';
    case Unknown = 'unknown';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'التحقق الخارجي غير مطلوب لهذه الخدمة',self::NotConfigured => 'خدمة التحقق غير مهيأة',self::Pending => 'العملية قيد الانتظار',self::InProgress => 'يجري تنفيذ التحقق',self::Verified => 'تم التحقق بنجاح من المصدر المعتمد',self::Rejected => 'أعاد المصدر نتيجة رفض صريحة',self::Mismatch => 'توجد بيانات غير متطابقة وفق نتيجة المصدر',self::Unavailable => 'تعذر الاتصال بمصدر التحقق، يرجى إعادة المحاولة لاحقًا',self::Failed => 'فشلت عملية التحقق تقنيًا',self::Unknown => 'نتيجة التحقق غير حاسمة',self::Expired => 'نتيجة التحقق قديمة أو تغيرت بياناتها؛ يلزم تحقق جديد'
        };
    }
}
