<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application;

use App\Modules\Clients\Domain\ClientPhoneAlreadyExists;
use App\Modules\Clients\Domain\ClientRepositoryInterface;

final class CreateClient
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients
    ) {}

    /**
     * @throws ClientPhoneAlreadyExists
     */
    public function execute(int $companyId, string $name, string $phone): int
    {
        if ($this->clients->existsByPhone($companyId, $phone)) {
            throw ClientPhoneAlreadyExists::forPhone($phone);
        }

        return $this->clients->create($companyId, $name, $phone);
    }
}
