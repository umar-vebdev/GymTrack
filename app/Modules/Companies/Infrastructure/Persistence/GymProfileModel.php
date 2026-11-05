<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $gym_id
 * @property string|null $description
 * @property string|null $address
 * @property string|null $district
 * @property bool $is_public
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $phone
 * @property array<string, mixed>|null $working_hours
 * @property list<string>|null $amenities
 * @property string $moderation_status
 * @property string|null $rejection_reason
 * @property Carbon|null $published_at
 */
final class GymProfileModel extends Model
{
    use BelongsToCompany;

    protected $table = 'companies_gym_profiles';

    protected $fillable = [
        'company_id', 'gym_id', 'description', 'address', 'latitude', 'longitude', 'phone',
        'working_hours', 'amenities', 'moderation_status', 'rejection_reason', 'published_at',
        'district', 'is_public',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'amenities' => 'array',
        'published_at' => 'datetime',
        'is_public' => 'boolean',
    ];
}
