<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\Currency;
use App\Modules\Companies\Domain\CurrencyRepositoryInterface;

final class ListCurrencies
{
    public function __construct(
        private readonly CurrencyRepositoryInterface $currencies
    ) {}

    /**
     * @return list<array{id: int, code: string, name: string, symbol: ?string}>
     */
    public function execute(): array
    {
        return array_map(
            fn (Currency $c) => [
                'id' => $c->id(),
                'code' => $c->code(),
                'name' => $c->name(),
                'symbol' => $c->symbol(),
            ],
            $this->currencies->all()
        );
    }
}
