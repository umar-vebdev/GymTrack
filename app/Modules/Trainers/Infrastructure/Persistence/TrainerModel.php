<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int $gym_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $bio
 * @property bool $is_active
 */
final class TrainerModel extends Model
{
    use BelongsToCompany;
    use HasUlids;

    protected $table = 'trainers_profiles';

    protected $fillable = ['company_id', 'gym_id', 'user_id', 'name', 'bio', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
