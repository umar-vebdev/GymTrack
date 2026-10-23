<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

enum MembershipKind: string
{
    /** Пакет посещений («12 занятий»). */
    case Visits = 'visits';

    /** По сроку («месяц», «год»). */
    case Duration = 'duration';

    /** Безлимит на срок. */
    case Unlimited = 'unlimited';

    /** Разовое посещение, действует в день выдачи. */
    case Single = 'single';
}
