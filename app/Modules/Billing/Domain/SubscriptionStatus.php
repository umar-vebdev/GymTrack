<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
}

