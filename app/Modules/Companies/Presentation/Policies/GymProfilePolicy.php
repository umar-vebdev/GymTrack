<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation\Policies;

use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use Illuminate\Auth\Access\Response;

final class GymProfilePolicy
{
    public function __construct(
        private readonly StaffCompanyResolverInterface $staff,
        private readonly GymRepositoryInterface $gyms,
    ) {}

    public function setPublication(User $user, int $companyId, int $gymId): Response
    {
        if (!$this->staff->isOwner($user->id, $companyId)) {
            return Response::deny('Управлять публикацией может только владелец компании.');
        }

        $gym = $this->gyms->findById($gymId);
        if ($gym === null || $gym->companyId() !== $companyId) {
            return Response::denyAsNotFound('Запрашиваемый зал не найден.');
        }

        return Response::allow();
    }
}
