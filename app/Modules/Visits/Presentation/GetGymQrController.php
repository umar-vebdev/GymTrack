<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Visits\Application\GenerateGymQr;
use App\Modules\Visits\Domain\Exceptions\GymNotFound;
use App\Modules\Visits\Domain\QrTokenServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetGymQrController extends Controller
{
    public function __construct(
        private readonly GenerateGymQr $generateGymQr,
        private readonly StaffCompanyResolverInterface $companies,
    ) {}

    public function __invoke(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Пользователь не состоит в компании.',
                ],
            ], 403);
        }

        try {
            $result = $this->generateGymQr->execute($companyId, $id);
        } catch (GymNotFound $e) {
            return response()->json([
                'error' => [
                    'code' => 'GYM_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'gym_id' => $result['gym']->id,
                'gym_name' => $result['gym']->name,
                'slot' => $result['payload']->slot,
                'token' => $result['payload']->toTokenString(),
                'ttl_seconds' => QrTokenServiceInterface::INTERVAL_SECONDS,
                'expires_at' => $result['payload']->expiresAt->format(DATE_ATOM),
            ],
        ]);
    }
}
