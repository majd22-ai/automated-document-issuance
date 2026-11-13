<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PostalPasswordLength implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Bcrypt must never silently truncate a configured or submitted password.
        if (is_string($value) && strlen($value) > 72) {
            $fail('كلمة المرور يجب ألا تتجاوز 72 بايت.');
        }
    }
}
