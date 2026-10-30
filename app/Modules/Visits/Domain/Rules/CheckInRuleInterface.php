<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

/**
 * Интерфейс правила проверки чек‑ина.
 *
 * Метод `validate` бросает `CheckInDenied` при несоответствии.
 */
interface CheckInRuleInterface
{
    public function validate(CheckInContext $ctx): void;
}
