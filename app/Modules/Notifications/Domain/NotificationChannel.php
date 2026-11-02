<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain;

enum NotificationChannel: string
{
    case Push = 'push';
    case Sms = 'sms';
}
