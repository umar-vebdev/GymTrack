<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetClientProfileController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $user = $this->userRepository->findById($authUser->id);

        return response()->json([
            'data' => [
                'id' => $authUser->id,
                'phone' => $user?->phone(),
                'name' => $user?->name(),
                'birth_date' => $user?->birthDate()?->format('Y-m-d'),
                'is_phone_verified' => $user?->isPhoneVerified() ?? false,
                'personal_data_consented_at' => $user?->personalDataConsentedAt()?->format(DATE_ATOM),
            ],
        ]);
    }
}
