<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property int $type_id
 * @property string $status
 * @property int|null $remaining_visits
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 * @property int $freeze_days_used
 * @property int $freeze_times_used
 * @property CarbonInterface|null $frozen_at
 * @property string|null $cancellation_reason
 */
final class MembershipModel extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'memberships';

    protected $fillable = [
        'company_id', 'client_id', 'type_id', 'status', 'remaining_visits',
        'starts_at', 'ends_at', 'freeze_days_used', 'freeze_times_used',
        'frozen_at', 'cancellation_reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'frozen_at' => 'datetime',
    ];
}
