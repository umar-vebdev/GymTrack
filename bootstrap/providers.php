<?php

declare(strict_types=1);

use App\Modules\Analytics\AnalyticsServiceProvider;
use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Billing\BillingServiceProvider;
use App\Modules\Clients\ClientsServiceProvider;
use App\Modules\Companies\CompaniesServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Memberships\MembershipsServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use App\Modules\Visits\VisitsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    CompaniesServiceProvider::class,
    AuditServiceProvider::class,
    ClientsServiceProvider::class,
    MembershipsServiceProvider::class,
    SalesServiceProvider::class,
    BillingServiceProvider::class,
    AnalyticsServiceProvider::class,
    VisitsServiceProvider::class,
];
