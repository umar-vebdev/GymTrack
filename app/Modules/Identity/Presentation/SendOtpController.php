<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\SendEmailVerificationOtp;
use App\Modules\Identity\Presentation\Requests\SendOtpRequest;
use Illuminate\Http\JsonResponse;

final class SendOtpController extends Controller
{
    public function __construct(
        private readonly SendEmailVerificationOtp $sendOtp,
    ) {}

    public function __invoke(SendOtpRequest $request): JsonResponse
    {
        $this->sendOtp->execute(
            email: $request->string('email')->toString(),
        );

        $message = 'Код подтверждения отправлен.';

        return response()->json([
            'data' => ['message' => $message],
            'message' => $message,
        ]);
    }
}
