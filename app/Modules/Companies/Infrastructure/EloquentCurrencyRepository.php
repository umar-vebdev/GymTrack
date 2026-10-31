<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Domain\Currency;
use App\Modules\Companies\Domain\CurrencyRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CurrencyModel;

final class EloquentCurrencyRepository implements CurrencyRepositoryInterface
{
    /**
     * @return list<Currency>
     */
    public function all(): array
    {
        /** @var list<Currency> $result */
        $result = CurrencyModel::query()
            ->orderBy('id')
            ->get()
            ->map(fn (CurrencyModel $m) => new Currency(
                id: $m->id,
                code: $m->code,
                name: $m->name,
                symbol: $m->symbol
            ))
            ->all();

        return $result;
    }

    public function findById(int $id): ?Currency
    {
        $m = CurrencyModel::find($id);

        return $m ? new Currency(
            id: $m->id,
            code: $m->code,
            name: $m->name,
            symbol: $m->symbol
        ) : null;
    }
}

