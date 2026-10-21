<?php

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case RequiresCorrection = 'requires_correction';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'مقدّم', self::UnderReview => 'قيد المراجعة',
            self::RequiresCorrection => 'يحتاج إلى استكمال', self::Accepted => 'مقبول',
            self::Rejected => 'مرفوض', self::Approved => 'معتمد', self::Processing => 'قيد التجهيز',
            self::Completed => 'مكتمل', self::Cancelled => 'ملغى',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Completed, self::Cancelled], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Submitted => [self::UnderReview],
            self::UnderReview => [self::RequiresCorrection, self::Accepted, self::Rejected],
            self::RequiresCorrection => [self::Submitted], self::Accepted => [self::Approved],
            self::Approved => [self::Processing], self::Processing => [self::Completed],
            default => [],
        }, true);
    }
}
