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

    public function delete(int $id): void;

    public function assignUser(int $id, int $userId): void;

    public function existsByPhone(int $companyId, string $phone): bool;

    /**
     * ID карточки клиента, привязанной к аккаунту, в указанной компании (при нескольких — самая ранняя).
     */
    public function findIdByUser(int $userId, int $companyId): ?int;

    /**
     * Проставляет user_id всем карточкам с этим телефоном (во всех компаниях), где он ещё не задан.
     */
    public function linkUserByPhone(int $userId, string $phone): void;

    public function create(int $companyId, string $name, string $phone, ?int $userId = null): int;
}
