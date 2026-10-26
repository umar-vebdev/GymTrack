<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

use DateTimeImmutable;

interface MembershipRepositoryInterface
{
    public function findById(int $id): ?Membership;

    /**
     * Загружает абонемент с блокировкой строки (`SELECT ... FOR UPDATE`).
     * Вызывать только внутри транзакции БД: так два параллельных запроса не спишут занятие дважды (ТЗ 9.1).
     */
    public function findByIdForUpdate(int $id): ?Membership;

    /**
     * Все абонементы клиента в зале (все статусы, от новых к старым).
     *
     * @return list<Membership>
     */
    public function listByClient(int $companyId, int $clientId): array;

    /**
     * Абонементы клиента в статусах «активен» и «ожидает старта» (кандидаты на списание).
     *
     * @return list<Membership>
     */
    public function listOpenByClient(int $companyId, int $clientId): array;

    /**
     * Создаёт запись абонемента и возвращает его ID.
     */
    public function create(
        int $companyId,
        int $clientId,
        int $typeId,
        MembershipStatus $status,
        ?int $remainingVisits,
        ?DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt
    ): int;

    /**
     * Сохраняет изменяемое состояние сущности (статус, остаток, сроки, заморозка, причина отмены).
     */
    public function save(Membership $membership): void;
}
