<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

/**
 * Публичный контракт модуля Billing: проверка, находится ли компания в режиме «только чтение»
 * из-за окончания пробного периода или подписки (Бизнес-ТЗ 4.1, 12.8).
 */
interface SubscriptionAccessInterface
{
    public function isReadOnly(int $companyId): bool;
}

