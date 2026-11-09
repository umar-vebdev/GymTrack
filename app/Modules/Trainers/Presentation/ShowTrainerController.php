<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Trainers\Application\GetTrainer;
use App\Modules\Trainers\Domain\Exceptions\TrainerNotFound;
use App\Modules\Trainers\Presentation\Resources\TrainerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowTrainerController extends Controller
{
    public function __construct(
        private readonly GetTrainer $getTrainer,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function __invoke(Request $request, int $trainer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Доступ только для сотрудников зала.',
                ],
            ], 403);
        }

        try {
            $entity = $this->getTrainer->execute($companyId, $trainer);
        } catch (TrainerNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        }

        return response()->json(['data' => TrainerResource::toArray($entity)]);
    }
}
