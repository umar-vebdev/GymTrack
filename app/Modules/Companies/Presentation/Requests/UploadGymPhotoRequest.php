<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UploadGymPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['photo' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120']];
    }
}
