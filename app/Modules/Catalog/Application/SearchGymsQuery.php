<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Companies\Contracts\GeoBounds;
use App\Modules\Companies\Contracts\GeoRadius;
use InvalidArgumentException;

/**
 * Входной DTO поиска публичных витрин залов в каталоге (Бизнес-ТЗ 7; D-010).
 */
final class SearchGymsQuery
{
    public readonly ?string $query;

    public readonly ?string $district;

    /** @var list<string> */
    public readonly array $amenities;

    public readonly ?int $priceMin;

    public readonly ?int $priceMax;

    public readonly ?string $currency;

    public readonly int $page;

    public readonly int $perPage;

    /**
     * @param  list<string>  $amenities
     */
    public function __construct(
        ?string $query = null,
        ?string $district = null,
        array $amenities = [],
        ?int $priceMin = null,
        ?int $priceMax = null,
        ?string $currency = null,
        int $page = 1,
        int $perPage = 20,
        public readonly ?GeoBounds $bounds = null,
        public readonly ?GeoRadius $radius = null,
    ) {
        if ($priceMin !== null && $priceMin < 0) {
            throw new InvalidArgumentException('Минимальная цена не может быть отрицательной.');
        }

        if ($priceMax !== null && $priceMax < 0) {
            throw new InvalidArgumentException('Максимальная цена не может быть отрицательной.');
        }

        $normalizedCurrency = $currency !== null ? strtoupper(trim($currency)) : null;
        $this->currency = $normalizedCurrency !== '' ? $normalizedCurrency : null;

        if (($priceMin !== null || $priceMax !== null) && $this->currency === null) {
            throw new InvalidArgumentException('При фильтрации по цене необходимо указать валюту.');
        }

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

        $this->priceMin = $priceMin;
        $this->priceMax = $priceMax;
        $this->page = max(1, $page);
        $this->perPage = min(100, max(1, $perPage));
    }
}
