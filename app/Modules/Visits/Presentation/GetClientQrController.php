<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Visits\Application\GenerateClientQr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetClientQrController extends Controller
{
    public function __construct(
        private readonly GenerateClientQr $generateClientQr,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->generateClientQr->execute($user->id);

        return response()->json([
            'data' => [
                'slot' => $result['payload']->slot,
                'token' => $result['payload']->toTokenString(),
                'ttl_seconds' => $result['ttlSeconds'],
                'expires_at' => $result['payload']->expiresAt->format(DATE_ATOM),
            ],
        ]);
    }
}
