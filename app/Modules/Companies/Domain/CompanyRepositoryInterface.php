<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

interface CompanyRepositoryInterface
{
    public function findById(int $id): ?Company;

    public function create(int $ownerId, string $name): int;
}
