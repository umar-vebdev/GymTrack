<?php

declare(strict_types=1);

namespace Tests\Unit\Companies;

use App\Modules\Companies\Domain\Exceptions\InvalidGymProfileTransition;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\ModerationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GymProfileTest extends TestCase
{
    private function filled(): GymProfile
    {
        $profile = GymProfile::draft(1);
        $profile->update('Описание', 'ул. Рудаки, 1', 38.5598, 68.787, '+992900000000', ['mon' => '08:00-22:00'], ['shower']);

        return $profile;
    }

    public function test_new_profile_is_draft_and_incomplete(): void
    {
        $profile = GymProfile::draft(1);

        $this->assertSame(ModerationStatus::DRAFT, $profile->status());
        $this->assertFalse($profile->isComplete());
    }

    public function test_incomplete_profile_cannot_be_submitted(): void
    {
        $this->expectException(InvalidGymProfileTransition::class);

        GymProfile::draft(1)->submit();
    }

    public function test_full_moderation_flow_approves_profile(): void
    {
        $profile = $this->filled();
        $profile->submit();
        $this->assertSame(ModerationStatus::PENDING, $profile->status());

        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $profile->approve($at);

        $this->assertSame(ModerationStatus::APPROVED, $profile->status());
        $this->assertSame($at, $profile->publishedAt());
    }

    public function test_reject_requires_reason_and_allows_resubmit(): void
    {
        $profile = $this->filled();
        $profile->submit();

        try {
            $profile->reject('  ');
            $this->fail('Ожидалось исключение');
        } catch (InvalidGymProfileTransition) {
            $this->assertSame(ModerationStatus::PENDING, $profile->status());
        }

        $profile->reject('Неверный адрес');
        $this->assertSame('Неверный адрес', $profile->rejectionReason());

        $profile->submit();
        $this->assertSame(ModerationStatus::PENDING, $profile->status());
        $this->assertNull($profile->rejectionReason());
    }

    public function test_update_of_approved_profile_returns_it_to_draft_and_unpublishes(): void
    {
        $profile = $this->filled();
        $profile->submit();
        $profile->approve(new DateTimeImmutable);

        $profile->update('Новое описание', 'ул. Рудаки, 1', null, null, '+992900000000', [], []);

        $this->assertSame(ModerationStatus::DRAFT, $profile->status());
        $this->assertNull($profile->publishedAt());
    }

    public function test_pending_profile_cannot_be_updated_or_approved_twice(): void
    {
        $profile = $this->filled();
        $profile->submit();

        try {
            $profile->update(null, null, null, null, null, [], []);
            $this->fail('Ожидалось исключение');
        } catch (InvalidGymProfileTransition) {
            $this->assertSame(ModerationStatus::PENDING, $profile->status());
        }

        $profile->approve(new DateTimeImmutable);
        $this->expectException(InvalidGymProfileTransition::class);
        $profile->approve(new DateTimeImmutable);
    }
}
