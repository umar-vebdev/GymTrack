<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

interface GymReviewCatalogContractInterface
{
    public function upsertReview(
        int $companyId,
        int $gymId,
        int $userId,
        int $rating,
        ?string $comment,
    ): PublicGymReviewDto;

    /**
     * @return array{items: list<PublicGymReviewDto>, total: int}
     */
    public function listForGym(int $gymId, int $page = 1, int $perPage = 20): array;
}

