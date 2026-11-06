<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Companies\Contracts\PublicGymFilter;
use App\Modules\Companies\Contracts\PublicGymProfile;
use App\Modules\Companies\Contracts\PublicGymQueryInterface;
use App\Modules\Memberships\Contracts\MembershipCatalogQueryInterface;

/**
 * Сценарий поиска залов в публичном каталоге через контракты Companies и Memberships (Бизнес-ТЗ 7; Тех. ТЗ 3.5, 3.7).
 */
final class SearchGyms
{
    public function __construct(
        private readonly PublicGymQueryInterface $gyms,
        private readonly MembershipCatalogQueryInterface $memberships,
    ) {}

    /**
     * @return array{items: list<PublicGymProfile>, total: int}
     */
    public function execute(SearchGymsQuery $query): array
    {
        $companyIds = null;

        if ($query->currency !== null) {
            $companyIds = $this->memberships->findCompanyIdsByPriceRange(
                currency: $query->currency,
                priceMin: $query->priceMin,
                priceMax: $query->priceMax,
            );

            if ($companyIds === []) {
                return [
                    'items' => [],
                    'total' => 0,
                ];
            }
        }

        return $this->gyms->search(new PublicGymFilter(
            query: $query->query,
            district: $query->district,
            amenities: $query->amenities,
            companyIds: $companyIds,
            page: $query->page,
            perPage: $query->perPage,
        ));
    }
}
