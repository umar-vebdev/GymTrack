<?php

declare(strict_types=1);

namespace App\Modules\Clients\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MergeClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_id' => ['required', 'integer'],
            'target_id' => ['required', 'integer', 'different:source_id'],
        ];
    }
}
