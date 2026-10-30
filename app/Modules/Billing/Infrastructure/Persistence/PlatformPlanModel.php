<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $period
 * @property int $price_minor
 * @property string $currency
 * @property int $max_gyms
 * @property int $max_staff
 * @property bool $is_active
 */
final class PlatformPlanModel extends Model
{
    protected $table = 'billing_plans';

    protected $fillable = [
        'name',
        'period',
        'price_minor',
        'currency',
        'max_gyms',
        'max_staff',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
