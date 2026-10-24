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
use App\Shared\Domain\Clock;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FreezeAndUnfreezeMembershipTest extends TestCase
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

    private function bindFixedClock(string $time): void
    {
        $fixed = new DateTimeImmutable($time);
        $this->app->instance(Clock::class, new class($fixed) implements Clock
        {
            public function __construct(private readonly DateTimeImmutable $now) {}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        });
    }

    private function issueDurationMembership(
        int $companyId,
        int $durationDays = 30,
        int $freezeMaxDays = 14,
        int $freezeMaxTimes = 1
    ): int {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($companyId, 'John Doe', '+992900111222');

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $rules = MembershipRules::create(
            kind: MembershipKind::Duration,
            startMode: StartMode::OnSale,
            durationDays: $durationDays,
            freezeMaxDays: $freezeMaxDays,
            freezeMaxTimes: $freezeMaxTimes
        );
        $typeId = $types->create($companyId, 'Monthly', new Money(20000, 'TJS'), $rules);

        /** @var MembershipIssuerInterface $issuer */
        $issuer = $this->app->make(MembershipIssuerInterface::class);

        return $issuer->issue($companyId, $clientId, $typeId, new DateTimeImmutable('2026-10-01 10:00:00'));
    }

    public function test_owner_and_admin_can_freeze_and_unfreeze_extending_ends_at(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);
        $membershipId = $this->issueDurationMembership($companyId, durationDays: 30, freezeMaxDays: 14, freezeMaxTimes: 2);

        $admin = User::factory()->create();
        StaffMemberModel::create([
            'user_id' => $admin->id,
            'company_id' => $companyId,
            'role' => Role::ADMIN->value,
        ]);

        // Freeze by admin on 2026-10-05
        $this->bindFixedClock('2026-10-05 10:00:00');
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/freeze")
            ->assertOk()
            ->assertJsonPath('data.status', 'frozen')
            ->assertJsonPath('data.freeze_times_used', 1);

        // Unfreeze by owner 5 days later (2026-10-10): ends_at shifts from 2026-10-31 to 2026-11-05
        $this->bindFixedClock('2026-10-10 10:00:00');
        $res = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/unfreeze")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.freeze_days_used', 5)
            ->assertJsonPath('data.frozen_at', null);

        $this->assertStringStartsWith('2026-11-05T10:00:00', (string) $res->json('data.ends_at'));
    }

    public function test_cannot_exceed_freeze_limit(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);
        $membershipId = $this->issueDurationMembership($companyId, durationDays: 30, freezeMaxDays: 7, freezeMaxTimes: 1);

        $this->bindFixedClock('2026-10-05 10:00:00');
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/freeze")
            ->assertOk();

        $this->bindFixedClock('2026-10-07 10:00:00');
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/unfreeze")
            ->assertOk();

        // Second freeze should fail with 409 MEMBERSHIP_FREEZE_NOT_ALLOWED
        $this->bindFixedClock('2026-10-10 10:00:00');
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/freeze")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEMBERSHIP_FREEZE_NOT_ALLOWED');
    }

    public function test_cannot_freeze_or_unfreeze_membership_of_another_company(): void
    {
        $owner1 = $this->createOwnerWithCompany();
        $companyId1 = $this->companyIdFor($owner1);
        $membershipId = $this->issueDurationMembership($companyId1);

        $owner2 = $this->createOwnerWithCompany();

        $this->bindFixedClock('2026-10-05 10:00:00');
        $this->actingAs($owner2, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/freeze")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MEMBERSHIP_NOT_FOUND');

        $this->actingAs($owner2, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/unfreeze")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MEMBERSHIP_NOT_FOUND');
    }

    public function test_non_staff_user_gets_403(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);
        $membershipId = $this->issueDurationMembership($companyId);

        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/business/memberships/{$membershipId}/freeze")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}

