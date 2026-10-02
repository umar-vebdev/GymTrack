<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $company_id
 * @property string $role
 */
final class StaffMemberModel extends Model
{
    protected $table = 'staff_members';

    protected $fillable = ['user_id', 'company_id', 'role'];
}
