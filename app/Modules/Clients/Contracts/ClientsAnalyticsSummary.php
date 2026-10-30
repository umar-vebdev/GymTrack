<?php

declare(strict_types=1);

namespace App\Modules\Clients\Contracts;

/**
 * DTO со сводкой по клиентской базе компании за выбранный период (новые и постоянные клиенты).
 */
final class ClientsAnalyticsSummary
{
    public function __construct(
        public readonly int $totalClients,
        public readonly int $newClients,
        public readonly int $existingClients,
    ) {}
}
