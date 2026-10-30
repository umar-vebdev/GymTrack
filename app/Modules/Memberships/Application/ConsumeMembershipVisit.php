<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Memberships\Contracts\CheckInMembershipCandidate;
use App\Modules\Memberships\Contracts\MembershipVisitConsumerInterface;
use App\Modules\Memberships\Domain\Exceptions\MembershipNotFound;
use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipStatus;
use DateTimeImmutable;

/**
 * Реализация узкого контракта для модуля Visits: выбор абонемента, списание и возврат занятия.
 * Методы consumeVisit/restoreVisit вызываются внутри транзакции вызывающего Use Case (блокировка строки, ТЗ 9.1).
 */
final class ConsumeMembershipVisit implements MembershipVisitConsumerInterface
{
    public function __construct(
        private readonly MembershipRepositoryInterface $memberships
    ) {}

    public function findCandidate(int $companyId, int $clientId, DateTimeImmutable $now): ?CheckInMembershipCandidate
    {
        $all = $this->memberships->listByClient($companyId, $clientId);

        $usable = array_values(array_filter($all, static fn (Membership $m): bool => $m->isUsableAt($now)));

        if ($usable !== []) {
            // Раньше заканчивается — выше; без срока — в конец; при равенстве — меньший id.
            usort($usable, static fn (Membership $a, Membership $b): int => [
                $a->endsAt()?->getTimestamp() ?? PHP_INT_MAX, $a->id(),
            ] <=> [
                $b->endsAt()?->getTimestamp() ?? PHP_INT_MAX, $b->id(),
            ]);

            return $this->toCandidate($usable[0]);
        }

        // Подходящего нет: если есть замороженный, вернём его, чтобы правило сообщило причину «заморожен».
        foreach ($all as $membership) {
            if ($membership->status() === MembershipStatus::Frozen) {
                return $this->toCandidate($membership);
            }
        }

        return null;
    }

    public function consumeVisit(int $membershipId, DateTimeImmutable $now): void
    {
        $membership = $this->memberships->findByIdForUpdate($membershipId)
            ?? throw MembershipNotFound::withId($membershipId);

        $membership->consumeVisit($now);
        $this->memberships->save($membership);
    }

    public function restoreVisit(int $membershipId, DateTimeImmutable $now): void
    {
        $membership = $this->memberships->findByIdForUpdate($membershipId)
            ?? throw MembershipNotFound::withId($membershipId);

        $membership->restoreVisit($now);
        $this->memberships->save($membership);
    }

    private function toCandidate(Membership $membership): CheckInMembershipCandidate
    {
        return new CheckInMembershipCandidate(
            $membership->id(),
            $membership->remainingVisits(),
            $membership->endsAt(),
            $membership->status() === MembershipStatus::Frozen,
            $membership->rules()->dailyLimit,
            $membership->rules()->timeFromMinute,
            $membership->rules()->timeToMinute,
        );
    }
}
