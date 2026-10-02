<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\IssueToken;
use App\Modules\Identity\Presentation\Requests\LoginUserRequest;
use Illuminate\Http\JsonResponse;

final class LoginUserController extends Controller
{
    public function __construct(
        private readonly IssueToken $issueToken
    ) {}

    public function __invoke(LoginUserRequest $request): JsonResponse
    {
        try {
            $token = $this->issueToken->execute(
                phone: $request->validated('phone'),
                password: $request->validated('password'),
                device: $request->validated('device', 'mobile')
            );

            return response()->json([
                'data' => [
                    'token' => $token,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => $e->getMessage(),
                ],
            ], 401);
        }
    }
}
