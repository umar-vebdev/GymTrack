<?php

declare(strict_types=1);

use App\Modules\Companies\CompaniesServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    CompaniesServiceProvider::class,
];
