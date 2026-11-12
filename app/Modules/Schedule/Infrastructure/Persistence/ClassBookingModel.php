<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int $gym_id
 * @property int $session_id
 * @property int $client_id
 * @property string $status
 * @property string|null $idempotency_key
 * @property Carbon $booked_at
 * @property Carbon|null $cancelled_at
 */
final class ClassBookingModel extends Model
{
    use BelongsToCompany;
    use HasUlids;

    protected $table = 'schedule_bookings';

    protected $fillable = [
        'company_id',
        'gym_id',
        'session_id',
        'client_id',
        'status',
        'idempotency_key',
        'booked_at',
        'cancelled_at',
    ];

    protected $casts = [
        'booked_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
