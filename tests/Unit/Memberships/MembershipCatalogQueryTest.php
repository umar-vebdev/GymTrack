<?php

declare(strict_types=1);

namespace Tests\Unit\Memberships;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Memberships\Contracts\MembershipCatalogQueryInterface;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Memberships\Infrastructure\Persistence\MembershipTypeModel;
use App\Shared\Application\CurrentCompanyInterface;
use App\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MembershipCatalogQueryTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $name): int
    {
        $owner = User::factory()->create();

        return $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, $name);
    }

    public function test_it_lists_only_active_types_for_company_with_public_id_and_rules_sorted_by_public_id(): void
    {
        $companyA = $this->createCompany('Corp A');
        $companyB = $this->createCompany('Corp B');
        $repo = $this->app->make(MembershipTypeRepositoryInterface::class);

        $visitsId = $repo->create(
            companyId: $companyA,
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

        $unlimitedId = $repo->create(
            companyId: $companyA,
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

        $inactiveId = $repo->create(
            companyId: $companyA,
            name: 'Архивный тариф',
            price: new Money(10000, 'TJS'),
            rules: MembershipRules::create(
                kind: MembershipKind::Single,
                startMode: StartMode::OnSale,
            ),
        );
        $repo->setActive($inactiveId, false);

        $repo->create(
            companyId: $companyB,
            name: 'Чужой тариф',
            price: new Money(15000, 'TJS'),
            rules: MembershipRules::create(
                kind: MembershipKind::Single,
                startMode: StartMode::OnSale,
            ),
        );

        $this->app->make(CurrentCompanyInterface::class)->setId($companyB);

        $query = $this->app->make(MembershipCatalogQueryInterface::class);
        $items = $query->listActiveForCompany($companyA);

        $this->assertCount(2, $items);

        $visitsPublicId = (string) MembershipTypeModel::query()->withoutGlobalScopes()->whereKey($visitsId)->value('public_id');
        $unlimitedPublicId = (string) MembershipTypeModel::query()->withoutGlobalScopes()->whereKey($unlimitedId)->value('public_id');
        $expectedOrder = [$visitsPublicId, $unlimitedPublicId];
        sort($expectedOrder);

        $this->assertSame($expectedOrder, [$items[0]->publicId, $items[1]->publicId]);

        $byPublicId = [];
        foreach ($items as $item) {
            $byPublicId[$item->publicId] = $item;
            $this->assertArrayNotHasKey('id', get_object_vars($item));
            $this->assertArrayNotHasKey('companyId', get_object_vars($item));
        }

        $visitsDto = $byPublicId[$visitsPublicId];
        $this->assertSame('12 занятий (утро)', $visitsDto->name);
        $this->assertSame('visits', $visitsDto->kind);
        $this->assertSame('first_visit', $visitsDto->startMode);
        $this->assertSame(25000, $visitsDto->priceMinor);
        $this->assertSame('TJS', $visitsDto->currency);
        $this->assertSame(12, $visitsDto->visitsCount);
        $this->assertNull($visitsDto->durationDays);
        $this->assertSame(60, $visitsDto->validityDays);
        $this->assertSame(1, $visitsDto->dailyLimit);
        $this->assertSame(360, $visitsDto->timeFromMinute);
        $this->assertSame(1020, $visitsDto->timeToMinute);
        $this->assertSame(14, $visitsDto->freezeMaxDays);
        $this->assertSame(2, $visitsDto->freezeMaxTimes);
    }

    public function test_deactivation_and_price_change_are_reflected_immediately(): void
    {
        $company = $this->createCompany('Corp');
        $repo = $this->app->make(MembershipTypeRepositoryInterface::class);
        $query = $this->app->make(MembershipCatalogQueryInterface::class);

        $typeId = $repo->create(
            companyId: $company,
            name: 'Месяц',
            price: new Money(30000, 'TJS'),
            rules: MembershipRules::create(
                kind: MembershipKind::Duration,
                startMode: StartMode::OnSale,
                durationDays: 30,
            ),
        );

        $this->assertSame([$company], $query->findCompanyIdsByPriceRange('TJS', 25000, 35000));
        $this->assertCount(1, $query->listActiveForCompany($company));

        MembershipTypeModel::query()->withoutGlobalScopes()->whereKey($typeId)->update(['price_minor' => 50000]);
        $this->assertSame([], $query->findCompanyIdsByPriceRange('TJS', 25000, 35000));
        $this->assertSame([$company], $query->findCompanyIdsByPriceRange('TJS', 50000, 50000));
        $this->assertSame(50000, $query->listActiveForCompany($company)[0]->priceMinor);

        $repo->setActive($typeId, false);
        $this->assertSame([], $query->listActiveForCompany($company));
        $this->assertSame([], $query->findCompanyIdsByPriceRange('TJS', null, null));
    }

    public function test_price_range_filter_checks_inclusive_boundaries_and_does_not_mix_currencies(): void
    {
        $companyTjsLow = $this->createCompany('TJS Low');
        $companyTjsMid = $this->createCompany('TJS Mid');
        $companyUsd = $this->createCompany('USD Corp');
        $repo = $this->app->make(MembershipTypeRepositoryInterface::class);

        $repo->create(
            companyId: $companyTjsLow,
            name: 'Разовое',
            price: new Money(5000, 'TJS'),
            rules: MembershipRules::create(MembershipKind::Single, StartMode::OnSale),
        );

        $repo->create(
            companyId: $companyTjsMid,
            name: 'Пакет 8',
            price: new Money(20000, 'TJS'),
            rules: MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 8),
        );
        $repo->create(
            companyId: $companyTjsMid,
            name: 'Пакет 12',
            price: new Money(25000, 'TJS'),
            rules: MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 12),
        );

        $repo->create(
            companyId: $companyUsd,
            name: 'VIP USD',
            price: new Money(20000, 'USD'),
            rules: MembershipRules::create(MembershipKind::Unlimited, StartMode::OnSale, durationDays: 30),
        );

        $query = $this->app->make(MembershipCatalogQueryInterface::class);

        $this->assertSame([$companyTjsLow, $companyTjsMid], $query->findCompanyIdsByPriceRange('  tjs  ', 5000, 20000));
        $this->assertSame([$companyTjsMid], $query->findCompanyIdsByPriceRange('TJS', 5001, 20000));
        $this->assertSame([$companyTjsLow], $query->findCompanyIdsByPriceRange('TJS', null, 19999));
        $this->assertSame([$companyTjsMid], $query->findCompanyIdsByPriceRange('TJS', 25000, null));
        $this->assertSame([], $query->findCompanyIdsByPriceRange('TJS', 25001, null));
        $this->assertSame([$companyUsd], $query->findCompanyIdsByPriceRange('USD', 5000, 25000));
        $this->assertSame([], $query->findCompanyIdsByPriceRange('EUR', null, null));
        $this->assertSame([], $query->findCompanyIdsByPriceRange('   ', null, null));
        $this->assertSame([], $query->findCompanyIdsByPriceRange('TJS', 30000, 10000));
    }
}
