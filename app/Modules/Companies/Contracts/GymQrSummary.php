<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

/**
 * DTO с данными зала и его секретным ключом для генерации и проверки динамического QR-кода.
 */
final class GymQrSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly string $name,
        public readonly string $qrSecret,
    ) {}
}
