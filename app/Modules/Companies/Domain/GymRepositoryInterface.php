<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

interface GymRepositoryInterface
{
    public function findById(int $id): ?Gym;

    public function findByIdForUpdate(int $id): ?Gym;

    /**
     * @return list<Gym>
     */
    public function listByCompany(int $companyId): array;

    public function create(int $companyId, string $name, ?string $address, string $timezone = Gym::DEFAULT_TIMEZONE): int;

    public function save(Gym $gym): void;
}
