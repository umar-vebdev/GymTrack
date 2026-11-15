<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\VerifyEmail;
use App\Modules\Identity\Presentation\Requests\VerifyEmailRequest;
use Illuminate\Http\JsonResponse;

final class VerifyEmailController extends Controller
{
    public function __construct(
        private readonly VerifyEmail $verifyEmail,
    ) {}

    public function __invoke(VerifyEmailRequest $request): JsonResponse
    {
        $this->verifyEmail->execute(
            email: $request->string('email')->toString(),
            code: $request->string('code')->toString(),
        );

        $message = 'Email успешно подтверждён.';

        return response()->json([
            'data' => ['message' => $message],
            'message' => $message,
        ]);
    }
}
