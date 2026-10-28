<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Billing\Application\GetCompanySubscription;
use App\Modules\Billing\Domain\Subscription;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Shared\Domain\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetCompanySubscriptionController extends Controller
{
    public function __construct(
        private readonly GetCompanySubscription $getSubscription,
        private readonly StaffCompanyResolverInterface $companies,
        private readonly Clock $clock
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Доступ только для сотрудников зала.',
            ]], 403);
        }

        $subscription = $this->getSubscription->execute($companyId);

        return response()->json([
            'data' => self::serialize($subscription, $this->clock),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function serialize(Subscription $subscription, Clock $clock): array
    {
        return [
            'id' => $subscription->id(),
            'company_id' => $subscription->companyId(),
            'plan_id' => $subscription->planId(),
            'status' => $subscription->status()->value,
            'is_read_only' => $subscription->isReadOnlyAt($clock->now()),
            'starts_at' => $subscription->startsAt()->format(DATE_ATOM),
            'ends_at' => $subscription->endsAt()->format(DATE_ATOM),
        ];
    }
}

