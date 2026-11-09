<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int $gym_id
 * @property int $trainer_id
 * @property string $title
 * @property string|null $description
 * @property int $weekday
 * @property int $start_time_minute
 * @property int $duration_minutes
 * @property int $capacity
 * @property bool $is_active
 */
final class ClassTemplateModel extends Model
{
    use BelongsToCompany;
    use HasUlids;

    protected $table = 'schedule_class_templates';

    protected $fillable = [
        'company_id',
        'gym_id',
        'trainer_id',
        'title',
        'description',
        'weekday',
        'start_time_minute',
        'duration_minutes',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'start_time_minute' => 'integer',
        'duration_minutes' => 'integer',
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
