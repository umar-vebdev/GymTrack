<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class SubmitGymProfileForModeration
{
    public function __construct(
        private readonly GymRepositoryInterface $gyms,
        private readonly GymProfileRepositoryInterface $profiles,
    ) {}

    public function execute(int $companyId, int $gymId): GymProfile
    {
        return DB::transaction(function () use ($companyId, $gymId): GymProfile {
            $gym = $this->gyms->findByIdForUpdate($gymId);
            if ($gym === null || $gym->companyId() !== $companyId) {
                throw GymNotFound::withId($gymId);
            }

            $profile = $this->profiles->findByGymId($gymId) ?? GymProfile::draft($gymId);

            $profile->submit();

            $this->profiles->save($profile);

            return $profile;
        });
    }
}
