<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
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
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ExpireDueMembershipsTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $name): int
    {
        $user = User::factory()->create();

        return CompanyModel::create(['owner_id' => $user->id, 'name' => $name])->id;
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
        int $durationDays = 30,
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

    public function test_command_expires_due_active_memberships_and_is_idempotent(): void
    {
        $companyId = $this->createCompany('Co A');
        $clientId = $this->createClient($companyId);

        // Ends on 2026-10-11 10:00:00 (overdue at 2026-10-20)
        $dueId = $this->issueDurationMembership($companyId, $clientId, '2026-10-01 10:00:00', durationDays: 10);

        // Ends on 2026-10-31 10:00:00 (still valid at 2026-10-20)
        $validId = $this->issueDurationMembership($companyId, $clientId, '2026-10-01 10:00:00', durationDays: 30);

        // Frozen membership whose original ends_at passed while frozen — must NOT expire automatically
        $frozenId = $this->issueDurationMembership(
            $companyId,
            $clientId,
            '2026-10-01 10:00:00',
            durationDays: 10,
            freezeMaxDays: 14,
            freezeMaxTimes: 1
        );
        $this->bindFixedClock('2026-10-05 10:00:00');
        /** @var FreezeMembership $freeze */
        $freeze = $this->app->make(FreezeMembership::class);
        $freeze->execute($companyId, $frozenId);

        $this->bindFixedClock('2026-10-20 12:00:00');

        $this->assertSame(0, Artisan::call('memberships:expire-due'));
        $this->assertStringContainsString('Завершено истёкших абонементов: 1', Artisan::output());

        $this->assertDatabaseHas('memberships', ['id' => $dueId, 'status' => 'expired']);
        $this->assertDatabaseHas('memberships', ['id' => $validId, 'status' => 'active']);
        $this->assertDatabaseHas('memberships', ['id' => $frozenId, 'status' => 'frozen']);

        // Second run is idempotent: 0 expired
        $this->assertSame(0, Artisan::call('memberships:expire-due'));
        $this->assertStringContainsString('Завершено истёкших абонементов: 0', Artisan::output());
    }

    public function test_command_can_target_single_company_explicitly(): void
    {
        $companyA = $this->createCompany('Co A');
        $clientA = $this->createClient($companyA, '+992900111001');
        $dueA = $this->issueDurationMembership($companyA, $clientA, '2026-10-01 10:00:00', durationDays: 5);

        $companyB = $this->createCompany('Co B');
        $clientB = $this->createClient($companyB, '+992900111002');
        $dueB = $this->issueDurationMembership($companyB, $clientB, '2026-10-01 10:00:00', durationDays: 5);

        $this->bindFixedClock('2026-10-20 12:00:00');

        $this->assertSame(0, Artisan::call('memberships:expire-due', ['--company' => (string) $companyA]));
        $this->assertStringContainsString('Завершено истёкших абонементов: 1', Artisan::output());

        $this->assertDatabaseHas('memberships', ['id' => $dueA, 'status' => 'expired']);
        $this->assertDatabaseHas('memberships', ['id' => $dueB, 'status' => 'active']);
    }
}
