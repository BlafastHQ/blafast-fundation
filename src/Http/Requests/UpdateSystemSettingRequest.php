<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for updating system settings.
 */
class UpdateSystemSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required'],
            'type' => ['sometimes', 'string', 'in:string,integer,boolean,float,json,array'],
            'group' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
