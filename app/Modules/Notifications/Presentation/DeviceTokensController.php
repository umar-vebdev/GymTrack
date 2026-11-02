<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Notifications\Application\RegisterDeviceToken;
use App\Modules\Notifications\Application\UnregisterDeviceToken;
use App\Modules\Notifications\Presentation\Requests\RegisterDeviceTokenRequest;
use App\Modules\Notifications\Presentation\Requests\UnregisterDeviceTokenRequest;
use Illuminate\Http\JsonResponse;

final class DeviceTokensController extends Controller
{
    public function register(
        RegisterDeviceTokenRequest $request,
        RegisterDeviceToken $useCase
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var string $token */
        $token = $request->validated('token');
        /** @var string $platform */
        $platform = $request->validated('platform');

        $useCase->execute($user->id, $token, $platform);

        return response()->json([
            'data' => [
                'registered' => true,
            ],
        ]);
    }

    public function unregister(
        UnregisterDeviceTokenRequest $request,
        UnregisterDeviceToken $useCase
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var string $token */
        $token = $request->validated('token');

        $useCase->execute($user->id, $token);

        return response()->json([
            'data' => [
                'unregistered' => true,
            ],
        ]);
    }
}
