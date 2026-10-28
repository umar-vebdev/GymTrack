<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Application\ListCompanySales;
use App\Modules\Sales\Domain\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListCompanySalesController extends Controller
{
    public function __construct(
        private readonly ListCompanySales $listCompanySales,
        private readonly StaffCompanyResolverInterface $companies
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

        $sales = $this->listCompanySales->execute($companyId);

        return response()->json([
            'data' => array_map(
                static fn (Sale $sale): array => RegisterSaleController::serializeSale($sale),
                $sales
            ),
        ]);
    }
}
