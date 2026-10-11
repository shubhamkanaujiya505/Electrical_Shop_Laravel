<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasShopPermission('staff.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers(), function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('The password must not exceed 72 bytes.');
                }
            }],
            'permissions' => ['present', 'array', 'max:1'],
            'permissions.*' => ['string', 'distinct', Rule::in(['shop.update'])],
            'tenant_id' => ['prohibited'],
            'role' => ['prohibited'],
            'is_active' => ['prohibited'],
        ];
    }
}
