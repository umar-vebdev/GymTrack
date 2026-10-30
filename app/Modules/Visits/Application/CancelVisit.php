<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application;

use App\Modules\Memberships\Contracts\MembershipVisitConsumerInterface;
use App\Modules\Visits\Domain\Events\VisitCancelled;
use App\Modules\Visits\Domain\Visit;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use App\Shared\Domain\Clock;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Отмена ошибочной отметки посещения сотрудником зала в течение 24 часов
 * с возвратом занятия абонементу и записью в аудит-лог (Бизнес-ТЗ 5.4; Тех. ТЗ 8).
 */
final class CancelVisit
{
    public function __construct(
        private readonly VisitRepositoryInterface $visits,
        private readonly MembershipVisitConsumerInterface $memberships,
        private readonly Clock $clock,
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(int $companyId, int $visitId, int $cancelledByUserId, string $reason): Visit
    {
        $now = $this->clock->now();

        /** @var Visit $visit */
        $visit = DB::transaction(function () use ($companyId, $visitId, $cancelledByUserId, $reason, $now): Visit {
            $visit = $this->visits->findById($visitId);
            if ($visit === null || $visit->companyId() !== $companyId) {
                throw new DomainException('Отметка посещения не найдена.');
            }

            $visit->cancel($reason, $cancelledByUserId, $now);
            $this->visits->save($visit);

            $this->memberships->restoreVisit($visit->membershipId(), $now);

            return $visit;
        });

        Event::dispatch(new VisitCancelled(
            visitId: $visit->id(),
            companyId: $visit->companyId(),
            gymId: $visit->gymId(),
            clientId: $visit->clientId(),
            membershipId: $visit->membershipId(),
            reason: (string) $visit->cancelReason(),
            cancelledByUserId: $cancelledByUserId,
            cancelledAt: $now,
        ));

        return $visit;
    }
}
