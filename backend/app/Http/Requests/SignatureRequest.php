<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'autorisation est gérée par le middleware RBAC + EmargementService
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:300000'],
        ];
    }
}
