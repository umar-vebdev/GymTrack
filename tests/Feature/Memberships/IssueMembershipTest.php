<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Memberships\Domain\Exceptions\ClientNotAvailable;
use App\Modules\Memberships\Domain\Exceptions\MembershipTypeInactive;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeNotFound;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IssueMembershipTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerId;

    private function company(string $name): int
    {
        $user = User::factory()->create();
        $this->ownerId = $user->id;

        return CompanyModel::create(['owner_id' => $user->id, 'name' => $name])->id;
    }

    private function client(int $companyId, string $phone = '+992900111222'): int
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);

        return $clients->create($companyId, 'John', $phone);
    }

    private function type(int $companyId, MembershipRules $rules): int
    {
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);

        return $types->create($companyId, 'Type', new Money(1000, 'TJS'), $rules);
    }

    private function issuer(): MembershipIssuerInterface
    {
        return $this->app->make(MembershipIssuerInterface::class);
    }

    public function test_it_issues_visits_membership_and_writes_audit(): void
    {
        $companyId = $this->company('Co');
        $clientId = $this->client($companyId);
        $typeId = $this->type($companyId, MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 12, validityDays: 60));

        $id = $this->issuer()->issue($companyId, $clientId, $typeId, new DateTimeImmutable('2026-10-01 10:00:00'), $this->ownerId);

        $this->assertDatabaseHas('memberships', [
            'id' => $id, 'client_id' => $clientId, 'type_id' => $typeId,
            'status' => 'active', 'remaining_visits' => 12,
        ]);
        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $companyId, 'user_id' => $this->ownerId,
            'action' => 'MEMBERSHIP_ISSUED', 'entity_id' => (string) $id,
        ]);
    }

    public function test_first_visit_mode_creates_pending_membership(): void
    {
        $companyId = $this->company('Co');
        $clientId = $this->client($companyId);
        $typeId = $this->type($companyId, MembershipRules::create(MembershipKind::Duration, StartMode::FirstVisit, durationDays: 30));

        $id = $this->issuer()->issue($companyId, $clientId, $typeId, new DateTimeImmutable('2026-10-01 10:00:00'));

        $this->assertDatabaseHas('memberships', ['id' => $id, 'status' => 'pending', 'starts_at' => null, 'ends_at' => null]);
    }

    public function test_it_rejects_type_of_another_company(): void
    {
        $companyA = $this->company('A');
        $companyB = $this->company('B');
        $clientId = $this->client($companyA);
        $foreignType = $this->type($companyB, MembershipRules::create(MembershipKind::Single, StartMode::OnSale));

        $this->expectException(MembershipTypeNotFound::class);

        $this->issuer()->issue($companyA, $clientId, $foreignType, new DateTimeImmutable);
    }

    public function test_it_rejects_inactive_type(): void
    {
        $companyId = $this->company('Co');
        $clientId = $this->client($companyId);
        $typeId = $this->type($companyId, MembershipRules::create(MembershipKind::Single, StartMode::OnSale));
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $types->setActive($typeId, false);

        $this->expectException(MembershipTypeInactive::class);

        $this->issuer()->issue($companyId, $clientId, $typeId, new DateTimeImmutable);
    }

    public function test_it_rejects_client_of_another_company(): void
    {
        $companyA = $this->company('A');
        $companyB = $this->company('B');
        $foreignClient = $this->client($companyB);
        $typeId = $this->type($companyA, MembershipRules::create(MembershipKind::Single, StartMode::OnSale));

        $this->expectException(ClientNotAvailable::class);

        $this->issuer()->issue($companyA, $foreignClient, $typeId, new DateTimeImmutable);
    }
}
