<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Companies\Application\AddStaffMember;
use App\Modules\Companies\Application\ListStaffMembers;
use App\Modules\Companies\Application\RemoveStaffMember;
use App\Modules\Companies\Application\StaffMemberView;
use App\Modules\Companies\Application\UpdateStaffMemberRole;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\Exceptions\StaffMemberNotFound;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Presentation\Requests\AddStaffMemberRequest;
use App\Modules\Companies\Presentation\Requests\UpdateStaffMemberRoleRequest;
use App\Modules\Companies\Presentation\Resources\StaffMemberResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffController extends Controller
{
    public function __construct(
        private readonly ListStaffMembers $listStaff,
        private readonly AddStaffMember $addStaff,
        private readonly UpdateStaffMemberRole $updateRole,
        private readonly RemoveStaffMember $removeStaff,
        private readonly StaffCompanyResolverInterface $staffResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $this->resolveOwnerCompanyId($user);

        if ($companyId === null) {
            return $this->forbiddenResponse();
        }

        $members = $this->listStaff->execute($companyId);

        return response()->json([
            'data' => array_map(static fn (StaffMemberView $view): array => StaffMemberResource::toArray($view), $members),
        ]);
    }

    public function store(AddStaffMemberRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $this->resolveOwnerCompanyId($user);

        if ($companyId === null) {
            return $this->forbiddenResponse();
        }

        /** @var array{phone: string, role: string} $data */
        $data = $request->validated();

        try {
            $member = $this->addStaff->execute(
                companyId: $companyId,
                actorUserId: $user->id,
                phone: $data['phone'],
                role: Role::from($data['role']),
            );
        } catch (DomainException $e) {
            return response()->json([
                'error' => ['code' => 'BAD_REQUEST', 'message' => $e->getMessage()],
            ], 400);
        }

        return response()->json([
            'data' => StaffMemberResource::toArray($member),
        ], 201);
    }

    public function update(UpdateStaffMemberRoleRequest $request, int $staff): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $this->resolveOwnerCompanyId($user);

        if ($companyId === null) {
            return $this->forbiddenResponse();
        }

        /** @var array{role: string} $data */
        $data = $request->validated();

        try {
            $member = $this->updateRole->execute(
                companyId: $companyId,
                actorUserId: $user->id,
                staffId: $staff,
                newRole: Role::from($data['role']),
            );
        } catch (StaffMemberNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        } catch (DomainException $e) {
            return response()->json([
                'error' => ['code' => 'BAD_REQUEST', 'message' => $e->getMessage()],
            ], 400);
        }

        return response()->json([
            'data' => StaffMemberResource::toArray($member),
        ]);
    }

    public function destroy(Request $request, int $staff): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $companyId = $this->resolveOwnerCompanyId($user);

        if ($companyId === null) {
            return $this->forbiddenResponse();
        }

        try {
            $this->removeStaff->execute(
                companyId: $companyId,
                actorUserId: $user->id,
                staffId: $staff,
            );
        } catch (StaffMemberNotFound $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], 404);
        } catch (DomainException $e) {
            return response()->json([
                'error' => ['code' => 'BAD_REQUEST', 'message' => $e->getMessage()],
            ], 400);
        }

        return response()->json(['data' => null]);
    }

    private function resolveOwnerCompanyId(User $user): ?int
    {
        $companyId = $this->staffResolver->companyIdForUser($user->id);

        if ($companyId === null || !$this->staffResolver->isOwner($user->id, $companyId)) {
            return null;
        }

        return $companyId;
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'FORBIDDEN', 'message' => 'Управлять сотрудниками может только владелец компании.'],
        ], 403);
    }
}
