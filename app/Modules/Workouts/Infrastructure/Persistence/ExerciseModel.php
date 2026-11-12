<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $user_id
 * @property string $name
 * @property string $muscle_group
 * @property string|null $description
 * @property bool $is_active
 */
final class ExerciseModel extends Model
{
    use HasUlids;

    protected $table = 'workouts_exercises';

    protected $fillable = [
        'user_id',
        'name',
        'muscle_group',
        'description',
        'is_active',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
