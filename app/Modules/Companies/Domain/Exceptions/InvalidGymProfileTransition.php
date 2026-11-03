<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain\Exceptions;

use App\Modules\Companies\Domain\ModerationStatus;
use DomainException;

final class InvalidGymProfileTransition extends DomainException
{
    public static function from(ModerationStatus $status, string $action): self
    {
        return new self(sprintf('Действие «%s» недоступно для витрины в статусе «%s».', $action, $status->value));
    }

    public static function incomplete(): self
    {
        return new self('Для отправки на модерацию заполните описание, адрес и телефон витрины.');
    }

    public static function reasonRequired(): self
    {
        return new self('Укажите причину отклонения витрины.');
    }
}
