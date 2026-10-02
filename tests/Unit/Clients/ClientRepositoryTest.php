<?php

declare(strict_types=1);

namespace Tests\Unit\Clients;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_finds_client(): void
    {
        $user = User::factory()->create();
        $company = CompanyModel::create(['owner_id' => $user->id, 'name' => 'Company']);

        /** @var ClientRepositoryInterface $repo */
        $repo = $this->app->make(ClientRepositoryInterface::class);

        $id = $repo->create($company->id, 'John Doe', '+992000111222');

        $client = $repo->findById($id);

        $this->assertNotNull($client);
        $this->assertSame('John Doe', $client->name());
        $this->assertSame('+992000111222', $client->phone());
        $this->assertSame($company->id, $client->companyId());
        $this->assertNull($client->userId());
    }
}
