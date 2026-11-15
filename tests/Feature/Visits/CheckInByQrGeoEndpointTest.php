<?php

declare(strict_types=1);

namespace Tests\Feature\Visits;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
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

final class CheckInByQrGeoEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $companyId;

    private int $gymId;

    private User $clientUser;

    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->companyId = CompanyModel::create(['owner_id' => $this->owner->id, 'name' => 'Geo Club'])->id;

        /** @var StaffMemberRepositoryInterface $staff */
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $staff->create($this->owner->id, $this->companyId, Role::OWNER);

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $this->gymId = $gyms->create($this->companyId, 'Main Geo Gym', 'ул. Рудаки, 10');

        $profile = GymProfile::draft($this->gymId);
        $profile->update(
            description: 'Зал в центре',
            address: 'ул. Рудаки, 10',
            latitude: 38.5598,
            longitude: 68.7870,
            phone: '+992900000111',
            workingHours: ['mon' => '08:00-22:00'],
            amenities: ['shower'],
        );
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        $this->clientUser = User::factory()->create(['phone' => '+992900777111', 'is_phone_verified' => true]);

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $this->clientId = $clients->create($this->companyId, 'Али', '+992900777111', $this->clientUser->id);

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $this->companyId,
            'Безлимит',
            new Money(20000, 'TJS'),
            MembershipRules::create(
                kind: MembershipKind::Unlimited,
                startMode: StartMode::OnSale,
                durationDays: 30,
                dailyLimit: 5,
            ),
        );

        /** @var MembershipRepositoryInterface $memberships */
        $memberships = $this->app->make(MembershipRepositoryInterface::class);
        $memberships->create($this->companyId, $this->clientId, $typeId, MembershipStatus::Active, null, null, null);
    }

    private function qrToken(): string
    {
        /** @var GenerateGymQr $generator */
        $generator = $this->app->make(GenerateGymQr::class);

        return $generator->execute($this->companyId, $this->gymId)['payload']->toTokenString();
    }

    public function test_owner_can_configure_geofence_and_check_in_enforces_coordinates(): void
    {
        $token = $this->qrToken();

        // Пока геопроверка выключена, чекин проходит без координат
        Sanctum::actingAs($this->clientUser);
        $this->postJson('/api/v1/client/visits/check-in', [
            'token' => $token,
        ])->assertCreated();

        // Владелец включает геопроверку с радиусом 150 м
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/business/gyms/{$this->gymId}", [
            'name' => 'Main Geo Gym',
            'address' => 'ул. Рудаки, 10',
            'geo_check_enabled' => true,
            'geo_check_radius_meters' => 150,
        ])->assertOk()
            ->assertJsonPath('data.geo_check_enabled', true)
            ->assertJsonPath('data.geo_check_radius_meters', 150);

        // Валидация парных координат и диапазона радиуса
        $this->patchJson("/api/v1/business/gyms/{$this->gymId}", [
            'name' => 'Main Geo Gym',
            'geo_check_radius_meters' => 10,
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        Sanctum::actingAs($this->clientUser);

        // Не передана только долгота -> 422 VALIDATION_FAILED
        $this->postJson('/api/v1/client/visits/check-in', [
            'token' => $token,
            'latitude' => 38.5598,
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Без координат при включённой геопроверке -> 422 GEO_LOCATION_REQUIRED
        $this->postJson('/api/v1/client/visits/check-in', [
            'token' => $token,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'GEO_LOCATION_REQUIRED');

        // Далеко от зала (~1.1 км) -> 422 OUTSIDE_GYM_GEOFENCE
        $this->postJson('/api/v1/client/visits/check-in', [
            'token' => $token,
            'latitude' => 38.5700,
            'longitude' => 68.7870,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'OUTSIDE_GYM_GEOFENCE');

        // В пределах радиуса (~55 м) -> 201 Created
        $this->postJson('/api/v1/client/visits/check-in', [
            'token' => $token,
            'latitude' => 38.5603,
            'longitude' => 68.7870,
        ])->assertCreated()
            ->assertJsonPath('data.gym_id', $this->gymId);
    }
}

