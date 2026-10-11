<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasShopPermission('staff.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'permissions' => ['present', 'array', 'max:1'],
            'permissions.*' => ['string', 'distinct', Rule::in(['shop.update'])],
            'tenant_id' => ['prohibited'],
            'role' => ['prohibited'],
            'name' => ['prohibited'],
            'email' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }
}
