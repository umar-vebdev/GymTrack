<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

enum ModerationStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}

