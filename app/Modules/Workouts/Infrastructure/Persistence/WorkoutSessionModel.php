<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int|null $plan_id
 * @property string $title
 * @property string $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property string|null $notes
 * @property-read Collection<int, WorkoutSetModel> $sets
 */
final class WorkoutSessionModel extends Model
{
    use HasUlids;

    protected $table = 'workouts_sessions';

    protected $fillable = [
        'user_id',
        'plan_id',
        'title',
        'status',
        'started_at',
        'finished_at',
        'notes',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'plan_id' => 'integer',
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @return HasMany<WorkoutSetModel, $this>
     */
    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSetModel::class, 'session_id')
            ->orderBy('performed_at')
            ->orderBy('id');
    }
}

