<?php

declare(strict_types=1);

namespace App\Modules\Clients\Contracts;

final readonly class ClientRecipientDto
{
    public function __construct(
        public int $clientId,
        public int $companyId,
        public ?int $userId,
        public string $name,
        public string $phone
    ) {}
}
