<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

/**
 * Публичный контракт модуля Companies: получение кода валюты компании (ISO 4217, например 'TJS', 'RUB').
 */
interface CompanyCurrencyLookupInterface
{
    public function getCurrencyCode(int $companyId): string;
}

