<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\User;
use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientLookupByUserTest extends TestCase
{
    use RefreshDatabase;

    private function company(): int
    {
        return CompanyModel::create(['owner_id' => User::factory()->create()->id, 'name' => 'Co'])->id;
    }

    private function lookup(): ClientLookupInterface
    {
        return $this->app->make(ClientLookupInterface::class);
    }

    private function clients(): ClientRepositoryInterface
    {
        return $this->app->make(ClientRepositoryInterface::class);
    }

    public function test_finds_card_of_user_in_given_company_only(): void
    {
        $user = User::factory()->create();
        $companyA = $this->company();
        $companyB = $this->company();

        $cardA = $this->clients()->create($companyA, 'John', '+992900111222', $user->id);
        $cardB = $this->clients()->create($companyB, 'John', '+992900111222', $user->id);

        $this->assertSame($cardA, $this->lookup()->findIdByUser($user->id, $companyA));
        $this->assertSame($cardB, $this->lookup()->findIdByUser($user->id, $companyB));
    }

    public function test_returns_null_when_not_linked_or_other_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $company = $this->company();

        $this->clients()->create($company, 'Unlinked', '+992900111333');
        $this->clients()->create($company, 'Other', '+992900111444', $other->id);

        $this->assertNull($this->lookup()->findIdByUser($user->id, $company));
    }

    public function test_picks_earliest_card_when_user_has_several_in_one_company(): void
    {
        $user = User::factory()->create();
        $company = $this->company();

        $first = $this->clients()->create($company, 'First', '+992900111555', $user->id);
        $this->clients()->create($company, 'Second', '+992900111666', $user->id);

        $this->assertSame($first, $this->lookup()->findIdByUser($user->id, $company));
    }
}
