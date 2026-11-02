<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property int|null $client_id
 * @property string $channel
 * @property string $type
 * @property string $recipient
 * @property string|null $title
 * @property string $body
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class NotificationLogModel extends Model
{
    protected $table = 'notifications_logs';

    protected $fillable = [
        'company_id',
        'user_id',
        'client_id',
        'channel',
        'type',
        'recipient',
        'title',
        'body',
        'entity_type',
        'entity_id',
        'status',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
