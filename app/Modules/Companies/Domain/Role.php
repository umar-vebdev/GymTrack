<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

enum Role: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
}
