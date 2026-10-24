<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Exceptions;

use DomainException;

final class MembershipFreezeNotAllowed extends DomainException
{
    public static function notActive(string $currentStatus): self
    {
        return new self(sprintf('Заморозить можно только активный абонемент (текущий статус: %s).', $currentStatus));
    }

    public static function timesLimitReached(): self
    {
        return new self('Лимит количества заморозок исчерпан.');
    }

    public static function daysLimitReached(): self
    {
        return new self('Лимит дней заморозки исчерпан.');
    }

    public static function notFrozen(): self
    {
        return new self('Абонемент не находится в состоянии заморозки.');
    }

    public static function unfreezeBeforeFreeze(): self
    {
        return new self('Дата разморозки не может быть раньше даты заморозки.');
    }
}

