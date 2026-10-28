<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Application\Commands\RegisterSaleCommand;
use App\Modules\Sales\Application\RegisterSale;
use App\Modules\Sales\Domain\Exceptions\ProductNotFound;
use App\Modules\Sales\Domain\PaymentMethod;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleItem;
use App\Modules\Sales\Presentation\Requests\RegisterSaleRequest;
use Illuminate\Http\JsonResponse;

final class RegisterSaleController extends Controller
{
    public function __construct(
        private readonly RegisterSale $registerSale,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(RegisterSaleRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Пользователь не состоит в активной компании.',
            ]], 403);
        }

        $headerKey = $request->header('Idempotency-Key');
        $idempotencyKey = is_string($headerKey) && trim($headerKey) !== ''
            ? mb_substr(trim($headerKey), 0, 128)
            : null;

        /** @var array<int, array{product_id: int|string, quantity: int|string}> $rawProducts */
        $rawProducts = $request->validated('products') ?? [];
        $products = array_values(array_map(
            static fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) $line['quantity'],
            ],
            $rawProducts
        ));

        /** @var array<int, int|string> $rawMembershipTypeIds */
        $rawMembershipTypeIds = $request->validated('membership_type_ids') ?? [];
        $membershipTypeIds = array_values(array_map(
            static fn (int|string $id): int => (int) $id,
            $rawMembershipTypeIds
        ));

        $clientId = $request->validated('client_id');

        try {
            $sale = $this->registerSale->execute(new RegisterSaleCommand(
                companyId: $companyId,
                acceptedByUserId: $user->id,
                clientId: $clientId === null ? null : (int) $clientId,
                paymentMethod: PaymentMethod::from((string) $request->validated('payment_method')),
                products: $products,
                membershipTypeIds: $membershipTypeIds,
                idempotencyKey: $idempotencyKey
            ));
        } catch (ProductNotFound $e) {
            return response()->json(['error' => [
                'code' => 'NOT_FOUND',
                'message' => $e->getMessage(),
            ]], 404);
        }

        return response()->json(['data' => self::serializeSale($sale)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeSale(Sale $sale): array
    {
        return [
            'id' => $sale->id(),
            'company_id' => $sale->companyId(),
            'client_id' => $sale->clientId(),
            'status' => $sale->status()->value,
            'payment_method' => $sale->paymentMark()->method->value,
            'total_minor' => $sale->paymentMark()->amount->amount(),
            'currency' => $sale->paymentMark()->amount->currency(),
            'accepted_by_user_id' => $sale->paymentMark()->acceptedByUserId,
            'paid_at' => $sale->paymentMark()->paidAt->format(DATE_ATOM),
            'refund_reason' => $sale->refundReason(),
            'refunded_by_user_id' => $sale->refundedByUserId(),
            'refunded_at' => $sale->refundedAt()?->format(DATE_ATOM),
            'items' => array_map(static fn (SaleItem $item): array => [
                'name' => $item->name,
                'unit_price_minor' => $item->unitPrice->amount(),
                'currency' => $item->unitPrice->currency(),
                'quantity' => $item->quantity,
                'line_total_minor' => $item->lineTotal()->amount(),
                'product_id' => $item->productId,
                'membership_type_id' => $item->membershipTypeId,
                'issued_membership_id' => $item->issuedMembershipId,
            ], $sale->items()),
        ];
    }
}
