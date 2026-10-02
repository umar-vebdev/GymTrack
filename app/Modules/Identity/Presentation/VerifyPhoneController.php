<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\VerifyPhone;
use App\Modules\Identity\Presentation\Requests\VerifyPhoneRequest;
use Illuminate\Http\JsonResponse;

final class VerifyPhoneController extends Controller
{
    public function __construct(
        private readonly VerifyPhone $verifyPhone
    ) {}

    public function __invoke(VerifyPhoneRequest $request): JsonResponse
    {
        $this->verifyPhone->execute(
            phone: $request->validated('phone'),
            code: $request->validated('code')
        );

        return response()->json([
            'message' => 'Телефон успешно подтвержден.',
        ]);
    }
}
