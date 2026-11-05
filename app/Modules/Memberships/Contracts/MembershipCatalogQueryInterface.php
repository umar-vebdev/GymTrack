<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

/**
 * Публичный контракт чтения актуальных типов абонементов для модуля Catalog (Бизнес-ТЗ 4.3, 7; D-010).
 */
interface MembershipCatalogQueryInterface
{
    /**
     * Активные типы абонементов компании (сортировка по public_id).
     *
     * @return list<PublicMembershipType>
     */
    public function listActiveForCompany(int $companyId): array;

    /**
     * Идентификаторы компаний, у которых есть хотя бы один активный тип абонемента
     * указанной валюты во включительном диапазоне цен (в минимальных единицах).
     *
     * @return list<int>
     */
    public function findCompanyIdsByPriceRange(string $currency, ?int $priceMin = null, ?int $priceMax = null): array;
}

