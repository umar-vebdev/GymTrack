<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Modules\Sales\Domain\MembershipRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $gym_id
 * @property int $user_id
 * @property int|null $client_id
 * @property int $membership_type_id
 * @property MembershipRequestStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $paid_at
 * @property int|null $sale_id
 * @property int|null $membership_id
 * @property string|null $idempotency_key
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class MembershipRequestModel extends Model
{
    protected $table = 'sales_membership_requests';

    protected $fillable = [
        'company_id',
        'gym_id',
        'user_id',
        'client_id',
        'membership_type_id',
        'status',
        'expires_at',
        'paid_at',
        'sale_id',
        'membership_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipRequestStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
