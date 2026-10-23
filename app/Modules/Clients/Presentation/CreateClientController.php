<?php

declare(strict_types=1);

namespace App\Modules\Clients\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Clients\Application\CreateClient;
use App\Modules\Clients\Domain\ClientPhoneAlreadyExists;
use App\Modules\Clients\Presentation\Requests\CreateClientRequest;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use Illuminate\Http\JsonResponse;

final class CreateClientController extends Controller
{
    public function __construct(
        private readonly CreateClient $createClient,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(CreateClientRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null) {
            return $this->error('FORBIDDEN', 'Доступ только для сотрудников зала.', 403);
        }

        try {
            $id = $this->createClient->execute(
                companyId: $companyId,
                name: $request->validated('name'),
                phone: $request->validated('phone')
            );
        } catch (ClientPhoneAlreadyExists $e) {
            return $this->error('CLIENT_PHONE_EXISTS', $e->getMessage(), 409);
        }

        return response()->json(['data' => ['id' => $id]], 201);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
