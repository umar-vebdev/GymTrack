<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Domain\MembershipRequest;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use DateTimeImmutable;

final class ListUserMembershipRequests
{
    public function __construct(
        private readonly MembershipRequestRepositoryInterface $requests
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     company_id: int,
     *     gym_id: int,
     *     user_id: int,
     *     client_id: int|null,
     *     membership_type_id: int,
     *     status: string,
     *     expires_at: string,
     *     paid_at: string|null,
     *     created_at: string|null
     * }>
     */
    public function execute(int $userId): array
    {
        return array_map(
            fn (MembershipRequest $r) => [
                'id' => $r->id(),
                'company_id' => $r->companyId(),
                'gym_id' => $r->gymId(),
                'user_id' => $r->userId(),
                'client_id' => $r->clientId(),
                'membership_type_id' => $r->membershipTypeId(),
                'status' => $r->status()->value,
                'expires_at' => $r->expiresAt()->format(DateTimeImmutable::ATOM),
                'paid_at' => $r->paidAt()?->format(DateTimeImmutable::ATOM),
                'created_at' => $r->createdAt()?->format(DateTimeImmutable::ATOM),
            ],
            $this->requests->listByUser($userId)
        );
    }
}

