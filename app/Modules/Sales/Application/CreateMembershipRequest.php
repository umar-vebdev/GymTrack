<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Companies\Contracts\GymQrLookupInterface;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Sales\Domain\Events\MembershipRequestCreated;
use App\Modules\Sales\Domain\MembershipRequest;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\Event;

final class CreateMembershipRequest
{
    public function __construct(
        private readonly MembershipRequestRepositoryInterface $requests,
        private readonly GymQrLookupInterface $gyms,
        private readonly MembershipIssuerInterface $membershipIssuer,
        private readonly ClientLookupInterface $clients,
        private readonly Clock $clock
    ) {}

    /**
     * @return array{
     *     id: int,
     *     company_id: int,
     *     gym_id: int,
     *     user_id: int,
     *     client_id: int|null,
     *     membership_type_id: int,
     *     status: string,
     *     expires_at: string,
     *     created_at: string|null
     * }
     */
    public function execute(
        int $userId,
        int $gymId,
        int $membershipTypeId,
        ?string $idempotencyKey = null
    ): array {
        if ($idempotencyKey !== null) {
            $existing = $this->requests->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                return $this->formatResponse($existing);
            }
        }

        $gym = $this->gyms->findById($gymId);
        if ($gym === null) {
            throw new DomainException('Зал не найден.');
        }

        // Проверяем принадлежность типа абонемента залу и его активность
        $this->membershipIssuer->quoteType($gym->companyId, $membershipTypeId);

        $clientId = $this->clients->findOrCreateForUser($userId, $gym->companyId);

        $now = $this->clock->now();
        $expiresAt = $now->modify('+24 hours');

        $request = $this->requests->create(
            companyId: $gym->companyId,
            gymId: $gymId,
            userId: $userId,
            clientId: $clientId,
            membershipTypeId: $membershipTypeId,
            expiresAt: $expiresAt,
            idempotencyKey: $idempotencyKey
        );

        Event::dispatch(new MembershipRequestCreated(
            requestId: $request->id(),
            companyId: $gym->companyId,
            gymId: $gymId,
            userId: $userId,
            clientId: $clientId,
            membershipTypeId: $membershipTypeId,
            expiresAt: $expiresAt
        ));

        return $this->formatResponse($request);
    }

    /**
     * @return array{
     *     id: int,
     *     company_id: int,
     *     gym_id: int,
     *     user_id: int,
     *     client_id: int|null,
     *     membership_type_id: int,
     *     status: string,
     *     expires_at: string,
     *     created_at: string|null
     * }
     */
    private function formatResponse(MembershipRequest $r): array
    {
        return [
            'id' => $r->id(),
            'company_id' => $r->companyId(),
            'gym_id' => $r->gymId(),
            'user_id' => $r->userId(),
            'client_id' => $r->clientId(),
            'membership_type_id' => $r->membershipTypeId(),
            'status' => $r->status()->value,
            'expires_at' => $r->expiresAt()->format(DateTimeImmutable::ATOM),
            'created_at' => $r->createdAt()?->format(DateTimeImmutable::ATOM),
        ];
    }
}
