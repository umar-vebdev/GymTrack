<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $gym_id
 * @property string $path
 * @property int $order
 * @property Carbon $created_at
 */
final class GymPhotoModel extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $table = 'companies_gym_photos';

    protected $fillable = ['company_id', 'gym_id', 'path', 'order'];

    protected $casts = ['order' => 'integer'];
}
