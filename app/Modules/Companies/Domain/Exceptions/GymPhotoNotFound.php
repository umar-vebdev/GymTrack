<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain\Exceptions;

use DomainException;

final class GymPhotoNotFound extends DomainException
{
    public static function withId(int $photoId): self
    {
        return new self(sprintf('Фотография #%d не найдена.', $photoId));
    }
}
