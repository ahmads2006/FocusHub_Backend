<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Helpers\RestrictedNameHelper;

class RestrictedName implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (RestrictedNameHelper::isRestricted((string) $value)) {
            $fail('عذراً، هذا الاسم يحتوي على كلمات محظورة أو مسجلة لإدارة المنصة.');
        }
    }
}
