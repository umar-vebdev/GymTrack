<?php

declare(strict_types=1);

namespace App\Modules\Clients\Domain;

interface ClientRepositoryInterface
{
    public function findById(int $id): ?Client;

    /**
     * Список клиентов компании с необязательным поиском по имени или части телефона.
     *
     * @return array{items: list<Client>, total: int}
     */
    public function search(int $companyId, ?string $search, int $page, int $perPage): array;

    public function existsByPhone(int $companyId, string $phone): bool;

    /**
     * Проставляет user_id всем карточкам с этим телефоном (во всех компаниях), где он ещё не задан.
     */
    public function linkUserByPhone(int $userId, string $phone): void;

    public function create(int $companyId, string $name, string $phone, ?int $userId = null): int;
}
