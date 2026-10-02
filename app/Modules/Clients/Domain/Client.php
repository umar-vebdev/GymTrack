<?php

declare(strict_types=1);

namespace App\Modules\Clients\Domain;

final class Client
{
    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly ?int $userId,
        private readonly string $name,
        private readonly string $phone
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function phone(): string
    {
        return $this->phone;
    }
}
