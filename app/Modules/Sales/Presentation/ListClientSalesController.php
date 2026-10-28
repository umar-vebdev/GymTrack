<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Application\ListClientSales;
use App\Modules\Sales\Domain\Exceptions\ClientNotAvailable;
use App\Modules\Sales\Domain\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListClientSalesController extends Controller
{
    public function __construct(
        private readonly ListClientSales $listClientSales,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(int $id, Request $request): JsonResponse
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

        try {
            $sales = $this->listClientSales->execute($companyId, $id);
        } catch (ClientNotAvailable $e) {
            return response()->json(['error' => [
                'code' => 'CLIENT_NOT_FOUND',
                'message' => $e->getMessage(),
            ]], 404);
        }

        return response()->json([
            'data' => array_map(
                static fn (Sale $sale): array => RegisterSaleController::serializeSale($sale),
                $sales
            ),
        ]);
    }
}

