<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class RegisterCompany
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companies,
        private readonly GymRepositoryInterface $gyms,
        private readonly StaffMemberRepositoryInterface $staff
    ) {}

    /**
     * @return array{company_id: int, gym_id: int}
     */
    public function execute(int $ownerId, string $companyName, string $gymName, ?string $gymAddress): array
    {
        return DB::transaction(function () use ($ownerId, $companyName, $gymName, $gymAddress) {
            $companyId = $this->companies->create(
                ownerId: $ownerId,
                name: $companyName
            );

            $this->staff->create(
                userId: $ownerId,
                companyId: $companyId,
                role: Role::OWNER
            );

            $gymId = $this->gyms->create(
                companyId: $companyId,
                name: $gymName,
                address: $gymAddress
            );

            return [
                'company_id' => $companyId,
                'gym_id' => $gymId,
            ];
        });
    }
}
