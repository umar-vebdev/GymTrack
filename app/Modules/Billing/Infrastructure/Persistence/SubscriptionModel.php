<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $plan_id
 * @property string $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
final class SubscriptionModel extends Model
{
    use BelongsToCompany;

    protected $table = 'billing_subscriptions';

    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}

