<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $kind
 * @property string $start_mode
 * @property int $price_minor
 * @property string $currency
 * @property int|null $visits_count
 * @property int|null $duration_days
 * @property int|null $validity_days
 * @property int $daily_limit
 * @property int|null $time_from_minute
 * @property int|null $time_to_minute
 * @property int $freeze_max_days
 * @property int $freeze_max_times
 * @property bool $is_active
 */
final class MembershipTypeModel extends Model
{
    use BelongsToCompany;

    protected $table = 'membership_types';

    protected $fillable = [
        'company_id', 'name', 'kind', 'start_mode', 'price_minor', 'currency',
        'visits_count', 'duration_days', 'validity_days', 'daily_limit',
        'time_from_minute', 'time_to_minute', 'freeze_max_days', 'freeze_max_times', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
