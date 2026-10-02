<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $company_id
 * @property string $action
 * @property string $entity_type
 * @property string $entity_id
 * @property array<string, mixed>|null $payload
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
final class AuditEntryModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_entries';

    protected $fillable = [
        'user_id',
        'company_id',
        'action',
        'entity_type',
        'entity_id',
        'payload',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
