<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Application\ListProducts;
use App\Modules\Sales\Domain\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListProductsController extends Controller
{
    public function __construct(
        private readonly ListProducts $listProducts,
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

        $onlyActive = $request->has('only_active')
            ? $request->boolean('only_active')
            : null;

        $products = $this->listProducts->execute($companyId, $onlyActive);

        return response()->json([
            'data' => array_map(static fn (Product $product): array => [
                'id' => $product->id(),
                'name' => $product->name(),
                'price_minor' => $product->price()->amount(),
                'currency' => $product->price()->currency(),
                'stock_quantity' => $product->stockQuantity(),
                'is_active' => $product->isActive(),
            ], $products),
        ]);
    }
}
