<?php

declare(strict_types=1);

namespace Tests\Feature\Visits;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Visits\Application\CheckInByQr;
use App\Modules\Visits\Application\Commands\CheckInByQrCommand;
use App\Modules\Visits\Application\GenerateGymQr;
use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use App\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CheckInByQrUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $gymId;

    private User $clientUser;

    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->companyId = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'GymTrack Co'])->id;

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $this->gymId = $gyms->create($this->companyId, 'Main Gym', 'Dushanbe');

        $this->clientUser = User::factory()->create(['phone' => '+992900555666', 'is_phone_verified' => true]);

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $this->clientId = $clients->create($this->companyId, 'Rustam', '+992900555666', $this->clientUser->id);
    }

    private function issueMembership(
        int $visitsCount = 10,
        MembershipStatus $status = MembershipStatus::Active,
        int $dailyLimit = 1,
        ?int $fromMinute = null,
        ?int $toMinute = null,
    ): int {
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $this->companyId,
            'Package',
            new Money(20000, 'TJS'),
            MembershipRules::create(
                kind: MembershipKind::Visits,
                startMode: StartMode::OnSale,
                visitsCount: $visitsCount,
                dailyLimit: $dailyLimit,
                timeFromMinute: $fromMinute,
                timeToMinute: $toMinute,
            ),
        );

        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);

        return $repo->create($this->companyId, $this->clientId, $typeId, $status, $visitsCount, null, null);
    }

    private function generateQrToken(): string
    {
        /** @var GenerateGymQr $generator */
        $generator = $this->app->make(GenerateGymQr::class);

        return $generator->execute($this->companyId, $this->gymId)['payload']->toTokenString();
    }

    private function useCase(): CheckInByQr
    {
        return $this->app->make(CheckInByQr::class);
    }

    public function test_checks_in_by_qr_decrements_visits_writes_audit_and_is_idempotent(): void
    {
        $membershipId = $this->issueMembership(10);
        $qrToken = $this->generateQrToken();

        $first = $this->useCase()->execute(new CheckInByQrCommand(
            userId: $this->clientUser->id,
            qrToken: $qrToken,
            idempotencyKey: 'idem-key-1',
            device: 'iOS 18',
        ));

        $this->assertSame(9, $first->remainingVisits);
        $this->assertSame($membershipId, $first->visit->membershipId());

        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $this->companyId,
            'action' => 'VISIT_REGISTERED',
            'entity_type' => 'Visit',
            'entity_id' => (string) $first->visit->id(),
        ]);

        // Повтор с тем же Idempotency-Key возвращает тот же визит и не списывает второй раз
        $second = $this->useCase()->execute(new CheckInByQrCommand(
            userId: $this->clientUser->id,
            qrToken: $qrToken,
            idempotencyKey: 'idem-key-1',
            device: 'iOS 18',
        ));

        $this->assertSame($first->visit->id(), $second->visit->id());
        $this->assertSame(9, $second->remainingVisits);
    }

    public function test_second_check_in_same_day_without_idempotency_key_is_denied_even_after_last_visit(): void
    {
        $this->issueMembership(1);
        $qrToken = $this->generateQrToken();

        $first = $this->useCase()->execute(new CheckInByQrCommand(
            userId: $this->clientUser->id,
            qrToken: $qrToken,
            idempotencyKey: 'first-visit',
        ));
        $this->assertSame(0, $first->remainingVisits);

        try {
            $this->useCase()->execute(new CheckInByQrCommand(
                userId: $this->clientUser->id,
                qrToken: $qrToken,
                idempotencyKey: 'second-attempt',
            ));
            $this->fail('Expected CheckInDenied exception was not thrown.');
        } catch (CheckInDenied $e) {
            $this->assertSame(CheckInDenied::REASON_DAILY_LIMIT_EXCEEDED, $e->reason);
        }
    }

    public function test_denies_when_frozen_or_no_membership_or_invalid_qr(): void
    {
        $qrToken = $this->generateQrToken();

        // Без абонемента
        try {
            $this->useCase()->execute(new CheckInByQrCommand($this->clientUser->id, $qrToken));
            $this->fail('Expected CheckInDenied');
        } catch (CheckInDenied $e) {
            $this->assertSame(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP, $e->reason);
        }

        // Замороженный абонемент
        $this->issueMembership(5, MembershipStatus::Frozen);
        try {
            $this->useCase()->execute(new CheckInByQrCommand($this->clientUser->id, $qrToken));
            $this->fail('Expected CheckInDenied');
        } catch (CheckInDenied $e) {
            $this->assertSame(CheckInDenied::REASON_FROZEN, $e->reason);
        }

        // Недействительный QR
        $this->expectException(InvalidQrToken::class);
        $this->useCase()->execute(new CheckInByQrCommand($this->clientUser->id, "{$this->gymId}|100|tampered"));
    }
}
