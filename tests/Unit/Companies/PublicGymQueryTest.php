<?php

declare(strict_types=1);

namespace Tests\Unit\Companies;

use App\Models\User;
use App\Modules\Companies\Contracts\GeoBounds;
use App\Modules\Companies\Contracts\GeoRadius;
use App\Modules\Companies\Contracts\PublicGymFilter;
use App\Modules\Companies\Contracts\PublicGymQueryInterface;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymPhotoRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\ModerationStatus;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Shared\Application\CurrentCompanyInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class PublicGymQueryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $amenities
     * @param  array<string, mixed>  $workingHours
     * @return array{companyId: int, gymId: int, publicId: string}
     */
    private function createGymWithProfile(
        string $name,
        ModerationStatus $status = ModerationStatus::APPROVED,
        bool $isPublic = true,
        ?string $district = 'Сино',
        array $amenities = ['shower', 'parking'],
        ?int $companyId = null,
        array $workingHours = ['mon' => '08:00-22:00'],
        ?float $latitude = 38.5598,
        ?float $longitude = 68.787,
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
            workingHours: $workingHours,
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

    public function test_it_returns_only_approved_and_public_profiles_ignoring_current_company_scope(): void
    {
        $publishedA = $this->createGymWithProfile('Published A', ModerationStatus::APPROVED, true);
        $publishedB = $this->createGymWithProfile('Published B', ModerationStatus::APPROVED, true);
        $approvedPrivate = $this->createGymWithProfile('Approved Private', ModerationStatus::APPROVED, false);
        $draft = $this->createGymWithProfile('Draft Gym', ModerationStatus::DRAFT, false);
        $pending = $this->createGymWithProfile('Pending Gym', ModerationStatus::PENDING, false);
        $rejected = $this->createGymWithProfile('Rejected Gym', ModerationStatus::REJECTED, false);

        $owner = User::factory()->create();
        $noProfileCompanyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, 'No Profile Corp');
        $noProfileGymId = $this->app->make(GymRepositoryInterface::class)->create($noProfileCompanyId, 'No Profile Gym', null);
        $noProfilePublicId = (string) GymModel::query()->withoutGlobalScopes()->whereKey($noProfileGymId)->value('public_id');

        $this->app->make(CurrentCompanyInterface::class)->setId($draft['companyId']);

        $query = $this->app->make(PublicGymQueryInterface::class);
        $result = $query->search(new PublicGymFilter);

        $this->assertSame(2, $result['total']);
        $returnedIds = array_map(static fn ($item): string => $item->publicId, $result['items']);
        $expectedIds = [$publishedA['publicId'], $publishedB['publicId']];
        sort($expectedIds);
        $this->assertSame($expectedIds, $returnedIds);

        $this->assertNotNull($query->findByPublicId($publishedA['publicId']));
        $this->assertNotNull($query->findByPublicId($publishedB['publicId']));
        $this->assertNull($query->findByPublicId($approvedPrivate['publicId']));
        $this->assertNull($query->findByPublicId($draft['publicId']));
        $this->assertNull($query->findByPublicId($pending['publicId']));
        $this->assertNull($query->findByPublicId($rejected['publicId']));
        $this->assertNull($query->findByPublicId($noProfilePublicId));
        $this->assertNull($query->findByPublicId('   '));
        $this->assertNull($query->findByPublicId('01J00000000000000000000000'));
    }

    public function test_unpublishing_or_editing_immediately_hides_profile_from_search_and_card(): void
    {
        $gym = $this->createGymWithProfile('Dynamic Gym');
        $repo = $this->app->make(GymProfileRepositoryInterface::class);
        $query = $this->app->make(PublicGymQueryInterface::class);

        $this->assertNotNull($query->findByPublicId($gym['publicId']));

        $profile = $repo->findByGymId($gym['gymId']) ?? $this->fail('Profile not found');
        $profile->setPublication(false);
        $repo->save($profile);

        $this->assertNull($query->findByPublicId($gym['publicId']));
        $this->assertSame(0, $query->search(new PublicGymFilter)['total']);

        $profile->setPublication(true);
        $repo->save($profile);
        $this->assertNotNull($query->findByPublicId($gym['publicId']));

        $profile->markContentChanged();
        $repo->save($profile);
        $this->assertNull($query->findByPublicId($gym['publicId']));
        $this->assertSame(0, $query->search(new PublicGymFilter)['total']);
    }

    public function test_it_filters_by_name_substring_case_insensitively_and_escapes_special_characters(): void
    {
        $alpha = $this->createGymWithProfile('Олимп Фитнес 100%');
        $beta = $this->createGymWithProfile('Power_Gym \\ Dushanbe');
        $this->createGymWithProfile('Олимп Фитнес 1000');

        $query = $this->app->make(PublicGymQueryInterface::class);

        $byCyrillic = $query->search(new PublicGymFilter(query: '  олимп фитнес  '));
        $this->assertSame(2, $byCyrillic['total']);

        $byPercent = $query->search(new PublicGymFilter(query: '100%'));
        $this->assertSame(1, $byPercent['total']);
        $this->assertSame($alpha['publicId'], $byPercent['items'][0]->publicId);

        $byUnderscore = $query->search(new PublicGymFilter(query: 'power_gym'));
        $this->assertSame(1, $byUnderscore['total']);
        $this->assertSame($beta['publicId'], $byUnderscore['items'][0]->publicId);

        $byBackslash = $query->search(new PublicGymFilter(query: '\\'));
        $this->assertSame(1, $byBackslash['total']);
        $this->assertSame($beta['publicId'], $byBackslash['items'][0]->publicId);
    }

    public function test_it_filters_by_exact_district_case_insensitively_and_ignores_null_districts(): void
    {
        $sino = $this->createGymWithProfile('Gym Sino', district: 'Сино');
        $this->createGymWithProfile('Gym Somoni', district: 'Исмоили Сомони');
        $this->createGymWithProfile('Gym Without District', district: null);

        $query = $this->app->make(PublicGymQueryInterface::class);

        $matched = $query->search(new PublicGymFilter(district: '  сино  '));
        $this->assertSame(1, $matched['total']);
        $this->assertSame($sino['publicId'], $matched['items'][0]->publicId);

        $partial = $query->search(new PublicGymFilter(district: 'Син'));
        $this->assertSame(0, $partial['total']);

        $allWhenBlank = $query->search(new PublicGymFilter(district: '   '));
        $this->assertSame(3, $allWhenBlank['total']);
    }

    public function test_it_filters_by_all_required_amenities_and_company_ids(): void
    {
        $gym1 = $this->createGymWithProfile('Gym 1', amenities: ['shower', 'parking', 'sauna']);
        $gym2 = $this->createGymWithProfile('Gym 2', amenities: ['shower', 'parking']);
        $this->createGymWithProfile('Gym 3', amenities: ['shower']);

        $query = $this->app->make(PublicGymQueryInterface::class);

        $twoAmenities = $query->search(new PublicGymFilter(amenities: ['shower', 'parking', 'shower']));
        $this->assertSame(2, $twoAmenities['total']);

        $threeAmenities = $query->search(new PublicGymFilter(amenities: ['shower', 'parking', 'sauna']));
        $this->assertSame(1, $threeAmenities['total']);
        $this->assertSame($gym1['publicId'], $threeAmenities['items'][0]->publicId);

        $filteredByCompany = $query->search(new PublicGymFilter(
            amenities: ['shower', 'parking'],
            companyIds: [$gym2['companyId']],
        ));
        $this->assertSame(1, $filteredByCompany['total']);
        $this->assertSame($gym2['publicId'], $filteredByCompany['items'][0]->publicId);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $emptyCompanies = $query->search(new PublicGymFilter(companyIds: []));
        $this->assertSame(['items' => [], 'total' => 0], $emptyCompanies);
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_it_returns_ordered_photos_public_fields_pagination_and_avoids_n_plus_one_queries(): void
    {
        $photos = $this->app->make(GymPhotoRepositoryInterface::class);
        $g1 = $this->createGymWithProfile('Gym 1');
        $g2 = $this->createGymWithProfile('Gym 2');
        $g3 = $this->createGymWithProfile('Gym 3');

        $photos->create($g1['companyId'], $g1['gymId'], 'gyms/g1-second.jpg', 2);
        $photos->create($g1['companyId'], $g1['gymId'], 'gyms/g1-first.jpg', 1);
        $photos->create($g2['companyId'], $g2['gymId'], 'gyms/g2-only.jpg', 0);

        $query = $this->app->make(PublicGymQueryInterface::class);

        $card = $query->findByPublicId($g1['publicId']);
        $this->assertNotNull($card);
        $this->assertSame($g1['publicId'], $card->publicId);
        $this->assertSame($g1['gymId'], $card->gymId);
        $this->assertSame($g1['companyId'], $card->companyId);
        $this->assertSame('Gym 1', $card->name);
        $this->assertSame('Описание Gym 1', $card->description);
        $this->assertSame('ул. Рудаки, 10', $card->address);
        $this->assertSame('Сино', $card->district);
        $this->assertSame(38.5598, $card->latitude);
        $this->assertSame(68.787, $card->longitude);
        $this->assertSame('+992900000000', $card->phone);
        $this->assertSame(['mon' => '08:00-22:00'], $card->workingHours);
        $this->assertSame(['shower', 'parking'], $card->amenities);
        $this->assertSame([
            Storage::disk('public')->url('gyms/g1-first.jpg'),
            Storage::disk('public')->url('gyms/g1-second.jpg'),
        ], $card->photos);
        $this->assertArrayNotHasKey('qr_secret', get_object_vars($card));
        $this->assertArrayNotHasKey('rejectionReason', get_object_vars($card));

        $expectedOrder = [$g1['publicId'], $g2['publicId'], $g3['publicId']];
        sort($expectedOrder);

        $page1 = $query->search(new PublicGymFilter(page: 1, perPage: 2));
        $this->assertSame(3, $page1['total']);
        $this->assertCount(2, $page1['items']);
        $this->assertGreaterThan(0, $page1['items'][0]->gymId);
        $this->assertSame([$expectedOrder[0], $expectedOrder[1]], [
            $page1['items'][0]->publicId,
            $page1['items'][1]->publicId,
        ]);

        $page2 = $query->search(new PublicGymFilter(page: 2, perPage: 2));
        $this->assertSame(3, $page2['total']);
        $this->assertCount(1, $page2['items']);
        $this->assertSame($expectedOrder[2], $page2['items'][0]->publicId);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $allPage = $query->search(new PublicGymFilter(page: 1, perPage: 50));
        $queriesExecuted = count(DB::getQueryLog());

        $this->assertCount(3, $allPage['items']);
        $this->assertLessThanOrEqual(3, $queriesExecuted);
    }

    public function test_it_filters_by_map_bounds_inclusively_and_skips_profiles_without_coordinates(): void
    {
        // Душанбе (центр) и Худжанд (~200 км севернее); у третьего зала нет точки на карте.
        $dushanbe = $this->createGymWithProfile('Gym Dushanbe', latitude: 38.5598, longitude: 68.787);
        $khujand = $this->createGymWithProfile('Gym Khujand', latitude: 40.2833, longitude: 69.6222);
        $this->createGymWithProfile('Gym Without Point', latitude: null, longitude: null);

        $query = $this->app->make(PublicGymQueryInterface::class);

        $dushanbeArea = $query->search(new PublicGymFilter(bounds: new GeoBounds(38.4, 68.6, 38.7, 68.9)));
        $this->assertSame(1, $dushanbeArea['total']);
        $this->assertSame($dushanbe['publicId'], $dushanbeArea['items'][0]->publicId);

        $edgeInclusive = $query->search(new PublicGymFilter(bounds: new GeoBounds(40.2833, 69.6222, 41.0, 70.0)));
        $this->assertSame(1, $edgeInclusive['total']);
        $this->assertSame($khujand['publicId'], $edgeInclusive['items'][0]->publicId);

        $wholeCountry = $query->search(new PublicGymFilter(bounds: new GeoBounds(36.0, 67.0, 41.5, 75.5)));
        $this->assertSame(2, $wholeCountry['total']);

        $withoutGeo = $query->search(new PublicGymFilter);
        $this->assertSame(3, $withoutGeo['total']);
    }

    public function test_it_filters_by_haversine_radius_and_combines_with_bounds_and_other_filters(): void
    {
        // Точка в ~1.1 км севернее центра Душанбе и точка в ~11 км восточнее.
        $center = $this->createGymWithProfile('Gym Center', latitude: 38.5598, longitude: 68.787, amenities: ['shower']);
        $near = $this->createGymWithProfile('Gym Near', latitude: 38.5698, longitude: 68.787, amenities: ['pool']);
        $this->createGymWithProfile('Gym Far', latitude: 38.5598, longitude: 68.9134);
        $this->createGymWithProfile('Gym Without Point', latitude: null, longitude: null);

        $query = $this->app->make(PublicGymQueryInterface::class);

        $twoKm = $query->search(new PublicGymFilter(radius: new GeoRadius(38.5598, 68.787, 2.0)));
        $this->assertSame(2, $twoKm['total']);
        $expected = [$center['publicId'], $near['publicId']];
        sort($expected);
        $this->assertSame($expected, array_map(static fn ($item): string => $item->publicId, $twoKm['items']));

        $halfKm = $query->search(new PublicGymFilter(radius: new GeoRadius(38.5598, 68.787, 0.5)));
        $this->assertSame(1, $halfKm['total']);
        $this->assertSame($center['publicId'], $halfKm['items'][0]->publicId);

        $fifteenKm = $query->search(new PublicGymFilter(radius: new GeoRadius(38.5598, 68.787, 15.0)));
        $this->assertSame(3, $fifteenKm['total']);

        $radiusAndAmenity = $query->search(new PublicGymFilter(
            amenities: ['pool'],
            radius: new GeoRadius(38.5598, 68.787, 2.0),
        ));
        $this->assertSame(1, $radiusAndAmenity['total']);
        $this->assertSame($near['publicId'], $radiusAndAmenity['items'][0]->publicId);

        $radiusAndBounds = $query->search(new PublicGymFilter(
            bounds: new GeoBounds(38.565, 68.7, 38.6, 68.8),
            radius: new GeoRadius(38.5598, 68.787, 15.0),
        ));
        $this->assertSame(1, $radiusAndBounds['total']);
        $this->assertSame($near['publicId'], $radiusAndBounds['items'][0]->publicId);
    }

    public function test_geo_value_objects_reject_invalid_coordinates_and_radius(): void
    {
        $invalid = [
            static fn (): GeoBounds => new GeoBounds(-91.0, 0.0, 0.0, 0.0),
            static fn (): GeoBounds => new GeoBounds(0.0, 0.0, 0.0, 181.0),
            static fn (): GeoBounds => new GeoBounds(10.0, 0.0, 5.0, 1.0),
            static fn (): GeoBounds => new GeoBounds(0.0, 10.0, 1.0, 5.0),
            static fn (): GeoRadius => new GeoRadius(90.5, 0.0, 1.0),
            static fn (): GeoRadius => new GeoRadius(0.0, -180.5, 1.0),
            static fn (): GeoRadius => new GeoRadius(0.0, 0.0, 0.0),
            static fn (): GeoRadius => new GeoRadius(0.0, 0.0, 100.01),
        ];

        foreach ($invalid as $index => $factory) {
            try {
                $factory();
                $this->fail("Case {$index} must be rejected.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(100.0, (new GeoRadius(-90.0, 180.0, 100.0))->radiusKm);
    }
}
