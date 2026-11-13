<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation\Resources;

use App\Modules\Companies\Application\StaffMemberView;

final class StaffMemberResource
{
    /**
     * @return array{id: int, user_id: int, company_id: int, role: string, name: string|null, phone: string|null, email: string|null}
     */
    public static function toArray(StaffMemberView $view): array
    {
        return [
            'id' => $view->id,
            'user_id' => $view->userId,
            'company_id' => $view->companyId,
            'role' => $view->role->value,
            'name' => $view->name,
            'phone' => $view->phone,
            'email' => $view->email,
        ];
    }
}
