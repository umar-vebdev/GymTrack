<?php
declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $address
 */
final class GymModel extends Model
{
    protected $table = 'gyms';
    protected $fillable = ['company_id', 'name', 'address'];
}
