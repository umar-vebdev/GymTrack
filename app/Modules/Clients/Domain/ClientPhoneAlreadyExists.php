<?php

declare(strict_types=1);

namespace App\Modules\Clients\Domain;

use DomainException;

final class ClientPhoneAlreadyExists extends DomainException
{
    public static function forPhone(string $phone): self
    {
        return new self(sprintf('Клиент с телефоном %s уже есть в этой компании.', $phone));
    }
}
