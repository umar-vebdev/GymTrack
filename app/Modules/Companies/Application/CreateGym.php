<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\Gym;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use InvalidArgumentException;

/**
 * Создание нового филиала (зала) компании (Бизнес-ТЗ 3, 4.1, 11).
 */
final class CreateGym
{
    public function __construct(
        private readonly GymRepositoryInterface $gyms,
    ) {}

    public function execute(
        int $companyId,
        string $name,
        ?string $address = null,
        string $timezone = Gym::DEFAULT_TIMEZONE,
    ): Gym {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('Название зала не может быть пустым.');
        }

        if (!Gym::isValidTimezone($timezone)) {
            throw new InvalidArgumentException("Некорректный часовой пояс зала: {$timezone}");
        }

        $normalizedAddress = $address !== null ? (trim($address) !== '' ? trim($address) : null) : null;

        $gymId = $this->gyms->create(
            companyId: $companyId,
            name: $trimmedName,
            address: $normalizedAddress,
            timezone: $timezone,
        );

        return new Gym(
            id: $gymId,
            companyId: $companyId,
            name: $trimmedName,
            address: $normalizedAddress,
            timezone: $timezone,
        );
    }
}
