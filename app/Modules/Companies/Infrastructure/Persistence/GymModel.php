<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $address
 * @property string|null $qr_secret
 */
final class GymModel extends Model
{
    use BelongsToCompany;

    protected $table = 'gyms';

    protected $fillable = ['company_id', 'name', 'address', 'qr_secret'];

    protected $casts = [
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
