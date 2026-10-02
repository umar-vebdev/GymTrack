<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\RegisterUser;
use App\Modules\Identity\Presentation\Requests\RegisterUserRequest;
use Illuminate\Http\JsonResponse;

final class RegisterUserController extends Controller
{
    public function __construct(
        private readonly RegisterUser $registerUser
    ) {}

    public function __invoke(RegisterUserRequest $request): JsonResponse
    {
        $id = $this->registerUser->execute(
            phone: $request->validated('phone'),
            email: $request->validated('email'),
            password: $request->validated('password')
        );

        return response()->json([
            'data' => [
                'id' => $id,
                'message' => 'User registered successfully.',
            ],
        ], 201);
    }
}
