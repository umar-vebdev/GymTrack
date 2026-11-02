<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $platform
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class DeviceTokenModel extends Model
{
    protected $table = 'notifications_device_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'platform',
    ];
}
