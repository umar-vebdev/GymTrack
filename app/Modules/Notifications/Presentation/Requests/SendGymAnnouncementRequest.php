<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SendGymAnnouncementRequest extends FormRequest
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
            'channel' => ['required', 'string', 'in:push,sms'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
        ];
    }
}

