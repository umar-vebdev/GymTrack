<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int $gym_id
 * @property int $user_id
 * @property int $rating
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class GymReviewModel extends Model
{
    use BelongsToCompany;
    use HasUlids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected $table = 'companies_gym_reviews';

    protected $fillable = [
        'public_id',
        'company_id',
        'gym_id',
        'user_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];
}
