<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

interface CurrencyRepositoryInterface
{
    /**
     * @return list<Currency>
     */
    public function all(): array;

    public function findById(int $id): ?Currency;
}
