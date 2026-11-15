<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User && $request->bearerToken() !== null) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'data' => [
                'logged_out' => true,
            ],
        ]);
    }
}
