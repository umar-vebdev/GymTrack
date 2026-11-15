<?php

declare(strict_types=1);

namespace App\Modules\Visits\Infrastructure;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Visits\Contracts\ClientGymVisitCheckInterface;
use App\Modules\Visits\Domain\VisitStatus;
use App\Modules\Visits\Infrastructure\Persistence\VisitModel;

final class EloquentClientGymVisitCheck implements ClientGymVisitCheckInterface
{
    public function __construct(
        private readonly ClientLookupInterface $clients,
    ) {}

    public function hasCompletedVisitInGym(int $userId, int $companyId, int $gymId): bool
    {
        $clientId = $this->clients->findIdByUser($userId, $companyId);
        if ($clientId === null) {
            return false;
        }

        return VisitModel::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('gym_id', $gymId)
            ->where('client_id', $clientId)
            ->where('status', VisitStatus::Completed->value)
            ->exists();
    }
}

