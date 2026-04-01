<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', new \App\Rules\RestrictedName()],
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('user_profiles')->ignore($this->user()->profile->id),
                function ($attribute, $value, $fail) {
                    if (!\App\Helpers\RestrictedNameHelper::isValidHandleFormat($value)) {
                        $fail('يجب أن يبدأ اسم المستخدم بـ @ ويحتوي فقط على أحرف وأرقام ونقطة أو شرطة سفلية.');
                    }
                    if (\App\Helpers\RestrictedNameHelper::isRestricted($value)) {
                        $fail('اسم المستخدم هذا غير مسموح به.');
                    }
                },
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];

        // Require current password if the user is changing their email
        if ($this->input('email') !== $this->user()->email) {
            $rules['password'] = ['required', 'current_password'];
        }

        return $rules;
    }
}
