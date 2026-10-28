<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property int $price_minor
 * @property string $currency
 * @property int|null $stock_quantity
 * @property bool $is_active
 */
final class ProductModel extends Model
{
    use BelongsToCompany;

    protected $table = 'sales_products';

    protected $fillable = [
        'company_id',
        'name',
        'price_minor',
        'currency',
        'stock_quantity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
