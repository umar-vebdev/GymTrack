<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Eloquent;

use App\Shared\Application\CurrentCompanyInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var CurrentCompanyInterface $currentCompany */
        $currentCompany = app(CurrentCompanyInterface::class);

        if ($currentCompany->hasId()) {
            $builder->where($model->getTable().'.company_id', $currentCompany->id());
        }
    }
}
