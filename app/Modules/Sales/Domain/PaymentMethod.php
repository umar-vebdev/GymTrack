<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

/**
 * Способ оплаты при фиксации отметки об оплате (PaymentMark).
 * Онлайн-платежи на платформе не проводятся (Бизнес-ТЗ 0, 3, 4.4).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case CardTerminal = 'card_terminal';
    case Transfer = 'transfer';
}

