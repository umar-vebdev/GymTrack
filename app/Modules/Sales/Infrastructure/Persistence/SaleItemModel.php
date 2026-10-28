<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $sale_id
 * @property int|null $product_id
 * @property int|null $membership_type_id
 * @property int|null $issued_membership_id
 * @property string $name
 * @property int $unit_price_minor
 * @property string $currency
 * @property int $quantity
 */
final class SaleItemModel extends Model
{
    protected $table = 'sales_sale_items';

    protected $fillable = [
        'sale_id',
        'product_id',
        'membership_type_id',
        'issued_membership_id',
        'name',
        'unit_price_minor',
        'currency',
        'quantity',
    ];
}

