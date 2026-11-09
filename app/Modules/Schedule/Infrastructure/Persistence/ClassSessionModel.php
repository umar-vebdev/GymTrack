<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int $gym_id
 * @property int $template_id
 * @property int $trainer_id
 * @property string $title
 * @property int $capacity
 * @property int $booked_count
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $status
 */
final class ClassSessionModel extends Model
{
    use BelongsToCompany;
    use HasUlids;

    protected $table = 'schedule_sessions';

    protected $fillable = [
        'company_id',
        'gym_id',
        'template_id',
        'trainer_id',
        'title',
        'capacity',
        'booked_count',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'booked_count' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
