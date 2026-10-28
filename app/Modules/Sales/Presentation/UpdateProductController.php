<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Application\UpdateProduct;
use App\Modules\Sales\Domain\Exceptions\ProductNotFound;
use App\Modules\Sales\Presentation\Requests\UpdateProductRequest;
use Illuminate\Http\JsonResponse;

final class UpdateProductController extends Controller
{
    public function __construct(
        private readonly UpdateProduct $updateProduct,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(int $id, UpdateProductRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null || !$this->companies->isOwner($user->id, $companyId)) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Изменять товары может только владелец компании.',
            ]], 403);
        }

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : null;
        $updateStock = array_key_exists('stock_quantity', $request->all());
        $stockRaw = $request->validated('stock_quantity');
        $stockQuantity = $stockRaw === null ? null : (int) $stockRaw;

        try {
            $product = $this->updateProduct->execute(
                companyId: $companyId,
                productId: $id,
                isActive: $isActive,
                updateStock: $updateStock,
                stockQuantity: $stockQuantity
            );
        } catch (ProductNotFound $e) {
            return response()->json(['error' => [
                'code' => 'PRODUCT_NOT_FOUND',
                'message' => $e->getMessage(),
            ]], 404);
        }

        return response()->json([
            'data' => [
                'id' => $product->id(),
                'name' => $product->name(),
                'price_minor' => $product->price()->amount(),
                'currency' => $product->price()->currency(),
                'stock_quantity' => $product->stockQuantity(),
                'is_active' => $product->isActive(),
            ],
        ]);
    }
}
