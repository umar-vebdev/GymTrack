<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Exceptions;

use DomainException;

final class InvalidQrToken extends DomainException
{
    public const string ERROR_CODE = 'INVALID_QR_TOKEN';

    public const string DEFAULT_MESSAGE = 'Код недействителен, попросите администратора';

    public static function invalid(): self
    {
        return new self(self::DEFAULT_MESSAGE);
    }

    public static function expired(): self
    {
        return new self(self::DEFAULT_MESSAGE);
    }
}

