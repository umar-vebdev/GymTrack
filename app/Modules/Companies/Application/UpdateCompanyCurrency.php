<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\CurrencyRepositoryInterface;
use InvalidArgumentException;

final class UpdateCompanyCurrency
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companies,
        private readonly CurrencyRepositoryInterface $currencies
    ) {}

    /**
     * @return array{company_id: int, currency_id: int, currency_code: string}
     */
    public function execute(int $companyId, int $currencyId): array
    {
        $currency = $this->currencies->findById($currencyId);
        if ($currency === null) {
            throw new InvalidArgumentException("Валюта с ID {$currencyId} не найдена.");
        }

        $this->companies->updateCurrency($companyId, $currencyId);

        return [
            'company_id' => $companyId,
            'currency_id' => $currency->id(),
            'currency_code' => $currency->code(),
        ];
    }
}
