<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation\Resources;

use App\Modules\Companies\Domain\Gym;

final class GymResource
{
    /**
     * @return array{id: int, company_id: int, name: string, address: string|null, timezone: string}
     */
    public static function toArray(Gym $gym): array
    {
        return [
            'id' => $gym->id(),
            'company_id' => $gym->companyId(),
            'name' => $gym->name(),
            'address' => $gym->address(),
            'timezone' => $gym->timezone(),
        ];
    }
}

