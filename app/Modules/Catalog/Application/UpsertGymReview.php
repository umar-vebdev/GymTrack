<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Exceptions\GymReviewDenied;
use App\Modules\Companies\Contracts\GymReviewCatalogContractInterface;
use App\Modules\Companies\Contracts\PublicGymQueryInterface;
use App\Modules\Companies\Contracts\PublicGymReviewDto;
use App\Modules\Visits\Contracts\ClientGymVisitCheckInterface;

/**
 * Сценарий создания или обновления отзыва клиента о публичном зале (Бизнес-ТЗ 7, 11).
 */
final class UpsertGymReview
{
    public function __construct(
        private readonly PublicGymQueryInterface $gyms,
        private readonly ClientGymVisitCheckInterface $visitCheck,
        private readonly GymReviewCatalogContractInterface $reviews,
    ) {}

    public function execute(string $publicId, int $userId, int $rating, ?string $comment): ?PublicGymReviewDto
    {
        $gym = $this->gyms->findByPublicId($publicId);
        if ($gym === null) {
            return null;
        }

        if (!$this->visitCheck->hasCompletedVisitInGym($userId, $gym->companyId, $gym->gymId)) {
            throw GymReviewDenied::visitRequired();
        }

        return $this->reviews->upsertReview($gym->companyId, $gym->gymId, $userId, $rating, $comment);
    }
}
