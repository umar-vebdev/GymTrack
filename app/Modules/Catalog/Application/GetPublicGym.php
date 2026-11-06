<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Companies\Contracts\PublicGymQueryInterface;
use App\Modules\Memberships\Contracts\MembershipCatalogQueryInterface;

/**
 * Сценарий получения публичной карточки зала по ULID через контракты Companies и Memberships (Бизнес-ТЗ 7; Тех. ТЗ 3.5, 3.7).
 */
final class GetPublicGym
{
    public function __construct(
        private readonly PublicGymQueryInterface $gyms,
        private readonly MembershipCatalogQueryInterface $memberships,
    ) {}

    public function execute(string $publicId): ?PublicGymCardResult
    {
        $gym = $this->gyms->findByPublicId($publicId);
        if ($gym === null) {
            return null;
        }

        return new PublicGymCardResult(
            gym: $gym,
            membershipTypes: $this->memberships->listActiveForCompany($gym->companyId),
        );
    }
}
