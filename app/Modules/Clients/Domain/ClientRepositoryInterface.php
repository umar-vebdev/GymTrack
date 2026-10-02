<?php

declare(strict_types=1);

namespace App\Modules\Clients\Domain;

interface ClientRepositoryInterface
{
    public function findById(int $id): ?Client;

    public function create(int $companyId, string $name, string $phone, ?int $userId = null): int;
}
