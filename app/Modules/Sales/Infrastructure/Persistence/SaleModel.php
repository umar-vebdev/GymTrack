<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $client_id
 * @property string $status
 * @property string $payment_method
 * @property int $total_minor
 * @property string $currency
 * @property int $accepted_by_user_id
 * @property Carbon $paid_at
 * @property string|null $idempotency_key
 * @property string|null $refund_reason
 * @property int|null $refunded_by_user_id
 * @property Carbon|null $refunded_at
 * @property-read Collection<int, SaleItemModel> $items
 */
final class SaleModel extends Model
{
    use BelongsToCompany;

    protected $table = 'sales_sales';

    protected $fillable = [
        'company_id',
        'client_id',
        'status',
        'payment_method',
        'total_minor',
        'currency',
        'accepted_by_user_id',
        'paid_at',
        'idempotency_key',
        'refund_reason',
        'refunded_by_user_id',
        'refunded_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * @return HasMany<SaleItemModel, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItemModel::class, 'sale_id')->orderBy('id');
    }
}
