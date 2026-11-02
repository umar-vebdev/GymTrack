<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Sales\Application\ListMyPurchases;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Presentation\Requests\ListMyPurchasesRequest;
use Illuminate\Http\JsonResponse;

final class ListMyPurchasesController extends Controller
{
    public function __construct(
        private readonly ListMyPurchases $listMyPurchases
    ) {}

    public function __invoke(ListMyPurchasesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $page = (int) $request->validated('page', 1);
        $perPage = (int) $request->validated('per_page', 20);
        $companyId = $request->validated('company_id') !== null ? (int) $request->validated('company_id') : null;
        /** @var string|null $status */
        $status = $request->validated('status');

        $result = $this->listMyPurchases->execute(
            userId: $user->id,
            companyId: $companyId,
            status: $status,
            page: $page,
            perPage: $perPage
        );

        return response()->json([
            'data' => array_map(
                static fn (Sale $sale): array => RegisterSaleController::serializeSale($sale),
                $result['items']
            ),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
            ],
        ]);
    }
}
