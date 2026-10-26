<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ListClientMembershipsTest extends TestCase
{
    use RefreshDatabase;

    private function createOwnerWithCompany(): User
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/business/companies', [
            'company_name' => 'Test Co',
            'gym_name' => 'Main Gym',
        ])->assertStatus(201);

        return $owner;
    }

    private function companyIdFor(User $user): int
    {
        return (int) StaffMemberModel::query()->where('user_id', $user->id)->value('company_id');
    }

    private function createClient(int $companyId, string $phone = '+992900111222'): int
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);

        return $clients->create($companyId, 'John Doe', $phone);
    }

    private function issueMembership(int $companyId, int $clientId, MembershipRules $rules): int
    {
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create($companyId, 'Plan', new Money(15000, 'TJS'), $rules);

        /** @var MembershipIssuerInterface $issuer */
        $issuer = $this->app->make(MembershipIssuerInterface::class);

        return $issuer->issue($companyId, $clientId, $typeId, new DateTimeImmutable('2026-10-01 10:00:00'));
    }

    public function test_owner_and_admin_can_list_client_memberships(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);
        $clientId = $this->createClient($companyId);

        $m1 = $this->issueMembership(
            $companyId,
            $clientId,
            MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 10)
        );
        $m2 = $this->issueMembership(
            $companyId,
            $clientId,
            MembershipRules::create(MembershipKind::Duration, StartMode::FirstVisit, durationDays: 30)
        );

        $admin = User::factory()->create();
        StaffMemberModel::create([
            'user_id' => $admin->id,
            'company_id' => $companyId,
            'role' => Role::ADMIN->value,
        ]);

        $res = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/business/clients/{$clientId}/memberships")
            ->assertOk();

        $data = $res->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($m2, $data[0]['id']);
        $this->assertSame('pending', $data[0]['status']);
        $this->assertSame($m1, $data[1]['id']);
        $this->assertSame('active', $data[1]['status']);
        $this->assertSame(10, $data[1]['remaining_visits']);
    }

    public function test_cannot_list_memberships_of_client_from_another_company(): void
    {
        $owner1 = $this->createOwnerWithCompany();
        $companyId1 = $this->companyIdFor($owner1);
        $clientId1 = $this->createClient($companyId1);

        $owner2 = $this->createOwnerWithCompany();

        $this->actingAs($owner2, 'sanctum')
            ->getJson("/api/v1/business/clients/{$clientId1}/memberships")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'CLIENT_NOT_FOUND');
    }

    public function test_non_staff_user_gets_403(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);
        $clientId = $this->createClient($companyId);

        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/business/clients/{$clientId}/memberships")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
