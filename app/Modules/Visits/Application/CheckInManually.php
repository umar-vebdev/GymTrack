<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Companies\Contracts\GymQrLookupInterface;
use App\Modules\Memberships\Contracts\MembershipVisitConsumerInterface;
use App\Modules\Visits\Application\Commands\CheckInManuallyCommand;
use App\Modules\Visits\Domain\Events\VisitRegistered;
use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Exceptions\GymNotFound;
use App\Modules\Visits\Domain\Rules\CheckInContext;
use App\Modules\Visits\Domain\Rules\CheckInRuleInterface;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use App\Shared\Domain\Clock;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Ручная отметка посещения сотрудником зала, в том числе задним числом (Бизнес-ТЗ 4.4, 6.3; Тех. ТЗ 8).
 */
final class CheckInManually
{
    /** @var list<CheckInRuleInterface> */
    private readonly array $rules;

    /**
     * @param  iterable<CheckInRuleInterface>  $rules
     */
    public function __construct(
        private readonly GymQrLookupInterface $gyms,
        private readonly ClientLookupInterface $clients,
        private readonly MembershipVisitConsumerInterface $memberships,
        private readonly VisitRepositoryInterface $visits,
        private readonly Clock $clock,
        iterable $rules,
    ) {
        $list = [];
        foreach ($rules as $rule) {
            $list[] = $rule;
        }
        $this->rules = $list;
    }

    /**
     * @throws GymNotFound
     * @throws CheckInDenied
     * @throws DomainException
     */
    public function execute(CheckInManuallyCommand $command): CheckInResult
    {
        $gym = $this->gyms->findById($command->gymId);
        if ($gym === null || $gym->companyId !== $command->companyId) {
            throw new GymNotFound;
        }

        if (!$this->clients->existsInCompany($command->clientId, $command->companyId)) {
            throw new DomainException('Клиент не найден в компании.');
        }

        $now = $this->clock->now();
        $visitedAt = $command->visitedAt ?? $now;

        if ($visitedAt > $now) {
            throw new DomainException('Время визита не может быть в будущем.');
        }

        if ($command->idempotencyKey !== null) {
            $existing = $this->visits->findByIdempotencyKey($command->companyId, $command->idempotencyKey);
            if ($existing !== null) {
                $remaining = $this->memberships
                    ->findCandidate($command->companyId, $existing->clientId(), $visitedAt)
                    ?->remainingVisits;

                return new CheckInResult($existing, $remaining);
            }
        }

        $createdNew = false;

        /** @var CheckInResult $result */
        $result = DB::transaction(function () use ($command, $gym, $visitedAt, &$createdNew): CheckInResult {
            if ($command->idempotencyKey !== null) {
                $existing = $this->visits->findByIdempotencyKey($command->companyId, $command->idempotencyKey);
                if ($existing !== null) {
                    $remaining = $this->memberships
                        ->findCandidate($command->companyId, $existing->clientId(), $visitedAt)
                        ?->remainingVisits;

                    return new CheckInResult($existing, $remaining);
                }
            }

            $candidate = $this->memberships->findCandidate($command->companyId, $command->clientId, $visitedAt);
            if ($candidate === null) {
                $completedOnDate = $this->visits->countCompletedForClientInGymOnDate(
                    $command->clientId,
                    $gym->id,
                    $visitedAt->format('Y-m-d'),
                );

                if ($completedOnDate > 0) {
                    throw CheckInDenied::forReason(CheckInDenied::REASON_DAILY_LIMIT_EXCEEDED);
                }

                throw CheckInDenied::forReason(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP);
            }

            $context = new CheckInContext(
                companyId: $command->companyId,
                gymId: $gym->id,
                clientId: $command->clientId,
                now: $visitedAt,
                remainingVisits: $candidate->remainingVisits,
                membershipExpiresAt: $candidate->expiresAt,
                membershipFrozen: $candidate->isFrozen,
                dailyLimit: $candidate->dailyLimit,
                timeFromMinute: $candidate->timeFromMinute,
                timeToMinute: $candidate->timeToMinute,
            );

            foreach ($this->rules as $rule) {
                $rule->validate($context);
            }

            $remainingVisits = $this->memberships->consumeVisit($candidate->membershipId, $visitedAt);

            $visit = $this->visits->create(
                companyId: $command->companyId,
                gymId: $gym->id,
                clientId: $command->clientId,
                membershipId: $candidate->membershipId,
                method: VisitMethod::Manual,
                visitedAt: $visitedAt,
                registeredByUserId: $command->staffUserId,
                device: $command->device,
                idempotencyKey: $command->idempotencyKey,
            );

            $createdNew = true;

            return new CheckInResult($visit, $remainingVisits);
        });

        if ($createdNew) {
            $visit = $result->visit;
            Event::dispatch(new VisitRegistered(
                visitId: $visit->id(),
                companyId: $visit->companyId(),
                gymId: $visit->gymId(),
                clientId: $visit->clientId(),
                membershipId: $visit->membershipId(),
                method: $visit->method(),
                visitedAt: $visit->visitedAt(),
                registeredByUserId: $visit->registeredByUserId(),
                device: $visit->device(),
            ));
        }

        return $result;
    }
}
