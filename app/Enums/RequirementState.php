<?php

namespace App\Enums;

enum RequirementState: string
{
    case Met = 'met';
    case Unmet = 'unmet';
    case NeedsReview = 'needs_review';
    case Unverifiable = 'unverifiable';

    public function label(): string
    {
        return match ($this) {
            self::Met => 'مستوفى', self::Unmet => 'غير مستوفى',
            self::NeedsReview => 'يحتاج إلى مراجعة', self::Unverifiable => 'تعذّر التحقق',
        };
    }
}
