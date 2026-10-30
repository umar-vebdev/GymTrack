<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

use App\Modules\Memberships\Domain\Exceptions\MembershipAlreadyCancelled;
use App\Modules\Memberships\Domain\Exceptions\MembershipFreezeNotAllowed;
use App\Modules\Memberships\Domain\Exceptions\MembershipNotUsable;
use App\Modules\Memberships\Domain\Exceptions\NoVisitsLeft;
use App\Modules\Memberships\Domain\Strategies\ConsumptionStrategy;
use App\Modules\Memberships\Domain\Strategies\ConsumptionStrategyResolver;
use DateTimeImmutable;
use DomainException;

/**
 * Богатая доменная сущность абонемента клиента (Rich Domain Model).
 * Инкапсулирует бизнес-правила жизненного цикла, списания и заморозки.
 */
final class Membership
{
    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly int $clientId,
        private readonly int $typeId,
        private readonly MembershipRules $rules,
        private MembershipStatus $status,
        private ?int $remainingVisits,
        private ?DateTimeImmutable $startsAt,
        private ?DateTimeImmutable $endsAt,
        private int $freezeDaysUsed = 0,
        private int $freezeTimesUsed = 0,
        private ?DateTimeImmutable $frozenAt = null,
        private ?string $cancellationReason = null
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function clientId(): int
    {
        return $this->clientId;
    }

    public function typeId(): int
    {
        return $this->typeId;
    }

    public function rules(): MembershipRules
    {
        return $this->rules;
    }

    public function status(): MembershipStatus
    {
        return $this->status;
    }

    public function remainingVisits(): ?int
    {
        return $this->remainingVisits;
    }

    public function startsAt(): ?DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): ?DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function freezeDaysUsed(): int
    {
        return $this->freezeDaysUsed;
    }

    public function freezeTimesUsed(): int
    {
        return $this->freezeTimesUsed;
    }

    public function frozenAt(): ?DateTimeImmutable
    {
        return $this->frozenAt;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    /**
     * Списание посещения или фиксация визита с помощью стратегии списания.
     *
     * @throws MembershipNotUsable
     * @throws NoVisitsLeft
     */
    public function consumeVisit(DateTimeImmutable $at, ?ConsumptionStrategy $strategy = null): void
    {
        $this->expireIfDue($at);

        if (!$this->status->isUsable()) {
            throw MembershipNotUsable::forStatus($this->id, $this->status->value);
        }

        // Активация абонемента при первом визите, если был в ожидании
        if ($this->status === MembershipStatus::Pending) {
            $this->activateOnFirstVisit($at);
        }

        $strategy ??= ConsumptionStrategyResolver::resolve($this->rules->kind);
        $strategy->consume($this, $at);
    }

    /**
     * Можно ли использовать абонемент в момент $at: подходящий статус, срок не истёк, остались занятия.
     */
    public function isUsableAt(DateTimeImmutable $at): bool
    {
        if (!$this->status->isUsable()) {
            return false;
        }

        if ($this->endsAt !== null && $at > $this->endsAt) {
            return false;
        }

        return $this->rules->kind !== MembershipKind::Visits || ($this->remainingVisits ?? 0) > 0;
    }

    public function decrementRemainingVisits(): void
    {
        if ($this->remainingVisits === null || $this->remainingVisits <= 0) {
            throw NoVisitsLeft::forMembership($this->id);
        }

        $this->remainingVisits--;

        if ($this->remainingVisits === 0) {
            $this->status = MembershipStatus::Expired;
        }
    }

    /**
     * Возврат занятия при отмене ошибочной отметки посещения (Бизнес-ТЗ 5.4).
     * Для абонементов не по занятиям остаток не меняется. Если абонемент стал Expired только
     * из-за обнуления остатка и срок ещё не вышел, он снова становится Active.
     */
    public function restoreVisit(DateTimeImmutable $at): void
    {
        if ($this->rules->kind !== MembershipKind::Visits || $this->remainingVisits === null) {
            return;
        }

        $this->remainingVisits++;

        if ($this->status === MembershipStatus::Expired && ($this->endsAt === null || $at <= $this->endsAt)) {
            $this->status = MembershipStatus::Active;
        }
    }

    /**
     * Проверка срока действия и автоматический перевод в статус Expired при наступлении даты окончания.
     * Замороженные (Frozen) и уже отменённые (Cancelled) абонементы не истекают автоматически.
     */
    public function expireIfDue(DateTimeImmutable $now): void
    {
        if ($this->status !== MembershipStatus::Active && $this->status !== MembershipStatus::Pending) {
            return;
        }

        if ($this->endsAt !== null && $now > $this->endsAt) {
            $this->status = MembershipStatus::Expired;
        }
    }

    /**
     * Заморозка абонемента.
     *
     * @throws MembershipFreezeNotAllowed
     */
    public function freeze(DateTimeImmutable $at): void
    {
        $this->expireIfDue($at);

        if ($this->status !== MembershipStatus::Active) {
            throw MembershipFreezeNotAllowed::notActive($this->status->value);
        }

        if ($this->rules->freezeMaxTimes <= 0 || $this->freezeTimesUsed >= $this->rules->freezeMaxTimes) {
            throw MembershipFreezeNotAllowed::timesLimitReached();
        }

        if ($this->freezeDaysUsed >= $this->rules->freezeMaxDays) {
            throw MembershipFreezeNotAllowed::daysLimitReached();
        }

        $this->status = MembershipStatus::Frozen;
        $this->frozenAt = $at;
        $this->freezeTimesUsed++;
    }

    /**
     * Разморозка абонемента с продлением даты окончания на срок фактически использованной заморозки.
     *
     * @throws MembershipFreezeNotAllowed
     */
    public function unfreeze(DateTimeImmutable $now): void
    {
        if ($this->status !== MembershipStatus::Frozen || $this->frozenAt === null) {
            throw MembershipFreezeNotAllowed::notFrozen();
        }

        if ($now < $this->frozenAt) {
            throw MembershipFreezeNotAllowed::unfreezeBeforeFreeze();
        }

        $seconds = $now->getTimestamp() - $this->frozenAt->getTimestamp();
        $daysFrozen = max(1, (int) ceil($seconds / 86400));

        // Контроль общего лимита дней заморозки
        $remainingAllowedDays = $this->rules->freezeMaxDays - $this->freezeDaysUsed;
        $effectiveDays = min($daysFrozen, $remainingAllowedDays);

        $this->freezeDaysUsed += $effectiveDays;

        if ($this->endsAt !== null) {
            $this->endsAt = $this->endsAt->modify(sprintf('+%d days', $effectiveDays));
        }

        $this->status = MembershipStatus::Active;
        $this->frozenAt = null;
    }

    /**
     * Аннулирование абонемента владельцем (с обязательной фиксацией причины).
     *
     * @throws MembershipAlreadyCancelled
     */
    public function cancel(string $reason): void
    {
        if ($this->status === MembershipStatus::Cancelled) {
            throw MembershipAlreadyCancelled::withId($this->id);
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new DomainException('Причина отмены обязательна.');
        }

        $this->status = MembershipStatus::Cancelled;
        $this->cancellationReason = $trimmedReason;
    }

    private function activateOnFirstVisit(DateTimeImmutable $at): void
    {
        $this->status = MembershipStatus::Active;
        $this->startsAt = $at;

        $this->endsAt = $this->rules->endsAtFor($at);
    }
}
