<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Companies\Contracts\GymQrLookupInterface;
use App\Modules\Companies\Contracts\GymQrSummary;
use App\Modules\Memberships\Contracts\MembershipVisitConsumerInterface;
use App\Modules\Visits\Application\Commands\CheckInByClientQrCommand;
use App\Modules\Visits\Domain\Events\VisitRegistered;
use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Exceptions\GymNotFound;
use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use App\Modules\Visits\Domain\QrTokenServiceInterface;
use App\Modules\Visits\Domain\Rules\CheckInContext;
use App\Modules\Visits\Domain\Rules\CheckInRuleInterface;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Сценарий отметки посещения путём сканирования личного динамического QR-кода клиента сотрудником зала (Бизнес-ТЗ 6.3, 11).
 */
final class CheckInByClientQr
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
     * @throws GymNotFound
     * @throws InvalidQrToken
     * @throws CheckInDenied
     */
    public function execute(CheckInByClientQrCommand $command): CheckInResult
    {
        $now = $this->clock->now();
        $gym = $this->resolveGym($command->companyId, $command->gymId);
        $clientUserId = $this->qrService->verifyClientToken($command->qrToken, $now);

        $idempotent = $this->findIdempotent($gym->companyId, $command->idempotencyKey, $now);
        if ($idempotent !== null) {
            return $idempotent;
        }

        $clientId = $this->clients->findIdByUser($clientUserId, $gym->companyId);
        if ($clientId === null) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP);
        }

        /** @var array{result: CheckInResult, created: bool} $outcome */
        $outcome = DB::transaction(
            fn (): array => $this->runTransaction($command, $gym, $clientId, $now)
        );

        if ($outcome['created']) {
            $this->dispatchVisitRegistered($outcome['result']);
        }

        return $outcome['result'];
    }

    private function resolveGym(int $companyId, int $gymId): GymQrSummary
    {
        $gym = $this->gyms->findById($gymId);
        if ($gym === null || $gym->companyId !== $companyId) {
            throw new GymNotFound;
        }

        return $gym;
    }

    private function findIdempotent(int $companyId, ?string $idempotencyKey, DateTimeImmutable $now): ?CheckInResult
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $existing = $this->visits->findByIdempotencyKey($companyId, $idempotencyKey);
        if ($existing === null) {
            return null;
        }

        $remaining = $this->memberships
            ->findCandidate($companyId, $existing->clientId(), $now)
            ?->remainingVisits;

        return new CheckInResult($existing, $remaining);
    }

    /**
     * @return array{result: CheckInResult, created: bool}
     */
    private function runTransaction(
        CheckInByClientQrCommand $command,
        GymQrSummary $gym,
        int $clientId,
        DateTimeImmutable $now,
    ): array {
        $idempotent = $this->findIdempotent($gym->companyId, $command->idempotencyKey, $now);
        if ($idempotent !== null) {
            return ['result' => $idempotent, 'created' => false];
        }

        $candidate = $this->memberships->findCandidate($gym->companyId, $clientId, $now);
        if ($candidate === null) {
            $localDate = $now->setTimezone(new DateTimeZone($gym->timezone))->format('Y-m-d');
            $completedToday = $this->visits->countCompletedForClientInGymOnDate($clientId, $gym->id, $localDate);

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
            gymTimezone: $gym->timezone,
            geoCheckEnabled: false,
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
            method: VisitMethod::ClientQr,
            visitedAt: $now,
            registeredByUserId: $command->staffUserId,
            device: $command->device,
            idempotencyKey: $command->idempotencyKey,
            gymTimezone: $gym->timezone,
        );

        return ['result' => new CheckInResult($visit, $remainingVisits), 'created' => true];
    }

    private function dispatchVisitRegistered(CheckInResult $result): void
    {
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
}
