<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use DomainException;

final class ClientRegistrationDenied extends DomainException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function underage(): self
    {
        return new self(
            'UNDERAGE_REGISTRATION_NOT_ALLOWED',
            'Регистрация в приложении доступна только с 18 лет.',
        );
    }

    public static function consentRequired(): self
    {
        return new self(
            'PERSONAL_DATA_CONSENT_REQUIRED',
            'Для регистрации необходимо согласие на обработку персональных данных.',
        );
    }

    public static function phoneAlreadyRegistered(): self
    {
        return new self(
            'PHONE_ALREADY_REGISTERED',
            'Пользователь с таким номером телефона уже зарегистрирован.',
        );
    }
}
