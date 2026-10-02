<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\SendPhoneVerificationOtp;
use App\Modules\Identity\Presentation\Requests\SendOtpRequest;
use Illuminate\Http\JsonResponse;

final class SendOtpController extends Controller
{
    public function __construct(
        private readonly SendPhoneVerificationOtp $sendOtp
    ) {}

    public function __invoke(SendOtpRequest $request): JsonResponse
    {
        $this->sendOtp->execute(
            phone: $request->validated('phone')
        );

        return response()->json([
            'message' => 'Код подтверждения отправлен.',
        ]);
    }
}
