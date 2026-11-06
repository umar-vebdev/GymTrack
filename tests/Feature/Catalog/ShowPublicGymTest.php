<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymPhotoRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\ModerationStatus;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Memberships\Infrastructure\Persistence\MembershipTypeModel;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ShowPublicGymTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $amenities
     * @return array{companyId: int, gymId: int, publicId: string}
     */
    private function createGymWithProfile(
        string $name,
        ModerationStatus $status = ModerationStatus::APPROVED,
        bool $isPublic = true,
        ?string $district = 'Сино',
        array $amenities = ['shower', 'parking'],
        ?int $companyId = null,
    ): array {
        if ($companyId === null) {
            $owner = User::factory()->create();
            $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$name}");
        }

        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, $name, 'ул. Рудаки, 1');
        $publicId = (string) GymModel::query()->withoutGlobalScopes()->whereKey($gymId)->value('public_id');

        $profile = GymProfile::draft($gymId);
        $profile->update(
            description: "Описание {$name}",
            address: 'ул. Рудаки, 10',
            latitude: 38.5598,
            longitude: 68.787,
            phone: '+992900000000',
            workingHours: ['mon' => '08:00-22:00'],
            amenities: $amenities,
            district: $district,
        );

        if ($status !== ModerationStatus::DRAFT) {
            $profile->submit();
        }
        if ($status === ModerationStatus::APPROVED) {
            $profile->approve(new DateTimeImmutable('2026-11-05 10:00:00'));
            $profile->setPublication($isPublic);
        } elseif ($status === ModerationStatus::REJECTED) {
            $profile->reject('Причина отклонения');
        }

        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        return [
            'companyId' => $companyId,
            'gymId' => $gymId,
            'publicId' => $publicId,
        ];
    }

    public function test_guest_gets_published_gym_card_with_photos_details_and_active_membership_types(): void
    {
        $photos = $this->app->make(GymPhotoRepositoryInterface::class);
        $typeRepo = $this->app->make(MembershipTypeRepositoryInterface::class);

        $gym = $this->createGymWithProfile('Олимп Центр');
        $otherGym = $this->createGymWithProfile('Чужой Зал');

        $photos->create($gym['companyId'], $gym['gymId'], 'gyms/card-2.jpg', 2);
        $photos->create($gym['companyId'], $gym['gymId'], 'gyms/card-1.jpg', 1);

        $visitsTypeId = $typeRepo->create(
            companyId: $gym['companyId'],
            name: '12 занятий (утро)',
            price: new Money(25000, 'TJS'),
            rules: MembershipRules::create(
                kind: MembershipKind::Visits,
                startMode: StartMode::FirstVisit,
                visitsCount: 12,
                validityDays: 60,
                dailyLimit: 1,
                timeFromMinute: 360,
                timeToMinute: 1020,
                freezeMaxDays: 14,
                freezeMaxTimes: 2,
            ),
        );

        $unlimitedTypeId = $typeRepo->create(
            companyId: $gym['companyId'],
            name: 'Безлимит на месяц',
            price: new Money(40000, 'TJS'),
            rules: MembershipRules::create(
                kind: MembershipKind::Unlimited,
                startMode: StartMode::OnSale,
                durationDays: 30,
                dailyLimit: 2,
                freezeMaxDays: 7,
                freezeMaxTimes: 1,
            ),
        );

        $inactiveTypeId = $typeRepo->create(
            companyId: $gym['companyId'],
            name: 'Архивный тариф',
            price: new Money(10000, 'TJS'),
            rules: MembershipRules::create(MembershipKind::Single, StartMode::OnSale),
        );
        $typeRepo->setActive($inactiveTypeId, false);

        $typeRepo->create(
            companyId: $otherGym['companyId'],
            name: 'Тариф другого зала',
            price: new Money(15000, 'TJS'),
            rules: MembershipRules::create(MembershipKind::Single, StartMode::OnSale),
        );

        $response = $this->getJson('/api/v1/public/gyms/'.$gym['publicId']);

        $response->assertOk()
            ->assertJsonPath('data.id', $gym['publicId'])
            ->assertJsonPath('data.name', 'Олимп Центр')
            ->assertJsonPath('data.description', 'Описание Олимп Центр')
            ->assertJsonPath('data.address', 'ул. Рудаки, 10')
            ->assertJsonPath('data.district', 'Сино')
            ->assertJsonPath('data.latitude', 38.5598)
            ->assertJsonPath('data.longitude', 68.787)
            ->assertJsonPath('data.phone', '+992900000000')
            ->assertJsonPath('data.working_hours', ['mon' => '08:00-22:00'])
            ->assertJsonPath('data.amenities', ['shower', 'parking'])
            ->assertJsonPath('data.photos', [
                Storage::disk('public')->url('gyms/card-1.jpg'),
                Storage::disk('public')->url('gyms/card-2.jpg'),
            ])
            ->assertJsonCount(2, 'data.membership_types');

        /** @var array<string, mixed> $data */
        $data = $response->json('data');
        $this->assertArrayNotHasKey('gym_id', $data);
        $this->assertArrayNotHasKey('company_id', $data);
        $this->assertArrayNotHasKey('qr_secret', $data);
        $this->assertArrayNotHasKey('moderation_status', $data);
        $this->assertArrayNotHasKey('rejection_reason', $data);
        $this->assertArrayNotHasKey('is_public', $data);

        $visitsPublicId = (string) MembershipTypeModel::query()->withoutGlobalScopes()->whereKey($visitsTypeId)->value('public_id');
        $unlimitedPublicId = (string) MembershipTypeModel::query()->withoutGlobalScopes()->whereKey($unlimitedTypeId)->value('public_id');
        $expectedTypeIds = [$visitsPublicId, $unlimitedPublicId];
        sort($expectedTypeIds);

        /** @var list<array<string, mixed>> $types */
        $types = $data['membership_types'];
        $this->assertSame($expectedTypeIds, [$types[0]['id'], $types[1]['id']]);

        $byId = [];
        foreach ($types as $type) {
            $byId[(string) $type['id']] = $type;
            $this->assertArrayNotHasKey('company_id', $type);
            $this->assertArrayNotHasKey('is_active', $type);
        }

        $visitsItem = $byId[$visitsPublicId];
        $this->assertSame('12 занятий (утро)', $visitsItem['name']);
        $this->assertSame('visits', $visitsItem['kind']);
        $this->assertSame('first_visit', $visitsItem['start_mode']);
        $this->assertSame(25000, $visitsItem['price_minor']);
        $this->assertSame('TJS', $visitsItem['currency']);
        $this->assertSame(12, $visitsItem['visits_count']);
        $this->assertNull($visitsItem['duration_days']);
        $this->assertSame(60, $visitsItem['validity_days']);
        $this->assertSame(1, $visitsItem['daily_limit']);
        $this->assertSame(360, $visitsItem['time_from_minute']);
        $this->assertSame(1020, $visitsItem['time_to_minute']);
        $this->assertSame(14, $visitsItem['freeze_max_days']);
        $this->assertSame(2, $visitsItem['freeze_max_times']);
    }

    public function test_it_returns_404_for_hidden_non_approved_or_unknown_public_id(): void
    {
        $approvedHidden = $this->createGymWithProfile('Approved Hidden', ModerationStatus::APPROVED, false);
        $draft = $this->createGymWithProfile('Draft Gym', ModerationStatus::DRAFT, false);
        $pending = $this->createGymWithProfile('Pending Gym', ModerationStatus::PENDING, false);
        $rejected = $this->createGymWithProfile('Rejected Gym', ModerationStatus::REJECTED, false);

        foreach ([$approvedHidden['publicId'], $draft['publicId'], $pending['publicId'], $rejected['publicId'], '01J00000000000000000000000'] as $id) {
            $this->getJson('/api/v1/public/gyms/'.$id)
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'NOT_FOUND');
        }
    }

    public function test_unpublishing_or_editing_profile_immediately_returns_404(): void
    {
        $gym = $this->createGymWithProfile('Dynamic Endpoint Gym');
        $profileRepo = $this->app->make(GymProfileRepositoryInterface::class);

        $this->getJson('/api/v1/public/gyms/'.$gym['publicId'])->assertOk();

        $profile = $profileRepo->findByGymId($gym['gymId']) ?? $this->fail('Profile not found');
        $profile->setPublication(false);
        $profileRepo->save($profile);

        $this->getJson('/api/v1/public/gyms/'.$gym['publicId'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $profile->setPublication(true);
        $profileRepo->save($profile);
        $this->getJson('/api/v1/public/gyms/'.$gym['publicId'])->assertOk();

        $profile->markContentChanged();
        $profileRepo->save($profile);
        $this->getJson('/api/v1/public/gyms/'.$gym['publicId'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
