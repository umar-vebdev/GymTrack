<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

/**
 * Value Object (неизменяемый объект-значение) с правилами типа абонемента.
 * Все инварианты проверяются при создании: невалидное состояние невозможно (fail fast).
 */
final class MembershipRules
{
    private const MINUTES_IN_DAY = 1440;

    private function __construct(
        public readonly MembershipKind $kind,
        public readonly StartMode $startMode,
        public readonly ?int $visitsCount,
        public readonly ?int $durationDays,
        public readonly ?int $validityDays,
        public readonly int $dailyLimit,
        public readonly ?int $timeFromMinute,
        public readonly ?int $timeToMinute,
        public readonly int $freezeMaxDays,
        public readonly int $freezeMaxTimes
    ) {}

    /**
     * @param  int|null  $visitsCount  число занятий (только для пакета посещений)
     * @param  int|null  $durationDays  длительность в днях (для «по сроку» и безлимита)
     * @param  int|null  $validityDays  срок действия пакета посещений (необязателен)
     * @param  int|null  $timeFromMinute  начало окна времени суток, минуты от полуночи
     * @param  int|null  $timeToMinute  конец окна времени суток, минуты от полуночи
     */
    public static function create(
        MembershipKind $kind,
        StartMode $startMode,
        ?int $visitsCount = null,
        ?int $durationDays = null,
        ?int $validityDays = null,
        int $dailyLimit = 1,
        ?int $timeFromMinute = null,
        ?int $timeToMinute = null,
        int $freezeMaxDays = 0,
        int $freezeMaxTimes = 0
    ): self {
        self::assertKindFields($kind, $startMode, $visitsCount, $durationDays, $validityDays);
        self::assertTimeWindow($timeFromMinute, $timeToMinute);
        self::assertFreeze($kind, $freezeMaxDays, $freezeMaxTimes);

        if ($dailyLimit < 1) {
            throw new InvalidMembershipRules('Лимит входов в день должен быть не меньше 1.');
        }

        return new self(
            $kind, $startMode, $visitsCount, $durationDays, $validityDays,
            $dailyLimit, $timeFromMinute, $timeToMinute, $freezeMaxDays, $freezeMaxTimes
        );
    }

    private static function assertKindFields(
        MembershipKind $kind,
        StartMode $startMode,
        ?int $visitsCount,
        ?int $durationDays,
        ?int $validityDays
    ): void {
        match ($kind) {
            MembershipKind::Visits => self::assertVisits($visitsCount, $durationDays, $validityDays),
            MembershipKind::Duration, MembershipKind::Unlimited => self::assertDuration($visitsCount, $durationDays, $validityDays),
            MembershipKind::Single => self::assertSingle($startMode, $visitsCount, $durationDays, $validityDays),
        };
    }

    private static function assertVisits(?int $visitsCount, ?int $durationDays, ?int $validityDays): void
    {
        if ($visitsCount === null || $visitsCount < 1) {
            throw new InvalidMembershipRules('Для пакета посещений нужно количество занятий не меньше 1.');
        }
        if ($durationDays !== null) {
            throw new InvalidMembershipRules('Для пакета посещений используется срок действия пакета, а не длительность.');
        }
        if ($validityDays !== null && $validityDays < 1) {
            throw new InvalidMembershipRules('Срок действия пакета должен быть не меньше 1 дня.');
        }
    }

    private static function assertDuration(?int $visitsCount, ?int $durationDays, ?int $validityDays): void
    {
        if ($durationDays === null || $durationDays < 1) {
            throw new InvalidMembershipRules('Для абонемента по сроку нужна длительность не меньше 1 дня.');
        }
        if ($visitsCount !== null || $validityDays !== null) {
            throw new InvalidMembershipRules('Количество занятий и срок пакета не применимы к абонементу по сроку.');
        }
    }

    private static function assertSingle(StartMode $startMode, ?int $visitsCount, ?int $durationDays, ?int $validityDays): void
    {
        if ($visitsCount !== null || $durationDays !== null || $validityDays !== null) {
            throw new InvalidMembershipRules('Для разового посещения нельзя задавать занятия и сроки.');
        }
        if ($startMode !== StartMode::OnSale) {
            throw new InvalidMembershipRules('Разовое посещение действует в день выдачи (старт с даты продажи).');
        }
    }

    private static function assertTimeWindow(?int $from, ?int $to): void
    {
        if ($from === null && $to === null) {
            return;
        }
        if ($from === null || $to === null) {
            throw new InvalidMembershipRules('Окно времени суток задаётся обеими границами.');
        }
        if ($from < 0 || $to > self::MINUTES_IN_DAY || $from >= $to) {
            throw new InvalidMembershipRules('Окно времени суток некорректно: начало должно быть раньше конца в пределах суток.');
        }
    }

    private static function assertFreeze(MembershipKind $kind, int $maxDays, int $maxTimes): void
    {
        if ($maxDays < 0 || $maxTimes < 0) {
            throw new InvalidMembershipRules('Лимиты заморозки не могут быть отрицательными.');
        }
        if (($maxDays === 0) !== ($maxTimes === 0)) {
            throw new InvalidMembershipRules('Максимум дней и число раз заморозки задаются вместе (или оба равны 0).');
        }
        if ($kind === MembershipKind::Single && $maxDays > 0) {
            throw new InvalidMembershipRules('Разовое посещение нельзя замораживать.');
        }
    }
}
