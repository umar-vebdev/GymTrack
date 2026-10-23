<?php

declare(strict_types=1);

namespace App\Modules\Clients\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Clients\Application\SearchClients;
use App\Modules\Clients\Domain\Client;
use App\Modules\Clients\Presentation\Requests\SearchClientsRequest;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use Illuminate\Http\JsonResponse;

final class SearchClientsController extends Controller
{
    public function __construct(
        private readonly SearchClients $searchClients,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(SearchClientsRequest $request): JsonResponse
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

        $page = (int) $request->validated('page', 1);
        $perPage = (int) $request->validated('per_page', 20);

        $result = $this->searchClients->execute(
            $companyId,
            $request->validated('search'),
            $page,
            $perPage
        );

        return response()->json([
            'data' => array_map(static fn (Client $c): array => [
                'id' => $c->id(),
                'name' => $c->name(),
                'phone' => $c->phone(),
                'linked' => $c->userId() !== null,
            ], $result['items']),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
        ]);
    }
}
