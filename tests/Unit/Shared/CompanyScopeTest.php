<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Models\User;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Shared\Application\CurrentCompanyInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_queries_by_current_company(): void
    {
        $user = User::factory()->create();

        $company1 = CompanyModel::create(['owner_id' => $user->id, 'name' => 'Company 1']);
        $company2 = CompanyModel::create(['owner_id' => $user->id, 'name' => 'Company 2']);

        // Create gym for company 1 without CurrentCompany (manually setting company_id)
        GymModel::withoutGlobalScopes()->create(['company_id' => $company1->id, 'name' => 'Gym 1']);

        // Create gym for company 2 without CurrentCompany
        GymModel::withoutGlobalScopes()->create(['company_id' => $company2->id, 'name' => 'Gym 2']);

        /** @var CurrentCompanyInterface $currentCompany */
        $currentCompany = $this->app->make(CurrentCompanyInterface::class);
        $currentCompany->setId($company1->id);

        $gyms = GymModel::all();
        $this->assertCount(1, $gyms);
        $this->assertSame('Gym 1', $gyms->firstOrFail()->name);
    }

    public function test_it_automatically_sets_company_id_on_creation(): void
    {
        $user = User::factory()->create();
        $company = CompanyModel::create(['owner_id' => $user->id, 'name' => 'Company 1']);

        /** @var CurrentCompanyInterface $currentCompany */
        $currentCompany = $this->app->make(CurrentCompanyInterface::class);
        $currentCompany->setId($company->id);

        // We don't provide company_id, BelongsToCompany should set it
        $gym = GymModel::create(['name' => 'New Gym']);

        $this->assertSame($company->id, $gym->company_id);
    }
}
