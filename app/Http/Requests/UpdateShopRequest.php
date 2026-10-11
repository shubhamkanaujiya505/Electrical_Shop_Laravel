<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasShopPermission('shop.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:25'],
            'address' => ['nullable', 'string', 'max:500'],
            'tenant_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
            'is_active' => ['prohibited'],
        ];
    }
}
