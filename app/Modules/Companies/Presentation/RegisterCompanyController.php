<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\RegisterCompany;
use App\Modules\Companies\Presentation\Requests\RegisterCompanyRequest;
use Illuminate\Http\JsonResponse;

final class RegisterCompanyController extends Controller
{
    public function __construct(
        private readonly RegisterCompany $registerCompany
    ) {}

    public function __invoke(RegisterCompanyRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->registerCompany->execute(
            ownerId: $user->id,
            companyName: $request->validated('company_name'),
            gymName: $request->validated('gym_name'),
            gymAddress: $request->validated('gym_address')
        );

        return response()->json([
            'data' => $result,
        ], 201);
    }
}
