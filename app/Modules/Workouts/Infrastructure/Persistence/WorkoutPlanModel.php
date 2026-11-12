<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $user_id
 * @property int|null $source_plan_id
 * @property string $title
 * @property string|null $description
 * @property string $level
 * @property bool $is_published
 * @property bool $is_active
 * @property-read Collection<int, WorkoutPlanItemModel> $items
 */
final class WorkoutPlanModel extends Model
{
    use HasUlids;

    protected $table = 'workouts_plans';

    protected $fillable = [
        'user_id',
        'source_plan_id',
        'title',
        'description',
        'level',
        'is_published',
        'is_active',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'source_plan_id' => 'integer',
        'is_published' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @return HasMany<WorkoutPlanItemModel, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WorkoutPlanItemModel::class, 'plan_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}

