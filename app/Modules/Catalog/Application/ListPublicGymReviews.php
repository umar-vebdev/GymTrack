<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Companies\Contracts\GymReviewCatalogContractInterface;
use App\Modules\Companies\Contracts\PublicGymQueryInterface;
use App\Modules\Companies\Contracts\PublicGymReviewDto;

/**
 * Сценарий получения публичных отзывов опубликованного зала с пагинацией (Бизнес-ТЗ 7, 11).
 */
final class ListPublicGymReviews
{
    public function __construct(
        private readonly PublicGymQueryInterface $gyms,
        private readonly GymReviewCatalogContractInterface $reviews,
    ) {}

    /**
     * @return array{items: list<PublicGymReviewDto>, total: int}|null
     */
    public function execute(string $publicId, int $page = 1, int $perPage = 20): ?array
    {
        $gym = $this->gyms->findByPublicId($publicId);
        if ($gym === null) {
            return null;
        }

        return $this->reviews->listForGym($gym->gymId, $page, $perPage);
    }
}
