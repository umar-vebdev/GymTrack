<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Contracts\CompanyCurrencyLookupInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;

final class EloquentCompanyCurrencyLookup implements CompanyCurrencyLookupInterface
{
    public function getCurrencyCode(int $companyId): string
    {
        /** @var CompanyModel|null $company */
        $company = CompanyModel::query()
            ->with('currency')
            ->find($companyId);

        return $company?->currency->code ?? 'TJS';
    }
}
