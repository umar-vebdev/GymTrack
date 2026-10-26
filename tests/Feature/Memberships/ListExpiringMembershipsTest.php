<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;
use App\Modules\Memberships\Application\FreezeMembership;
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

final class ListExpiringMembershipsTest extends TestCase
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
        int $clientId,
        string $issuedAt,
        int $durationDays,
        int $freezeMaxDays = 0,
        int $freezeMaxTimes = 0
    ): int {
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $rules = MembershipRules::create(
            kind: MembershipKind::Duration,
            startMode: StartMode::OnSale,
            durationDays: $durationDays,
            freezeMaxDays: $freezeMaxDays,
            freezeMaxTimes: $freezeMaxTimes
        );
        $typeId = $types->create($companyId, 'Plan', new Money(20000, 'TJS'), $rules);

        /** @var MembershipIssuerInterface $issuer */
        $issuer = $this->app->make(MembershipIssuerInterface::class);

        return $issuer->issue($companyId, $clientId, $typeId, new DateTimeImmutable($issuedAt));
    }

    public function test_returns_only_active_memberships_expiring_within_7_days_ordered_by_ends_at(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);
        $clientId = $this->createClient($companyId);

        // Now is 2026-10-10 10:00:00 (window: 2026-10-10 10:00:00 .. 2026-10-17 10:00:00)
        // 1) Ends 2026-10-16 10:00:00 (in 6 days)
        $expiringIn6Days = $this->issueDurationMembership($companyId, $clientId, '2026-10-01 10:00:00', 15);
        // 2) Ends 2026-10-12 10:00:00 (in 2 days)
        $expiringIn2Days = $this->issueDurationMembership($companyId, $clientId, '2026-10-01 10:00:00', 11);
        // 3) Ends 2026-10-08 10:00:00 (already in the past)
        $this->issueDurationMembership($companyId, $clientId, '2026-10-01 10:00:00', 7);
        // 4) Ends 2026-10-31 10:00:00 (in 21 days — outside 7-day window)
        $this->issueDurationMembership($companyId, $clientId, '2026-10-01 10:00:00', 30);
        // 5) Frozen membership ending in 4 days — must be excluded
        $frozenId = $this->issueDurationMembership($companyId, $clientId, '2026-10-01 10:00:00', 13, freezeMaxDays: 7, freezeMaxTimes: 1);
        $this->bindFixedClock('2026-10-05 10:00:00');
        /** @var FreezeMembership $freeze */
        $freeze = $this->app->make(FreezeMembership::class);
        $freeze->execute($companyId, $frozenId);

        // Another company's expiring membership — must not leak
        $otherOwner = $this->createOwnerWithCompany();
        $otherCompanyId = $this->companyIdFor($otherOwner);
        $otherClientId = $this->createClient($otherCompanyId, '+992900999888');
        $this->issueDurationMembership($otherCompanyId, $otherClientId, '2026-10-01 10:00:00', 12);

        $admin = User::factory()->create();
        StaffMemberModel::create([
            'user_id' => $admin->id,
            'company_id' => $companyId,
            'role' => Role::ADMIN->value,
        ]);

        $this->bindFixedClock('2026-10-10 10:00:00');

        $res = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/business/memberships/expiring')
            ->assertOk();

        $data = $res->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($expiringIn2Days, $data[0]['id']);
        $this->assertSame($expiringIn6Days, $data[1]['id']);
    }

    public function test_non_staff_user_gets_403(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/v1/business/memberships/expiring')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}

