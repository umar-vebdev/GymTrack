<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application;

use App\Modules\Clients\Domain\ClientPhoneAlreadyExists;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Identity\Contracts\UserQueryInterface;

final class CreateClient
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly UserQueryInterface $users
    ) {}

    /**
     * @throws ClientPhoneAlreadyExists
     */
    public function execute(int $companyId, string $name, string $phone, ?string $email = null): int
    {
        if ($this->clients->existsByPhone($companyId, $phone)) {
            throw ClientPhoneAlreadyExists::forPhone($phone);
        }

        $normalizedEmail = $email !== null && trim($email) !== '' ? mb_strtolower(trim($email)) : null;
        $userId = $normalizedEmail !== null
            ? $this->users->findVerifiedUserIdByEmail($normalizedEmail)
            : null;

        return $this->clients->create(
            companyId: $companyId,
            name: $name,
            phone: $phone,
            userId: $userId,
            email: $normalizedEmail,
        );
    }
}
