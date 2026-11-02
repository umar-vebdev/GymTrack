<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Memberships\Contracts\ClientMembershipItemDto;
use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;

final class ListMyMemberships
{
    public function __construct(
        private readonly MembershipRepositoryInterface $memberships,
        private readonly MembershipTypeRepositoryInterface $types,
        private readonly ClientLookupInterface $clients
    ) {}

    /**
     * @return list<ClientMembershipItemDto>
     */
    public function execute(int $userId, ?string $statusFilter = null, ?int $companyId = null): array
    {
        $clientIds = $this->clients->findAllClientIdsByUser($userId);
        if ($clientIds === []) {
            return [];
        }

        $items = $this->memberships->listByClientIds($clientIds, $statusFilter, $companyId);
        if ($items === []) {
            return [];
        }

        /** @var array<int, string> $typeNames */
        $typeNames = [];

        return array_map(function (Membership $m) use (&$typeNames): ClientMembershipItemDto {
            if (!isset($typeNames[$m->typeId()])) {
                $type = $this->types->findById($m->typeId());
                $typeNames[$m->typeId()] = $type?->name() ?? 'Абонемент';
            }

            return new ClientMembershipItemDto(
                id: $m->id(),
                companyId: $m->companyId(),
                clientId: $m->clientId(),
                typeId: $m->typeId(),
                typeName: $typeNames[$m->typeId()],
                kind: $m->rules()->kind->value,
                status: $m->status()->value,
                remainingVisits: $m->remainingVisits(),
                startsAt: $m->startsAt(),
                endsAt: $m->endsAt(),
                frozenAt: $m->frozenAt(),
                freezeDaysUsed: $m->freezeDaysUsed(),
                freezeTimesUsed: $m->freezeTimesUsed(),
                isUsable: $m->status()->isUsable()
            );
        }, $items);
    }
}
