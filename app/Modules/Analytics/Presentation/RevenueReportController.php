<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Analytics\Application\GetRevenueReport;
use App\Modules\Analytics\Presentation\Requests\AnalyticsPeriodRequest;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Contracts\RevenueSummary;
use App\Shared\Domain\Clock;
use Illuminate\Http\JsonResponse;

final class RevenueReportController extends Controller
{
    public function __construct(
        private readonly GetRevenueReport $getRevenueReport,
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

        $summary = $this->getRevenueReport->execute($companyId, $from, $to);

        return response()->json([
            'data' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                ...self::serializeRevenue($summary),
            ],
        ]);
    }

    /**
     * @return array{
     *     total_minor: int,
     *     memberships_minor: int,
     *     products_minor: int,
     *     by_payment_method: array{cash: int, card_terminal: int, transfer: int},
     *     sales_count: int,
     *     currency: string
     * }
     */
    public static function serializeRevenue(RevenueSummary $summary): array
    {
        return [
            'total_minor' => $summary->totalMinor,
            'memberships_minor' => $summary->membershipsMinor,
            'products_minor' => $summary->productsMinor,
            'by_payment_method' => $summary->byPaymentMethod,
            'sales_count' => $summary->salesCount,
            'currency' => $summary->currency,
        ];
    }
}
