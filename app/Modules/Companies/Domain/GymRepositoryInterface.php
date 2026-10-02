<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

interface GymRepositoryInterface
{
    public function findById(int $id): ?Gym;

    public function create(int $companyId, string $name, ?string $address): int;
}
