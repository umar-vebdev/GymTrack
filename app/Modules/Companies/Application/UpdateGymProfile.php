<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;

final class UpdateGymProfile
{
    public function __construct(
        private readonly GymRepositoryInterface $gyms,
        private readonly GymProfileRepositoryInterface $profiles,
    ) {}

    public function execute(UpdateGymProfileCommand $command): GymProfile
    {
        $gym = $this->gyms->findById($command->gymId);
        if ($gym === null || $gym->companyId() !== $command->companyId) {
            throw GymNotFound::withId($command->gymId);
        }

        $profile = $this->profiles->findByGymId($command->gymId) ?? GymProfile::draft($command->gymId);

        $profile->update(
            $command->description,
            $command->address,
            $command->latitude,
            $command->longitude,
            $command->phone,
            $command->workingHours,
            $command->amenities,
            $command->district,
        );

        $this->profiles->save($profile);

        return $profile;
    }
}
