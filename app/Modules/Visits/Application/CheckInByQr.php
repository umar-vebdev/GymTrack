<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Companies\Contracts\GymQrLookupInterface;
use App\Modules\Memberships\Contracts\MembershipVisitConsumerInterface;
use App\Modules\Visits\Application\Commands\CheckInByQrCommand;
use App\Modules\Visits\Domain\Events\VisitRegistered;
use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use App\Modules\Visits\Domain\QrPayload;
use App\Modules\Visits\Domain\QrTokenServiceInterface;
use App\Modules\Visits\Domain\Rules\CheckInContext;
use App\Modules\Visits\Domain\Rules\CheckInRuleInterface;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use App\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Сценарий отметки посещения клиентом по динамическому QR-коду (Бизнес-ТЗ 5.4; Тех. ТЗ 5.4, 7.3, 9.1).
 */
final class CheckInByQr
{
    /** @var list<CheckInRuleInterface> */
    private readonly array $rules;

    /**
     * @param  iterable<CheckInRuleInterface>  $rules
     */
    public function __construct(
        private readonly GymQrLookupInterface $gyms,
        private readonly QrTokenServiceInterface $qrService,
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
     * @throws InvalidQrToken
     * @throws CheckInDenied
     */
    public function execute(CheckInByQrCommand $command): CheckInResult
    {
        $now = $this->clock->now();
        $parsed = QrPayload::parseTokenString($command->qrToken);

        $gym = $this->gyms->findById($parsed['gymId']);
        if ($gym === null) {
            throw InvalidQrToken::invalid();
        }

        $this->qrService->verifyForGym($command->qrToken, $gym->id, $gym->qrSecret, $now);

        if ($command->idempotencyKey !== null) {
            $existing = $this->visits->findByIdempotencyKey($gym->companyId, $command->idempotencyKey);
            if ($existing !== null) {
                $remaining = $this->memberships
                    ->findCandidate($gym->companyId, $existing->clientId(), $now)
                    ?->remainingVisits;

                return new CheckInResult($existing, $remaining);
            }
        }

        $clientId = $this->clients->findIdByUser($command->userId, $gym->companyId);
        if ($clientId === null) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP);
        }

        $createdNew = false;

        /** @var CheckInResult $result */
        $result = DB::transaction(function () use ($command, $gym, $clientId, $now, &$createdNew): CheckInResult {
            if ($command->idempotencyKey !== null) {
                $existing = $this->visits->findByIdempotencyKey($gym->companyId, $command->idempotencyKey);
                if ($existing !== null) {
                    $remaining = $this->memberships
                        ->findCandidate($gym->companyId, $existing->clientId(), $now)
                        ?->remainingVisits;

                    return new CheckInResult($existing, $remaining);
                }
            }

            $candidate = $this->memberships->findCandidate($gym->companyId, $clientId, $now);
            if ($candidate === null) {
                $completedToday = $this->visits->countCompletedForClientInGymOnDate(
                    $clientId,
                    $gym->id,
                    $now->format('Y-m-d'),
                );

                if ($completedToday > 0) {
                    throw CheckInDenied::forReason(CheckInDenied::REASON_DAILY_LIMIT_EXCEEDED);
                }

                throw CheckInDenied::forReason(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP);
            }

            $context = new CheckInContext(
                companyId: $gym->companyId,
                gymId: $gym->id,
                clientId: $clientId,
                now: $now,
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

            $remainingVisits = $this->memberships->consumeVisit($candidate->membershipId, $now);

            $visit = $this->visits->create(
                companyId: $gym->companyId,
                gymId: $gym->id,
                clientId: $clientId,
                membershipId: $candidate->membershipId,
                method: VisitMethod::Qr,
                visitedAt: $now,
                registeredByUserId: $command->userId,
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
