<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $symbol
 */
final class CurrencyModel extends Model
{
    protected $table = 'currencies';

    protected $fillable = ['code', 'name', 'symbol'];
}
