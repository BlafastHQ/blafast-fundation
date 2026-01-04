<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for updating organization settings.
 */
class UpdateOrganizationSettingsRequest extends FormRequest
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
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'], // Any value type is allowed
        ];
    }
}
