<?php

declare(strict_types=1);

namespace Tests\Unit\Memberships;

use App\Modules\Memberships\Domain\InvalidMembershipRules;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\StartMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MembershipRulesTest extends TestCase
{
    public function test_it_creates_visits_package(): void
    {
        $rules = MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 12, validityDays: 60);

        $this->assertSame(12, $rules->visitsCount);
        $this->assertSame(60, $rules->validityDays);
        $this->assertSame(1, $rules->dailyLimit);
    }

    public function test_it_creates_duration_and_unlimited(): void
    {
        $month = MembershipRules::create(MembershipKind::Duration, StartMode::FirstVisit, durationDays: 30, freezeMaxDays: 14, freezeMaxTimes: 2);
        $unlimited = MembershipRules::create(MembershipKind::Unlimited, StartMode::OnSale, durationDays: 365, timeFromMinute: 0, timeToMinute: 1020);

        $this->assertSame(30, $month->durationDays);
        $this->assertSame(1020, $unlimited->timeToMinute);
    }

    public function test_it_creates_single_visit(): void
    {
        $rules = MembershipRules::create(MembershipKind::Single, StartMode::OnSale);

        $this->assertSame(MembershipKind::Single, $rules->kind);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    #[DataProvider('invalidCases')]
    public function test_it_rejects_invalid_combinations(MembershipKind $kind, StartMode $mode, array $args): void
    {
        $this->expectException(InvalidMembershipRules::class);

        MembershipRules::create($kind, $mode, ...$args);
    }

    /**
     * @return array<string, array{MembershipKind, StartMode, array<string, mixed>}>
     */
    public static function invalidCases(): array
    {
        return [
            'visits without count' => [MembershipKind::Visits, StartMode::OnSale, []],
            'visits with zero count' => [MembershipKind::Visits, StartMode::OnSale, ['visitsCount' => 0]],
            'visits with duration' => [MembershipKind::Visits, StartMode::OnSale, ['visitsCount' => 5, 'durationDays' => 30]],
            'visits with zero validity' => [MembershipKind::Visits, StartMode::OnSale, ['visitsCount' => 5, 'validityDays' => 0]],
            'duration without days' => [MembershipKind::Duration, StartMode::OnSale, []],
            'duration with visits' => [MembershipKind::Duration, StartMode::OnSale, ['durationDays' => 30, 'visitsCount' => 3]],
            'unlimited without days' => [MembershipKind::Unlimited, StartMode::OnSale, []],
            'single with days' => [MembershipKind::Single, StartMode::OnSale, ['durationDays' => 1]],
            'single from first visit' => [MembershipKind::Single, StartMode::FirstVisit, []],
            'single with freeze' => [MembershipKind::Single, StartMode::OnSale, ['freezeMaxDays' => 3, 'freezeMaxTimes' => 1]],
            'daily limit zero' => [MembershipKind::Duration, StartMode::OnSale, ['durationDays' => 30, 'dailyLimit' => 0]],
            'half time window' => [MembershipKind::Duration, StartMode::OnSale, ['durationDays' => 30, 'timeFromMinute' => 60]],
            'reversed time window' => [MembershipKind::Duration, StartMode::OnSale, ['durationDays' => 30, 'timeFromMinute' => 600, 'timeToMinute' => 300]],
            'window beyond day' => [MembershipKind::Duration, StartMode::OnSale, ['durationDays' => 30, 'timeFromMinute' => 0, 'timeToMinute' => 1500]],
            'freeze days without times' => [MembershipKind::Duration, StartMode::OnSale, ['durationDays' => 30, 'freezeMaxDays' => 7]],
            'negative freeze' => [MembershipKind::Duration, StartMode::OnSale, ['durationDays' => 30, 'freezeMaxDays' => -1, 'freezeMaxTimes' => -1]],
        ];
    }
}
