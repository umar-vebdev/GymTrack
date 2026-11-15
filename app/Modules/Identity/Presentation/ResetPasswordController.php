<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\ResetPasswordWithOtp;
use App\Modules\Identity\Presentation\Requests\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

final class ResetPasswordController extends Controller
{
    public function __construct(
        private readonly ResetPasswordWithOtp $resetPassword,
    ) {}

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $this->resetPassword->execute(
            email: $request->string('email')->toString(),
            code: $request->string('code')->toString(),
            newPassword: $request->string('password')->toString(),
        );

        $message = 'Пароль успешно изменён.';

        return response()->json([
            'data' => ['message' => $message],
            'message' => $message,
        ]);
    }
}
