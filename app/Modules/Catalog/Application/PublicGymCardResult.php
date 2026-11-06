<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Companies\Contracts\PublicGymProfile;
use App\Modules\Memberships\Contracts\PublicMembershipType;

/**
 * Результат сценария получения публичной карточки зала с актуальными абонементами (Бизнес-ТЗ 7; Тех. ТЗ 3.5, 3.7).
 */
final class PublicGymCardResult
{
    /**
     * @param  list<PublicMembershipType>  $membershipTypes
     */
    public function __construct(
        public readonly PublicGymProfile $gym,
        public readonly array $membershipTypes,
    ) {}
}

