<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property string $name
 * @property string|null $address
 * @property string $timezone
 * @property bool $geo_check_enabled
 * @property int $geo_check_radius_meters
 * @property string|null $qr_secret
 */
final class GymModel extends Model
{
    use BelongsToCompany;
    use HasUlids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected $table = 'gyms';

    protected $fillable = [
        'company_id',
        'name',
        'address',
        'timezone',
        'geo_check_enabled',
        'geo_check_radius_meters',
        'qr_secret',
    ];

    protected $casts = [
        'geo_check_enabled' => 'boolean',
        'geo_check_radius_meters' => 'integer',
        'qr_secret' => 'encrypted',
    ];

    protected static function booted(): void
    {
        self::creating(static function (self $gym): void {
            if ($gym->qr_secret === null || $gym->qr_secret === '') {
                $gym->qr_secret = bin2hex(random_bytes(32));
            }
        });
    }
}
