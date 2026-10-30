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
use App\Modules\Visits\Application\GenerateGymQr;
use App\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CheckInByQrEndpointTest extends TestCase
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

        $this->clientUser = User::factory()->create(['phone' => '+992900777888', 'is_phone_verified' => true]);

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $this->clientId = $clients->create($this->companyId, 'Farid', '+992900777888', $this->clientUser->id);
    }

    private function issueMembership(
        MembershipKind $kind = MembershipKind::Visits,
        ?int $visitsCount = 10,
        ?int $durationDays = null,
        MembershipStatus $status = MembershipStatus::Active,
        int $dailyLimit = 1,
    ): int {
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $this->companyId,
            'Plan',
            new Money(25000, 'TJS'),
            MembershipRules::create(
                kind: $kind,
                startMode: StartMode::OnSale,
                visitsCount: $visitsCount,
                durationDays: $durationDays,
                dailyLimit: $dailyLimit,
            ),
        );

        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);

        return $repo->create($this->companyId, $this->clientId, $typeId, $status, $visitsCount, null, null);
    }

    private function qrToken(): string
    {
        /** @var GenerateGymQr $generator */
        $generator = $this->app->make(GenerateGymQr::class);

        return $generator->execute($this->companyId, $this->gymId)['payload']->toTokenString();
    }

    public function test_client_checks_in_by_qr_is_idempotent_and_blocks_duplicate_same_day(): void
    {
        $this->issueMembership(visitsCount: 10);
        $token = $this->qrToken();

        Sanctum::actingAs($this->clientUser);

        // 12.1 Отметка по абонементу: было 10, стало 9
        $first = $this->postJson('/api/v1/client/visits/check-in', [
            'token' => $token,
            'device' => 'Android 15',
        ], [
            'Idempotency-Key' => 'client-click-1',
        ]);

        $first->assertCreated()
            ->assertJsonPath('data.gym_id', $this->gymId)
            ->assertJsonPath('data.client_id', $this->clientId)
            ->assertJsonPath('data.method', 'qr')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.remaining_visits', 9);

        $visitId = $first->json('data.id');

        // Двойное нажатие «Да» с тем же Idempotency-Key возвращает тот же визит, остаток 9
        $duplicate = $this->postJson('/api/v1/client/visits/check-in', [
            'token' => $token,
        ], [
            'Idempotency-Key' => 'client-click-1',
        ]);

        $duplicate->assertCreated()
            ->assertJsonPath('data.id', $visitId)
            ->assertJsonPath('data.remaining_visits', 9);

        // 12.2 Повторная отметка сегодня без ключа: не списывает занятие, код DAILY_LIMIT_EXCEEDED
        $secondToday = $this->postJson('/api/v1/client/visits/check-in', [
            'token' => $token,
        ]);

        $secondToday->assertStatus(422)
            ->assertJsonPath('error.code', 'DAILY_LIMIT_EXCEEDED');
    }

    public function test_returns_proper_errors_for_invalid_qr_frozen_and_missing_membership(): void
    {
        Sanctum::actingAs($this->clientUser);
        $token = $this->qrToken();

        // 12.3 Нет абонемента
        $this->postJson('/api/v1/client/visits/check-in', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NO_ACTIVE_MEMBERSHIP');

        // Замороженный абонемент
        $this->issueMembership(visitsCount: 5, status: MembershipStatus::Frozen);
        $this->postJson('/api/v1/client/visits/check-in', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MEMBERSHIP_FROZEN');

        // Битый/чужой QR
        $this->postJson('/api/v1/client/visits/check-in', ['token' => "{$this->gymId}|999|forged"])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_QR_TOKEN');
    }

    public function test_unlimited_membership_allows_multiple_entries_when_daily_limit_permits(): void
    {
        // 12.4 Безлимит с дневным лимитом 2
        $this->issueMembership(
            kind: MembershipKind::Unlimited,
            visitsCount: null,
            durationDays: 30,
            dailyLimit: 2,
        );
        $token = $this->qrToken();

        Sanctum::actingAs($this->clientUser);

        $this->postJson('/api/v1/client/visits/check-in', ['token' => $token])
            ->assertCreated()
            ->assertJsonPath('data.remaining_visits', null);

        $this->postJson('/api/v1/client/visits/check-in', ['token' => $token])
            ->assertCreated()
            ->assertJsonPath('data.remaining_visits', null);

        $this->postJson('/api/v1/client/visits/check-in', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DAILY_LIMIT_EXCEEDED');
    }
}

