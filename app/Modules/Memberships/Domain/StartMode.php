<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

enum StartMode: string
{
    /** Срок идёт с даты продажи. */
    case OnSale = 'on_sale';

    /** Срок идёт с первого посещения. */
    case FirstVisit = 'first_visit';
}
