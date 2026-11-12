<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Application\SearchGyms;
use App\Modules\Catalog\Application\SearchGymsQuery;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Companies\Contracts\PublicGymFilter;
use App\Modules\Companies\Contracts\PublicGymProfile;
use App\Modules\Companies\Contracts\PublicGymQueryInterface;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\ModerationStatus;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Modules\Memberships\Contracts\MembershipCatalogQueryInterface;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class SearchGymsTest extends TestCase
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

    private function createMembershipType(
        int $companyId,
        string $name,
        int $priceMinor,
        string $currency = 'TJS',
        bool $isActive = true,
    ): int {
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

        return $typeId;
    }

    public function test_it_registers_catalog_provider_and_searches_published_gyms_without_price_filter(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(CatalogServiceProvider::class));

        $matched = $this->createGymWithProfile(
            name: 'Олимп Сино',
            district: 'Сино',
            amenities: ['shower', 'parking', 'sauna'],
        );
        $this->createGymWithProfile(
            name: 'Олимп Сомони',
            district: 'Исмоили Сомони',
            amenities: ['shower', 'parking', 'sauna'],
        );
        $this->createGymWithProfile(
            name: 'Олимп Скрытый',
            status: ModerationStatus::APPROVED,
            isPublic: false,
            district: 'Сино',
            amenities: ['shower', 'parking', 'sauna'],
        );

        $useCase = $this->app->make(SearchGyms::class);
        $result = $useCase->execute(new SearchGymsQuery(
            query: '  олимп  ',
            district: '  сино ',
            amenities: ['shower', 'sauna'],
        ));

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame($matched['publicId'], $result['items'][0]->publicId);
    }

    public function test_it_combines_membership_price_filter_with_public_gym_search(): void
    {
        $affordableSino = $this->createGymWithProfile(
            name: 'Fit Sino Affordable',
            district: 'Сино',
            amenities: ['shower', 'parking'],
        );
        $this->createMembershipType($affordableSino['companyId'], '12 занятий', 20000, 'TJS');

        $expensiveSino = $this->createGymWithProfile(
            name: 'Fit Sino Expensive',
            district: 'Сино',
            amenities: ['shower', 'parking'],
        );
        $this->createMembershipType($expensiveSino['companyId'], 'VIP', 60000, 'TJS');

        $inactiveCheapSino = $this->createGymWithProfile(
            name: 'Fit Sino Inactive Tariff',
            district: 'Сино',
            amenities: ['shower', 'parking'],
        );
        $this->createMembershipType($inactiveCheapSino['companyId'], 'Старый тариф', 15000, 'TJS', isActive: false);

        $usdSino = $this->createGymWithProfile(
            name: 'Fit Sino USD',
            district: 'Сино',
            amenities: ['shower', 'parking'],
        );
        $this->createMembershipType($usdSino['companyId'], 'USD Pass', 20000, 'USD');

        $affordableOtherDistrict = $this->createGymWithProfile(
            name: 'Fit Somoni Affordable',
            district: 'Исмоили Сомони',
            amenities: ['shower', 'parking'],
        );
        $this->createMembershipType($affordableOtherDistrict['companyId'], '12 занятий', 20000, 'TJS');

        $useCase = $this->app->make(SearchGyms::class);

        $result = $useCase->execute(new SearchGymsQuery(
            district: 'Сино',
            amenities: ['shower'],
            priceMin: 15000,
            priceMax: 30000,
            currency: 'tjs',
        ));

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame($affordableSino['publicId'], $result['items'][0]->publicId);
    }

    public function test_empty_price_match_returns_empty_result_without_leaking_all_gyms(): void
    {
        $gym = $this->createGymWithProfile('Fit Public');
        $this->createMembershipType($gym['companyId'], 'Базовый', 30000, 'TJS');

        $useCase = $this->app->make(SearchGyms::class);

        $noMatchResult = $useCase->execute(new SearchGymsQuery(
            priceMin: 5000,
            priceMax: 10000,
            currency: 'TJS',
        ));

        $this->assertSame(['items' => [], 'total' => 0], $noMatchResult);

        $spyGyms = new class implements PublicGymQueryInterface
        {
            public bool $searchCalled = false;

            public function search(PublicGymFilter $filter): array
            {
                $this->searchCalled = true;

                return ['items' => [], 'total' => 0];
            }

            public function findByPublicId(string $publicId): ?PublicGymProfile
            {
                return null;
            }
        };

        $emptyMemberships = new class implements MembershipCatalogQueryInterface
        {
            public function listActiveForCompany(int $companyId): array
            {
                return [];
            }

            public function findCompanyIdsByPriceRange(string $currency, ?int $priceMin = null, ?int $priceMax = null): array
            {
                return [];
            }
        };

        $isolatedUseCase = new SearchGyms($spyGyms, $emptyMemberships);
        $isolatedResult = $isolatedUseCase->execute(new SearchGymsQuery(
            priceMin: 1000,
            currency: 'TJS',
        ));

        $this->assertSame(['items' => [], 'total' => 0], $isolatedResult);
        $this->assertFalse($spyGyms->searchCalled);
    }

    public function test_search_gyms_query_validates_currency_and_non_negative_bounds(): void
    {
        try {
            new SearchGymsQuery(priceMin: 10000);
            $this->fail('Ожидалось исключение при отсутствии валюты для priceMin.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('валют', $e->getMessage());
        }

        try {
            new SearchGymsQuery(priceMax: 20000, currency: '   ');
            $this->fail('Ожидалось исключение при пустой валюте для priceMax.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('валют', $e->getMessage());
        }

        try {
            new SearchGymsQuery(priceMin: -1, currency: 'TJS');
            $this->fail('Ожидалось исключение при отрицательной минимальной цене.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('отрицательной', $e->getMessage());
        }

        try {
            new SearchGymsQuery(priceMax: -5, currency: 'TJS');
            $this->fail('Ожидалось исключение при отрицательной максимальной цене.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('отрицательной', $e->getMessage());
        }
    }

    public function test_catalog_module_depends_only_on_allowed_contracts(): void
    {
        $catalogDir = base_path('app/Modules/Catalog');
        $this->assertDirectoryExists($catalogDir);

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($catalogDir));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all('/^\s*use\s+(App\\\\Modules\\\\[^;]+);/m', $contents, $matches);

            foreach ($matches[1] as $importedClass) {
                $isOwnModule = str_starts_with($importedClass, 'App\\Modules\\Catalog\\');
                $isAllowedContract = str_starts_with($importedClass, 'App\\Modules\\Companies\\Contracts\\')
                    || str_starts_with($importedClass, 'App\\Modules\\Memberships\\Contracts\\')
                    || str_starts_with($importedClass, 'App\\Modules\\Schedule\\Contracts\\');

                $this->assertTrue(
                    $isOwnModule || $isAllowedContract,
                    sprintf('Недопустимая зависимость модуля Catalog от [%s] в файле [%s].', $importedClass, $file->getPathname()),
                );
            }
        }
    }
}
