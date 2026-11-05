<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

/**
 * Публичный контракт чтения одобренных и опубликованных витрин залов для модуля Catalog.
 */
interface PublicGymQueryInterface
{
    /**
     * Поиск опубликованных витрин с фильтрацией и пагинацией (сортировка по public_id).
     *
     * @return array{items: list<PublicGymProfile>, total: int}
     */
    public function search(PublicGymFilter $filter): array;

    /**
     * Карточка опубликованного зала по публичному идентификатору (ULID).
     */
    public function findByPublicId(string $publicId): ?PublicGymProfile;
}

