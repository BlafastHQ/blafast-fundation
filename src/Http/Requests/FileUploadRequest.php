<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validator for file uploads.
 *
 * Validates that:
 * - A file is provided
 * - File size is within limits (50MB default)
 * - Optional name and custom properties are valid
 */
class FileUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by the controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxSize = config('blafast-fundation.media.max_file_size', 10240); // in KB

        return [
            'file' => ['required', 'file', "max:{$maxSize}"],
            'name' => ['nullable', 'string', 'max:255'],
            'properties' => ['nullable', 'array'],
            'properties.*' => ['nullable'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file is required for upload.',
            'file.file' => 'The uploaded file is invalid.',
            'file.max' => 'The file size exceeds the maximum allowed size of :max KB.',
            'name.max' => 'The file name cannot exceed 255 characters.',
            'properties.array' => 'Custom properties must be an array.',
        ];
    }
}
