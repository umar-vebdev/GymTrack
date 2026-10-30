<?php

declare(strict_types=1);

namespace Tests\Unit\Visits;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use App\Modules\Visits\Domain\VisitStatus;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VisitRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_counts_and_cancels_visit_within_24_hours(): void
    {
        $owner = User::factory()->create();
        $companyId = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Gym Co'])->id;

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($companyId, 'Main Gym', 'Dushanbe');

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($companyId, 'Rustam', '+992900111222');

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $companyId,
            '12 Visits',
            new Money(20000, 'TJS'),
            MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 12, validityDays: 60)
        );

        /** @var MembershipIssuerInterface $issuer */
        $issuer = $this->app->make(MembershipIssuerInterface::class);
        $visitedAt = new DateTimeImmutable('2026-10-30 10:00:00');
        $membershipId = $issuer->issue($companyId, $clientId, $typeId, $visitedAt, $owner->id);

        /** @var VisitRepositoryInterface $visits */
        $visits = $this->app->make(VisitRepositoryInterface::class);

        $visit = $visits->create(
            companyId: $companyId,
            gymId: $gymId,
            clientId: $clientId,
            membershipId: $membershipId,
            method: VisitMethod::Qr,
            visitedAt: $visitedAt,
            registeredByUserId: $owner->id,
            device: 'iOS 18',
            idempotencyKey: 'idem-visit-1',
        );

        $this->assertSame(VisitStatus::Completed, $visit->status());
        $this->assertSame(1, $visits->countCompletedForClientInGymOnDate($clientId, $gymId, '2026-10-30'));
        $this->assertNotNull($visits->findByIdempotencyKey($companyId, 'idem-visit-1'));

        // Попытка отменить спустя более 24 часов отклоняется (Бизнес-ТЗ 5.4)
        try {
            $visit->cancel('Слишком поздно', $owner->id, new DateTimeImmutable('2026-10-31 10:00:01'));
            $this->fail('Ожидалось исключение при отмене позже 24 часов');
        } catch (DomainException $e) {
            $this->assertStringContainsString('24 часов', $e->getMessage());
        }

        // Отмена в пределах 24 часов проходит успешно
        $visit->cancel('Ошибочное списание', $owner->id, new DateTimeImmutable('2026-10-30 12:00:00'));
        $visits->save($visit);

        $reloaded = $visits->findById($visit->id());
        $this->assertNotNull($reloaded);
        $this->assertSame(VisitStatus::Cancelled, $reloaded->status());
        $this->assertSame('Ошибочное списание', $reloaded->cancelReason());
        $this->assertSame(0, $visits->countCompletedForClientInGymOnDate($clientId, $gymId, '2026-10-30'));
    }
}
