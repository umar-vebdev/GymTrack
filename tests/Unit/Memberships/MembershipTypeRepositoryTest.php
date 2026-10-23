<?php

declare(strict_types=1);

namespace Tests\Unit\Memberships;

use App\Models\User;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MembershipTypeRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_finds_membership_type(): void
    {
        $user = User::factory()->create();
        $company = CompanyModel::create(['owner_id' => $user->id, 'name' => 'Company']);

        /** @var MembershipTypeRepositoryInterface $repo */
        $repo = $this->app->make(MembershipTypeRepositoryInterface::class);

        $rules = MembershipRules::create(
            MembershipKind::Visits,
            StartMode::FirstVisit,
            visitsCount: 12,
            validityDays: 60,
            timeFromMinute: 0,
            timeToMinute: 1020,
            freezeMaxDays: 14,
            freezeMaxTimes: 2
        );

        $id = $repo->create($company->id, '12 занятий', new Money(60000, 'TJS'), $rules);

        $type = $repo->findById($id);

        $this->assertNotNull($type);
        $this->assertSame('12 занятий', $type->name());
        $this->assertSame($company->id, $type->companyId());
        $this->assertSame(60000, $type->price()->amount());
        $this->assertSame('TJS', $type->price()->currency());
        $this->assertTrue($type->isActive());
        $this->assertSame(MembershipKind::Visits, $type->rules()->kind);
        $this->assertSame(StartMode::FirstVisit, $type->rules()->startMode);
        $this->assertSame(12, $type->rules()->visitsCount);
        $this->assertSame(60, $type->rules()->validityDays);
        $this->assertSame(1020, $type->rules()->timeToMinute);
        $this->assertSame(14, $type->rules()->freezeMaxDays);
    }
}
