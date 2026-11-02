<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

interface SaleRepositoryInterface
{
    public function findById(int $id): ?Sale;

    /**
     * Загружает продажу с блокировкой строки (`SELECT ... FOR UPDATE`) внутри транзакции БД.
     */
    public function findByIdForUpdate(int $id): ?Sale;

    public function findByIdempotencyKey(int $companyId, string $idempotencyKey): ?Sale;

    /**
     * @param  list<SaleItem>  $items
     */
    public function create(
        int $companyId,
        ?int $clientId,
        PaymentMark $paymentMark,
        array $items,
        ?string $idempotencyKey = null
    ): Sale;

    /**
     * @return list<Sale>
     */
    public function listByCompany(int $companyId): array;

    /**
     * @return list<Sale>
     */
    public function listByClient(int $companyId, int $clientId): array;

    /**
     * @param  list<int>  $clientIds
     * @return array{items: list<Sale>, total: int}
     */
    public function listForClients(
        array $clientIds,
        ?int $companyId = null,
        ?string $status = null,
        int $page = 1,
        int $perPage = 20
    ): array;

    public function save(Sale $sale): void;
}
