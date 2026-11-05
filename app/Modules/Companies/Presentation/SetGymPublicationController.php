<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\SetGymPublication;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Presentation\Policies\GymProfilePolicy;
use App\Modules\Companies\Presentation\Requests\SetGymPublicationRequest;
use App\Modules\Companies\Presentation\Resources\GymProfileResource;
use Illuminate\Http\JsonResponse;

final class SetGymPublicationController extends Controller
{
    public function __construct(
        private readonly SetGymPublication $setPublication,
        private readonly StaffCompanyResolverInterface $staff,
        private readonly GymProfilePolicy $policy,
    ) {}

    public function __invoke(SetGymPublicationRequest $request, int $gym): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $this->staff->companyIdForUser($user->id);
        if ($companyId === null) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Нет доступа к компании.']], 403);
        }

        $permission = $this->policy->setPublication($user, $companyId, $gym);
        if ($permission->denied()) {
            $status = $permission->status() ?? 403;

            return response()->json(['error' => [
                'code' => $status === 404 ? 'NOT_FOUND' : 'FORBIDDEN',
                'message' => $permission->message(),
            ]], $status);
        }

        try {
            $profile = $this->setPublication->execute($companyId, $gym, $request->boolean('is_public'));
        } catch (GymNotFound $exception) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => $exception->getMessage()]], 404);
        }

        return response()->json(['data' => GymProfileResource::toArray($profile)]);
    }
}
