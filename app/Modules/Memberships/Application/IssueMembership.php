<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Memberships\Contracts\MembershipTypeQuote;
use App\Modules\Memberships\Domain\Events\MembershipIssued;
use App\Modules\Memberships\Domain\Exceptions\ClientNotAvailable;
use App\Modules\Memberships\Domain\Exceptions\MembershipTypeInactive;
use App\Modules\Memberships\Domain\MembershipFactory;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipTypeNotFound;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;

final class IssueMembership implements MembershipIssuerInterface
{
    public function __construct(
        private readonly MembershipTypeRepositoryInterface $types,
        private readonly MembershipRepositoryInterface $memberships,
        private readonly ClientLookupInterface $clients
    ) {}

    /**
     * @throws MembershipTypeNotFound
     * @throws MembershipTypeInactive
     */
    public function quoteType(int $companyId, int $typeId): MembershipTypeQuote
    {
        $type = $this->types->findById($typeId);

        if ($type === null || $type->companyId() !== $companyId) {
            throw MembershipTypeNotFound::withId($typeId);
        }

        if (!$type->isActive()) {
            throw MembershipTypeInactive::withId($typeId);
        }

        return new MembershipTypeQuote(
            typeId: $type->id(),
            name: $type->name(),
            price: $type->price()
        );
    }

    /**
     * @throws MembershipTypeNotFound
     * @throws MembershipTypeInactive
     * @throws ClientNotAvailable
     */
    public function issue(
        int $companyId,
        int $clientId,
        int $typeId,
        DateTimeImmutable $at,
        ?int $issuedByUserId = null
    ): int {
        $type = $this->types->findById($typeId);

        if ($type === null || $type->companyId() !== $companyId) {
            throw MembershipTypeNotFound::withId($typeId);
        }

        if (!$type->isActive()) {
            throw MembershipTypeInactive::withId($typeId);
        }

        if (!$this->clients->existsInCompany($clientId, $companyId)) {
            throw ClientNotAvailable::withId($clientId);
        }

        $terms = MembershipFactory::termsFor($type, $at);

        $id = $this->memberships->create(
            $companyId,
            $clientId,
            $typeId,
            $terms->status,
            $terms->remainingVisits,
            $terms->startsAt,
            $terms->endsAt
        );

        Event::dispatch(new MembershipIssued($id, $companyId, $clientId, $typeId, $issuedByUserId));

        return $id;
    }
}
