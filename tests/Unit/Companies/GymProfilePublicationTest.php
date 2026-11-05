<?php

declare(strict_types=1);

namespace Tests\Unit\Companies;

use App\Modules\Companies\Domain\Exceptions\InvalidGymProfileTransition;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\ModerationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GymProfilePublicationTest extends TestCase
{
    private function profile(ModerationStatus $status): GymProfile
    {
        return new GymProfile(
            1, 'Description', 'Address', null, null, '+992900000000', [], [],
            $status, null, $status === ModerationStatus::APPROVED ? new DateTimeImmutable : null, 'Сино',
        );
    }

    /** @return array<string, array{ModerationStatus}> */
    public static function statuses(): array
    {
        return [
            'draft' => [ModerationStatus::DRAFT],
            'pending' => [ModerationStatus::PENDING],
            'approved' => [ModerationStatus::APPROVED],
            'rejected' => [ModerationStatus::REJECTED],
        ];
    }

    public function test_draft_is_private_and_has_no_district_by_default(): void
    {
        $profile = GymProfile::draft(1);
        $this->assertFalse($profile->isPublic());
        $this->assertNull($profile->district());
    }

    #[DataProvider('statuses')]
    public function test_only_approved_profile_can_be_made_public(ModerationStatus $status): void
    {
        $profile = $this->profile($status);
        $this->assertFalse($profile->isPublic());
        if ($status !== ModerationStatus::APPROVED) {
            $this->expectException(InvalidGymProfileTransition::class);
        }
        $profile->setPublication(true);
        $profile->setPublication(true);
        $this->assertTrue($profile->isPublic());
        $this->assertSame($status, $profile->status());
    }

    #[DataProvider('statuses')]
    public function test_unpublishing_is_allowed_in_every_status_without_changing_moderation(ModerationStatus $status): void
    {
        $profile = $this->profile($status);
        $publishedAt = $profile->publishedAt();
        if ($status === ModerationStatus::APPROVED) {
            $profile->setPublication(true);
        }
        $profile->setPublication(false);
        $profile->setPublication(false);
        $this->assertFalse($profile->isPublic());
        $this->assertSame($status, $profile->status());
        $this->assertSame($publishedAt, $profile->publishedAt());
        $this->assertSame('Сино', $profile->district());
    }

    public function test_editing_content_unpublishes_profile_and_saves_district(): void
    {
        $profile = $this->profile(ModerationStatus::APPROVED);
        $profile->setPublication(true);
        $profile->update('Updated', 'Address', null, null, '+992900000000', [], [], 'Исмоили Сомони');
        $this->assertFalse($profile->isPublic());
        $this->assertSame('Исмоили Сомони', $profile->district());
        $this->assertSame(ModerationStatus::DRAFT, $profile->status());
        $this->assertNull($profile->publishedAt());
        $profile->submit();
        $profile->approve(new DateTimeImmutable);
        $this->assertFalse($profile->isPublic());
    }

    public function test_photo_change_unpublishes_profile_without_changing_district(): void
    {
        $profile = $this->profile(ModerationStatus::APPROVED);
        $profile->setPublication(true);
        $profile->markContentChanged();
        $this->assertFalse($profile->isPublic());
        $this->assertSame('Сино', $profile->district());
        $this->assertSame(ModerationStatus::DRAFT, $profile->status());
        $this->assertNull($profile->publishedAt());
    }

    public function test_constructor_rejects_public_profile_that_has_not_been_approved(): void
    {
        $this->expectException(InvalidGymProfileTransition::class);
        new GymProfile(1, null, null, null, null, null, [], [], ModerationStatus::DRAFT, null, null, null, true);
    }
}
