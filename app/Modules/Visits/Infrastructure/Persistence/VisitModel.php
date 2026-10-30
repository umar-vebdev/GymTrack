<?php

declare(strict_types=1);

namespace App\Modules\Visits\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $gym_id
 * @property int $client_id
 * @property int $membership_id
 * @property string $method
 * @property string $status
 * @property Carbon $visited_at
 * @property string $visit_date
 * @property int $daily_entry_number
 * @property int|null $registered_by_user_id
 * @property string|null $device
 * @property string|null $idempotency_key
 * @property string|null $cancel_reason
 * @property int|null $cancelled_by_user_id
 * @property Carbon|null $cancelled_at
 */
final class VisitModel extends Model
{
    use BelongsToCompany;

    protected $table = 'visits_visits';

    protected $fillable = [
        'company_id',
        'gym_id',
        'client_id',
        'membership_id',
        'method',
        'status',
        'visited_at',
        'visit_date',
        'daily_entry_number',
        'registered_by_user_id',
        'device',
        'idempotency_key',
        'cancel_reason',
        'cancelled_by_user_id',
        'cancelled_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}

