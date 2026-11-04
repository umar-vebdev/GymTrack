<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\ModerationStatus;

final class ListGymProfilesForModeration
{
    public function __construct(
        private readonly GymProfileRepositoryInterface $profiles,
    ) {}

    /**
     * @return array{items: list<GymProfile>, total: int}
     */
    public function execute(ModerationStatus $status, int $page = 1, int $perPage = 20): array
    {
        return $this->profiles->paginateByStatus($status, $page, $perPage);
    }
}

