<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain;

enum VisitMethod: string
{
    case Qr = 'qr';
    case Manual = 'manual';
}
