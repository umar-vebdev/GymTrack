<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Domain\MembershipRequest;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use App\Modules\Sales\Domain\MembershipRequestStatus;
use DateTimeImmutable;

final class ListCompanyMembershipRequests
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
     *     sale_id: int|null,
     *     membership_id: int|null,
     *     created_at: string|null
     * }>
     */
    public function execute(int $companyId, ?MembershipRequestStatus $status = null): array
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
                'sale_id' => $r->saleId(),
                'membership_id' => $r->membershipId(),
                'created_at' => $r->createdAt()?->format(DateTimeImmutable::ATOM),
            ],
            $this->requests->listByCompany($companyId, $status)
        );
    }
}
