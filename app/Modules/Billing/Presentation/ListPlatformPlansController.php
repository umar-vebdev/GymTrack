<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\ListPlatformPlans;
use App\Modules\Billing\Domain\PlatformPlan;
use Illuminate\Http\JsonResponse;

final class ListPlatformPlansController extends Controller
{
    public function __construct(
        private readonly ListPlatformPlans $listPlans
    ) {}

    public function __invoke(): JsonResponse
    {
        $plans = $this->listPlans->execute(onlyActive: true);

        return response()->json([
            'data' => array_map(static fn (PlatformPlan $plan): array => [
                'id' => $plan->id(),
                'name' => $plan->name(),
                'period' => $plan->period()->value,
                'price_minor' => $plan->price()->amount(),
                'currency' => $plan->price()->currency(),
                'max_gyms' => $plan->maxGyms(),
                'max_staff' => $plan->maxStaff(),
                'is_active' => $plan->isActive(),
            ], $plans),
        ]);
    }
}
