<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Application\GetPublicGym;
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
use App\Modules\Memberships\Infrastructure\Persistence\MembershipTypeModel;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GetPublicGymTest extends TestCase
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

    public function test_it_returns_published_gym_card_with_active_company_membership_types(): void
    {
        $gym = $this->createGymWithProfile('Олимп Центр');
        $otherGym = $this->createGymWithProfile('Чужой Зал');

        $activeTypeA = $this->createMembershipType($gym['companyId'], '12 занятий', 25000, 'TJS', true);
        $activeTypeB = $this->createMembershipType($gym['companyId'], 'Безлимит', 40000, 'TJS', true);
        $this->createMembershipType($gym['companyId'], 'Архивный', 10000, 'TJS', false);
        $this->createMembershipType($otherGym['companyId'], 'Чужой тариф', 15000, 'TJS', true);

        $useCase = $this->app->make(GetPublicGym::class);
        $result = $useCase->execute($gym['publicId']);

        $this->assertNotNull($result);
        $this->assertSame($gym['publicId'], $result->gym->publicId);
        $this->assertSame('Олимп Центр', $result->gym->name);
        $this->assertCount(2, $result->membershipTypes);

        $pubA = (string) MembershipTypeModel::query()->withoutGlobalScopes()->whereKey($activeTypeA)->value('public_id');
        $pubB = (string) MembershipTypeModel::query()->withoutGlobalScopes()->whereKey($activeTypeB)->value('public_id');
        $expectedIds = [$pubA, $pubB];
        sort($expectedIds);

        $this->assertSame($expectedIds, [
            $result->membershipTypes[0]->publicId,
            $result->membershipTypes[1]->publicId,
        ]);
    }

    public function test_it_returns_null_and_does_not_load_memberships_for_hidden_or_unknown_gym(): void
    {
        $approvedHidden = $this->createGymWithProfile('Approved Hidden', ModerationStatus::APPROVED, false);
        $draft = $this->createGymWithProfile('Draft Gym', ModerationStatus::DRAFT, false);
        $pending = $this->createGymWithProfile('Pending Gym', ModerationStatus::PENDING, false);
        $rejected = $this->createGymWithProfile('Rejected Gym', ModerationStatus::REJECTED, false);

        $this->createMembershipType($approvedHidden['companyId'], 'Тариф скрытого зала', 20000);

        $spyMemberships = new class implements MembershipCatalogQueryInterface
        {
            public bool $listCalled = false;

            public function listActiveForCompany(int $companyId): array
            {
                $this->listCalled = true;

                return [];
            }

            public function findCompanyIdsByPriceRange(string $currency, ?int $priceMin = null, ?int $priceMax = null): array
            {
                return [];
            }
        };

        $useCase = new GetPublicGym(
            $this->app->make(PublicGymQueryInterface::class),
            $spyMemberships,
        );

        $this->assertNull($useCase->execute($approvedHidden['publicId']));
        $this->assertNull($useCase->execute($draft['publicId']));
        $this->assertNull($useCase->execute($pending['publicId']));
        $this->assertNull($useCase->execute($rejected['publicId']));
        $this->assertNull($useCase->execute('01J00000000000000000000000'));
        $this->assertFalse($spyMemberships->listCalled);
    }

    public function test_unpublishing_or_editing_profile_immediately_hides_card(): void
    {
        $gym = $this->createGymWithProfile('Dynamic Card Gym');
        $typeId = $this->createMembershipType($gym['companyId'], 'Стандарт', 25000);

        $profileRepo = $this->app->make(GymProfileRepositoryInterface::class);
        $typeRepo = $this->app->make(MembershipTypeRepositoryInterface::class);
        $useCase = $this->app->make(GetPublicGym::class);

        $initial = $useCase->execute($gym['publicId']);
        $this->assertNotNull($initial);
        $this->assertCount(1, $initial->membershipTypes);

        $typeRepo->setActive($typeId, false);
        $withoutActiveTypes = $useCase->execute($gym['publicId']);
        $this->assertNotNull($withoutActiveTypes);
        $this->assertSame([], $withoutActiveTypes->membershipTypes);

        $profile = $profileRepo->findByGymId($gym['gymId']) ?? $this->fail('Profile not found');
        $profile->setPublication(false);
        $profileRepo->save($profile);

        $this->assertNull($useCase->execute($gym['publicId']));

        $profile->setPublication(true);
        $profileRepo->save($profile);
        $this->assertNotNull($useCase->execute($gym['publicId']));

        $profile->markContentChanged();
        $profileRepo->save($profile);
        $this->assertNull($useCase->execute($gym['publicId']));
    }
}
