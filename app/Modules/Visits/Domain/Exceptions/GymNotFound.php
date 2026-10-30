<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Exceptions;

use DomainException;

final class GymNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('Зал не найден.');
    }
}

