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
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ListPublicGymsTest extends TestCase
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
        float $latitude = 38.5598,
        float $longitude = 68.787,
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
            latitude: $latitude,
            longitude: $longitude,
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

    private function createMembershipType(
        int $companyId,
        string $name,
        int $priceMinor,
        string $currency = 'TJS',
        bool $isActive = true,
    ): void {
        $repo = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $repo->create(
            companyId: $companyId,
            name: $name,
            price: new Money($priceMinor, $currency),
            rules: MembershipRules::create(
                kind: MembershipKind::Visits,
                startMode: StartMode::OnSale,
                visitsCount: 12,
                validityDays: 30,
            ),
        );

        if (!$isActive) {
            $repo->setActive($typeId, false);
        }
    }

    public function test_guest_lists_only_published_gyms_without_private_fields(): void
    {
        $photos = $this->app->make(GymPhotoRepositoryInterface::class);
        $pub1 = $this->createGymWithProfile('Олимп 1');
        $pub2 = $this->createGymWithProfile('Олимп 2');
        $this->createGymWithProfile('Одобрен но скрыт', ModerationStatus::APPROVED, false);
        $this->createGymWithProfile('Черновик', ModerationStatus::DRAFT, false);
        $this->createGymWithProfile('На модерации', ModerationStatus::PENDING, false);
        $this->createGymWithProfile('Отклонён', ModerationStatus::REJECTED, false);

        $photos->create($pub1['companyId'], $pub1['gymId'], 'gyms/p1-b.jpg', 2);
        $photos->create($pub1['companyId'], $pub1['gymId'], 'gyms/p1-a.jpg', 1);

        $response = $this->getJson('/api/v1/public/gyms');

        $response->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');

        $expectedOrder = [$pub1['publicId'], $pub2['publicId']];
        sort($expectedOrder);

        /** @var list<array<string, mixed>> $items */
        $items = $response->json('data');
        $this->assertSame([$expectedOrder[0], $expectedOrder[1]], [$items[0]['id'], $items[1]['id']]);

        $byId = [];
        foreach ($items as $item) {
            $byId[(string) $item['id']] = $item;
            $this->assertArrayNotHasKey('gym_id', $item);
            $this->assertArrayNotHasKey('company_id', $item);
            $this->assertArrayNotHasKey('qr_secret', $item);
            $this->assertArrayNotHasKey('moderation_status', $item);
            $this->assertArrayNotHasKey('rejection_reason', $item);
            $this->assertArrayNotHasKey('is_public', $item);
        }

        $firstPub = $byId[$pub1['publicId']];
        $this->assertSame('Олимп 1', $firstPub['name']);
        $this->assertSame('Описание Олимп 1', $firstPub['description']);
        $this->assertSame('ул. Рудаки, 10', $firstPub['address']);
        $this->assertSame('Сино', $firstPub['district']);
        $this->assertSame(38.5598, $firstPub['latitude']);
        $this->assertSame(68.787, $firstPub['longitude']);
        $this->assertSame('+992900000000', $firstPub['phone']);
        $this->assertSame(['mon' => '08:00-22:00'], $firstPub['working_hours']);
        $this->assertSame(['shower', 'parking'], $firstPub['amenities']);
        $this->assertSame([
            Storage::disk('public')->url('gyms/p1-a.jpg'),
            Storage::disk('public')->url('gyms/p1-b.jpg'),
        ], $firstPub['photos']);
    }

    public function test_it_applies_search_district_amenities_price_and_pagination_filters(): void
    {
        $g1 = $this->createGymWithProfile(
            name: 'Олимп 100% Сино',
            district: 'Сино',
            amenities: ['shower', 'parking', 'sauna'],
        );
        $this->createMembershipType($g1['companyId'], 'Стандарт', 25000, 'TJS');

        $g2 = $this->createGymWithProfile(
            name: 'Олимп 100% Премиум',
            district: 'Сино',
            amenities: ['shower', 'parking', 'sauna'],
        );
        $this->createMembershipType($g2['companyId'], 'Премиум', 60000, 'TJS');

        $g3 = $this->createGymWithProfile(
            name: 'Олимп 1000 Сино',
            district: 'Сино',
            amenities: ['shower', 'parking', 'sauna'],
        );
        $this->createMembershipType($g3['companyId'], 'Стандарт', 25000, 'TJS');

        $filtered = $this->getJson('/api/v1/public/gyms?'.http_build_query([
            'q' => '100%',
            'district' => 'сино',
            'amenities' => ['shower', 'sauna'],
            'price_min' => 20000,
            'price_max' => 30000,
            'currency' => 'tjs',
        ]));

        $filtered->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $g1['publicId']);

        $emptyByPrice = $this->getJson('/api/v1/public/gyms?price_min=1000&price_max=5000&currency=TJS');
        $emptyByPrice->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');

        $page1 = $this->getJson('/api/v1/public/gyms?page=1&per_page=2');
        $page1->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');

        $page2 = $this->getJson('/api/v1/public/gyms?page=2&per_page=2');
        $page2->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(1, 'data');
    }

    public function test_invalid_filter_and_pagination_parameters_return_422(): void
    {
        $this->getJson('/api/v1/public/gyms?price_min=10000')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['currency']]]);

        $this->getJson('/api/v1/public/gyms?price_max=20000')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['currency']]]);

        $this->getJson('/api/v1/public/gyms?price_min=30000&price_max=10000&currency=TJS')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['price_max']]]);

        $this->getJson('/api/v1/public/gyms?price_min=-1&currency=TJS')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['price_min']]]);

        $this->getJson('/api/v1/public/gyms?currency=TJ')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['currency']]]);

        $this->getJson('/api/v1/public/gyms?amenities=shower')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['amenities']]]);

        $this->getJson('/api/v1/public/gyms?page=0&per_page=101')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['page', 'per_page']]]);

        $this->getJson('/api/v1/public/gyms?district='.str_repeat('a', 101))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['district']]]);
    }

    public function test_it_filters_by_radius_and_map_bounds_together_with_other_filters(): void
    {
        $center = $this->createGymWithProfile('Центр', amenities: ['pool']);
        $near = $this->createGymWithProfile('Рядом', amenities: ['sauna'], latitude: 38.5698);
        $this->createGymWithProfile('Далеко', amenities: ['pool'], latitude: 40.2833, longitude: 69.6222);

        $radius = $this->getJson('/api/v1/public/gyms?lat=38.5598&lng=68.787&radius_km=2');
        $radius->assertOk()->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/public/gyms?lat=38.5598&lng=68.787&radius_km=0.5')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $center['publicId']);

        $this->getJson('/api/v1/public/gyms?lat=38.5598&lng=68.787&radius_km=2&amenities[]=sauna')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $near['publicId']);

        $this->getJson('/api/v1/public/gyms?sw_lat=38.5&sw_lng=68.7&ne_lat=38.7&ne_lng=68.9')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/public/gyms?sw_lat=38.565&sw_lng=68.7&ne_lat=38.6&ne_lng=68.9&lat=38.5598&lng=68.787&radius_km=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $near['publicId']);
    }

    public function test_incomplete_or_invalid_geo_parameters_return_422(): void
    {
        $cases = [
            'lat=38.5&lng=68.7' => ['radius_km'],
            'lat=38.5&radius_km=3' => ['lng'],
            'radius_km=3' => ['lat', 'lng'],
            'lat=91&lng=68.7&radius_km=3' => ['lat'],
            'lat=38.5&lng=181&radius_km=3' => ['lng'],
            'lat=38.5&lng=68.7&radius_km=0' => ['radius_km'],
            'lat=38.5&lng=68.7&radius_km=100.5' => ['radius_km'],
            'lat=abc&lng=68.7&radius_km=3' => ['lat'],
            'sw_lat=38&sw_lng=68&ne_lat=39' => ['ne_lng'],
            'sw_lat=39&sw_lng=68&ne_lat=38&ne_lng=69' => ['ne_lat'],
            'sw_lat=38&sw_lng=69&ne_lat=39&ne_lng=68' => ['ne_lng'],
            'sw_lat=-91&sw_lng=68&ne_lat=39&ne_lng=69' => ['sw_lat'],
        ];

        foreach ($cases as $queryString => $fields) {
            $this->getJson('/api/v1/public/gyms?'.$queryString)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonStructure(['error' => ['details' => $fields]]);
        }
    }
}
