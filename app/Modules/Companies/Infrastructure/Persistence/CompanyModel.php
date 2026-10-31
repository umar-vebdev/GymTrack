<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property int $currency_id
 * @property-read CurrencyModel|null $currency
 */
final class CompanyModel extends Model
{
    protected $table = 'companies';

    protected $fillable = ['owner_id', 'name', 'currency_id'];

    /**
     * @return BelongsTo<CurrencyModel, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(CurrencyModel::class, 'currency_id');
    }
}
