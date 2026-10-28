<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Sales\Application\CreateProduct;
use App\Modules\Sales\Presentation\Requests\CreateProductRequest;
use Illuminate\Http\JsonResponse;

final class CreateProductController extends Controller
{
    public function __construct(
        private readonly CreateProduct $createProduct,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(CreateProductRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null || !$this->companies->isOwner($user->id, $companyId)) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Создавать товары может только владелец компании.',
            ]], 403);
        }

        $stock = $request->validated('stock_quantity');
        $currency = $request->validated('currency');

        $id = $this->createProduct->execute(
            companyId: $companyId,
            name: (string) $request->validated('name'),
            priceMinor: (int) $request->validated('price_minor'),
            stockQuantity: $stock === null ? null : (int) $stock,
            currency: is_string($currency) ? strtoupper($currency) : null
        );

        return response()->json(['data' => ['id' => $id]], 201);
    }
}
