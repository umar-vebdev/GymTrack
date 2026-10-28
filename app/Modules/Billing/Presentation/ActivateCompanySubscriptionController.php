<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Billing\Application\ActivateCompanySubscription;
use App\Modules\Billing\Domain\Exceptions\PlanNotFound;
use App\Modules\Billing\Presentation\Requests\ActivateSubscriptionRequest;
use App\Shared\Domain\Clock;
use Illuminate\Http\JsonResponse;

final class ActivateCompanySubscriptionController extends Controller
{
    public function __construct(
        private readonly ActivateCompanySubscription $activateSubscription,
        private readonly Clock $clock
    ) {}

    public function __invoke(int $id, ActivateSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->is_platform_admin) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Подтверждать оплату подписки может только администратор платформы.',
            ]], 403);
        }

        try {
            $subscription = $this->activateSubscription->execute(
                companyId: $id,
                planId: (int) $request->validated('plan_id'),
                activatedByUserId: $user->id
            );
        } catch (PlanNotFound $e) {
            return response()->json(['error' => [
                'code' => 'PLAN_NOT_FOUND',
                'message' => $e->getMessage(),
            ]], 404);
        }

        return response()->json([
            'data' => GetCompanySubscriptionController::serialize($subscription, $this->clock),
        ]);
    }
}

