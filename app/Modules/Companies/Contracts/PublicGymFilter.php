<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

/**
 * Параметры фильтрации и пагинации публичных витрин залов (Бизнес-ТЗ 7; D-010).
 */
final class PublicGymFilter
{
    public readonly ?string $query;

    public readonly ?string $district;

    /** @var list<string> */
    public readonly array $amenities;

    /** @var list<int>|null */
    public readonly ?array $companyIds;

    public readonly int $page;

    public readonly int $perPage;

    /**
     * @param  list<string>  $amenities
     * @param  list<int>|null  $companyIds
     */
    public function __construct(
        ?string $query = null,
        ?string $district = null,
        array $amenities = [],
        ?array $companyIds = null,
        int $page = 1,
        int $perPage = 20,
    ) {
        $trimmedQuery = $query !== null ? trim($query) : null;
        $this->query = $trimmedQuery !== '' ? $trimmedQuery : null;

        $trimmedDistrict = $district !== null ? trim($district) : null;
        $this->district = $trimmedDistrict !== '' ? $trimmedDistrict : null;

        $normalizedAmenities = [];
        foreach ($amenities as $amenity) {
            $trimmed = trim($amenity);
            if ($trimmed !== '' && !in_array($trimmed, $normalizedAmenities, true)) {
                $normalizedAmenities[] = $trimmed;
            }
        }
        $this->amenities = $normalizedAmenities;

        $this->companyIds = $companyIds !== null ? array_values(array_unique($companyIds)) : null;
        $this->page = max(1, $page);
        $this->perPage = min(100, max(1, $perPage));
    }
}

