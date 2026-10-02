<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Eloquent;

use App\Shared\Application\CurrentCompanyInterface;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            /** @var CurrentCompanyInterface $currentCompany */
            $currentCompany = app(CurrentCompanyInterface::class);

            if ($currentCompany->hasId()) {
                $model->company_id ??= $currentCompany->id();
            }
        });
    }
}
