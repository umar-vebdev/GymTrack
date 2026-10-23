<?php

declare(strict_types=1);

namespace App\Modules\Clients\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Clients\Application\MergeClients;
use App\Modules\Clients\Domain\ClientNotFound;
use App\Modules\Clients\Presentation\Requests\MergeClientsRequest;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use Illuminate\Http\JsonResponse;

final class MergeClientsController extends Controller
{
    public function __construct(
        private readonly MergeClients $mergeClients,
        private readonly StaffCompanyResolverInterface $companies
    ) {}

    public function __invoke(MergeClientsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId === null || !$this->companies->isOwner($user->id, $companyId)) {
            return $this->error('FORBIDDEN', 'Объединять карточки может только владелец.', 403);
        }

        try {
            $this->mergeClients->execute(
                $companyId,
                (int) $request->validated('source_id'),
                (int) $request->validated('target_id')
            );
        } catch (ClientNotFound $e) {
            return $this->error('CLIENT_NOT_FOUND', $e->getMessage(), 404);
        }

        return response()->json(['data' => ['merged' => true]]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
