<?php

declare(strict_types=1);

namespace App\Modules\Clients\Infrastructure\Persistence;

use App\Shared\Infrastructure\Eloquent\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property string $name
 * @property string $phone
 */
final class ClientModel extends Model
{
    use BelongsToCompany;

    protected $table = 'clients';

    protected $fillable = ['company_id', 'user_id', 'name', 'phone'];
}
