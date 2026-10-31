<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\UpdateCompanyCurrency;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Presentation\Requests\UpdateCompanyCurrencyRequest;
use Illuminate\Http\JsonResponse;

final class UpdateCompanyCurrencyController extends Controller
{
    public function __construct(
        private readonly UpdateCompanyCurrency $updateCompanyCurrency,
        private readonly StaffCompanyResolverInterface $staffResolver
    ) {}

    public function __invoke(UpdateCompanyCurrencyRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null || !$this->staffResolver->isOwner($user->id, $companyId)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Изменять валюту может только владелец компании.',
                ],
            ], 403);
        }

        $result = $this->updateCompanyCurrency->execute(
            companyId: $companyId,
            currencyId: (int) $request->validated('currency_id')
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
