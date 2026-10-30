<?php

declare(strict_types=1);

namespace Tests\Unit\Clients;

use App\Models\User;
use App\Modules\Clients\Contracts\ClientsAnalyticsQueryInterface;
use App\Modules\Clients\Infrastructure\Persistence\ClientModel;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ClientsAnalyticsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_new_existing_and_total_clients_with_company_isolation(): void
    {
        $owner = User::factory()->create();
        $companyA = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Gym A'])->id;
        $companyB = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Gym B'])->id;

        // Постоянные клиенты компании A (созданы до начала периода)
        DB::table('clients')->insert([
            [
                'company_id' => $companyA,
                'name' => 'Old Client 1',
                'phone' => '+992900000001',
                'created_at' => '2026-09-10 10:00:00',
                'updated_at' => '2026-09-10 10:00:00',
            ],
            [
                'company_id' => $companyA,
                'name' => 'Old Client 2',
                'phone' => '+992900000002',
                'created_at' => '2026-09-25 14:00:00',
                'updated_at' => '2026-09-25 14:00:00',
            ],
        ]);

        // Новые клиенты компании A в периоде (октябрь 2026)
        DB::table('clients')->insert([
            [
                'company_id' => $companyA,
                'name' => 'New Client 1',
                'phone' => '+992900000003',
                'created_at' => '2026-10-05 09:00:00',
                'updated_at' => '2026-10-05 09:00:00',
            ],
            [
                'company_id' => $companyA,
                'name' => 'New Client 2',
                'phone' => '+992900000004',
                'created_at' => '2026-10-20 18:00:00',
                'updated_at' => '2026-10-20 18:00:00',
            ],
            [
                'company_id' => $companyA,
                'name' => 'New Client 3',
                'phone' => '+992900000005',
                'created_at' => '2026-10-31 12:00:00',
                'updated_at' => '2026-10-31 12:00:00',
            ],
        ]);

        // Клиент компании A после периода (ноябрь 2026)
        DB::table('clients')->insert([
            'company_id' => $companyA,
            'name' => 'Future Client',
            'phone' => '+992900000006',
            'created_at' => '2026-11-02 10:00:00',
            'updated_at' => '2026-11-02 10:00:00',
        ]);

        // Клиент другой компании в том же периоде
        ClientModel::withoutGlobalScopes()->create([
            'company_id' => $companyB,
            'name' => 'Other Company Client',
            'phone' => '+992900999999',
        ]);

        /** @var ClientsAnalyticsQueryInterface $query */
        $query = $this->app->make(ClientsAnalyticsQueryInterface::class);

        $summary = $query->summaryForPeriod(
            $companyA,
            new DateTimeImmutable('2026-10-01 00:00:00'),
            new DateTimeImmutable('2026-10-31 23:59:59'),
        );

        $this->assertSame(5, $summary->totalClients);
        $this->assertSame(3, $summary->newClients);
        $this->assertSame(2, $summary->existingClients);
    }
}
