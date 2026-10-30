<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Contracts\GymQrLookupInterface;
use App\Modules\Companies\Contracts\GymQrSummary;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;

final class EloquentGymQrLookup implements GymQrLookupInterface
{
    public function findById(int $gymId): ?GymQrSummary
    {
        $model = GymModel::withoutGlobalScopes()->find($gymId);

        if ($model === null) {
            return null;
        }

        if ($model->qr_secret === null || $model->qr_secret === '') {
            $model->qr_secret = bin2hex(random_bytes(32));
            $model->save();
        }

        return new GymQrSummary(
            id: $model->id,
            companyId: $model->company_id,
            name: $model->name,
            qrSecret: $model->qr_secret,
        );
    }
}
