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

final class CancelMembershipTest extends TestCase
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

    private function issueMembership(int $companyId): int
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($companyId, 'John Doe', '+992900111222');

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $rules = MembershipRules::create(
            kind: MembershipKind::Visits,
            startMode: StartMode::OnSale,
            visitsCount: 10
        );
        $typeId = $types->create($companyId, '10 Visits', new Money(15000, 'TJS'), $rules);

        /** @var MembershipIssuerInterface $issuer */
        $issuer = $this->app->make(MembershipIssuerInterface::class);

        return $issuer->issue($companyId, $clientId, $typeId, new DateTimeImmutable('2026-10-01 10:00:00'));
    }

    public function test_owner_can_cancel_membership_with_reason_and_writes_audit(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);
        $membershipId = $this->issueMembership($companyId);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/cancel", [
                'reason' => '  Переезд в другой город  ',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Переезд в другой город');

        $this->assertDatabaseHas('memberships', [
            'id' => $membershipId,
            'status' => 'cancelled',
            'cancellation_reason' => 'Переезд в другой город',
        ]);

        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'action' => 'MEMBERSHIP_CANCELLED',
            'entity_type' => 'Membership',
            'entity_id' => (string) $membershipId,
        ]);
    }

    public function test_admin_cannot_cancel_membership(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);
        $membershipId = $this->issueMembership($companyId);

        $admin = User::factory()->create();
        StaffMemberModel::create([
            'user_id' => $admin->id,
            'company_id' => $companyId,
            'role' => Role::ADMIN->value,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/cancel", [
                'reason' => 'Ошибка кассира',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_reason_is_required(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);
        $membershipId = $this->issueMembership($companyId);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/cancel", [
                'reason' => '   ',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_cannot_cancel_already_cancelled_membership(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);
        $membershipId = $this->issueMembership($companyId);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/cancel", [
                'reason' => 'Причина 1',
            ])
            ->assertOk();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/cancel", [
                'reason' => 'Причина 2',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEMBERSHIP_ALREADY_CANCELLED');
    }

    public function test_cannot_cancel_membership_of_another_company(): void
    {
        $owner1 = $this->createOwnerWithCompany();
        $companyId1 = $this->companyIdFor($owner1);
        $membershipId = $this->issueMembership($companyId1);

        $owner2 = $this->createOwnerWithCompany();

        $this->actingAs($owner2, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/cancel", [
                'reason' => 'Попытка чужой отмены',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MEMBERSHIP_NOT_FOUND');
    }
}
