<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Analytics\Application\GetOverviewAnalytics;
use App\Modules\Analytics\Presentation\Requests\AnalyticsPeriodRequest;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Contracts\ItemPopularity;
use App\Shared\Domain\Clock;
use Illuminate\Http\JsonResponse;

final class OverviewAnalyticsController extends Controller
{
    public function __construct(
        private readonly GetOverviewAnalytics $getOverviewAnalytics,
        private readonly StaffCompanyResolverInterface $companies,
        private readonly Clock $clock,
    ) {}

    public function __invoke(AnalyticsPeriodRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null || !$this->companies->isOwner($user->id, $companyId)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Финансовая аналитика доступна только владельцу компании.',
                ],
            ], 403);
        }

        $from = $request->fromDate($this->clock);
        $to = $request->toDate($this->clock);

        $result = $this->getOverviewAnalytics->execute($companyId, $from, $to, $request->inactiveDays());

        return response()->json([
            'data' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'revenue' => RevenueReportController::serializeRevenue($result->revenue),
                'clients' => [
                    'total_clients' => $result->clients->totalClients,
                    'new_clients' => $result->clients->newClients,
                    'existing_clients' => $result->clients->existingClients,
                ],
                'visits' => [
                    'total_visits' => $result->visits->totalVisits,
                    'qr_visits' => $result->visits->qrVisits,
                    'manual_visits' => $result->visits->manualVisits,
                    'by_day' => $result->visits->byDay,
                    'inactive_days_threshold' => $result->inactiveDaysThreshold,
                    'inactive_clients_count' => $result->inactiveClientsCount,
                ],
                'popular_memberships' => array_map(
                    [self::class, 'serializePopularity'],
                    $result->popularMemberships,
                ),
                'popular_products' => array_map(
                    [self::class, 'serializePopularity'],
                    $result->popularProducts,
                ),
            ],
        ]);
    }

    /**
     * @return array{item_id: int, name: string, sold_count: int, revenue_minor: int, currency: string}
     */
    public static function serializePopularity(ItemPopularity $item): array
    {
        return [
            'item_id' => $item->itemId,
            'name' => $item->name,
            'sold_count' => $item->soldCount,
            'revenue_minor' => $item->revenueMinor,
            'currency' => $item->currency,
        ];
    }
}
