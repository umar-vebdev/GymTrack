<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\UploadGymPhoto;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Presentation\Policies\GymPhotoPolicy;
use App\Modules\Companies\Presentation\Requests\UploadGymPhotoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

final class UploadGymPhotoController extends Controller
{
    public function __construct(
        private readonly UploadGymPhoto $upload,
        private readonly StaffCompanyResolverInterface $staff,
        private readonly GymPhotoPolicy $policy,
    ) {}

    public function __invoke(UploadGymPhotoRequest $request, int $gym): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $this->staff->companyIdForUser($user->id);
        if ($companyId === null) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Нет доступа к компании.']], 403);
        }

        $permission = $this->policy->upload($user, $companyId, $gym);
        if ($permission->denied()) {
            $status = $permission->status() ?? 403;

            return response()->json(['error' => [
                'code' => $status === 404 ? 'NOT_FOUND' : 'FORBIDDEN',
                'message' => $permission->message(),
            ]], $status);
        }

        try {
            $photo = $this->upload->execute($companyId, $gym, $request->validated('photo'));
        } catch (GymNotFound $exception) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => $exception->getMessage()]], 404);
        }

        return response()->json(['data' => [
            'id' => $photo->id,
            'gym_id' => $photo->gymId,
            'url' => Storage::disk('public')->url($photo->path),
            'order' => $photo->order,
            'created_at' => $photo->createdAt->format(DATE_ATOM),
        ]], 201);
    }
}
