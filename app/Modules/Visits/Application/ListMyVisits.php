<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Visits\Contracts\ClientVisitItemDto;
use App\Modules\Visits\Domain\Visit;
use App\Modules\Visits\Domain\VisitRepositoryInterface;

final class ListMyVisits
{
    public function __construct(
        private readonly VisitRepositoryInterface $visits,
        private readonly ClientLookupInterface $clients
    ) {}

    /**
     * @return array{items: list<ClientVisitItemDto>, total: int}
     */
    public function execute(
        int $userId,
        ?int $companyId = null,
        ?string $status = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        $clientIds = $this->clients->findAllClientIdsByUser($userId);
        if ($clientIds === []) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }

        $result = $this->visits->listForClients(
            clientIds: $clientIds,
            companyId: $companyId,
            status: $status,
            page: $page,
            perPage: $perPage
        );

        return [
            'items' => array_map(
                static fn (Visit $v): ClientVisitItemDto => new ClientVisitItemDto(
                    id: $v->id(),
                    companyId: $v->companyId(),
                    gymId: $v->gymId(),
                    clientId: $v->clientId(),
                    membershipId: $v->membershipId(),
                    method: $v->method()->value,
                    status: $v->status()->value,
                    visitedAt: $v->visitedAt(),
                    cancelReason: $v->cancelReason(),
                    cancelledAt: $v->cancelledAt()
                ),
                $result['items']
            ),
            'total' => $result['total'],
        ];
    }
}
