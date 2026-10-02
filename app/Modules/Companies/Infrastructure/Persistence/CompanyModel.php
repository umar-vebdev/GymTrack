<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 */
final class CompanyModel extends Model
{
    protected $table = 'companies';

    protected $fillable = ['owner_id', 'name'];
}
