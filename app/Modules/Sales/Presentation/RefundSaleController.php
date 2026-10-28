<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Application\RefundSale;
use App\Modules\Sales\Domain\Exceptions\SaleNotFound;
use App\Modules\Sales\Presentation\Requests\RefundSaleRequest;
use Illuminate\Http\JsonResponse;

final class RefundSaleController extends Controller
{
    public function __construct(
        private readonly RefundSale $refundSale,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(int $id, RefundSaleRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null || !$this->companies->isOwner($user->id, $companyId)) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Возвращать продажу может только владелец компании.',
            ]], 403);
        }

        try {
            $sale = $this->refundSale->execute(
                companyId: $companyId,
                saleId: $id,
                reason: $request->string('reason')->toString(),
                refundedByUserId: $user->id
            );
        } catch (SaleNotFound $e) {
            return response()->json(['error' => [
                'code' => 'SALE_NOT_FOUND',
                'message' => $e->getMessage(),
            ]], 404);
        }

        return response()->json([
            'data' => RegisterSaleController::serializeSale($sale),
        ]);
    }
}

