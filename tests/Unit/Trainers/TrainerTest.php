<?php

declare(strict_types=1);

namespace Tests\Unit\Trainers;

use App\Modules\Trainers\Domain\Exceptions\InvalidTrainerProfile;
use App\Modules\Trainers\Domain\Trainer;
use PHPUnit\Framework\TestCase;

final class TrainerTest extends TestCase
{
    private function trainer(string $name = 'Иван', ?string $bio = null, bool $isActive = true): Trainer
    {
        return new Trainer(1, '01j00000000000000000000000', 10, 20, null, $name, $bio, $isActive);
    }

    public function test_it_trims_name_and_turns_blank_bio_into_null(): void
    {
        $trainer = $this->trainer('  Иван Петров  ', '   ');

        $this->assertSame('Иван Петров', $trainer->name());
        $this->assertNull($trainer->bio());
        $this->assertTrue($trainer->isActive());
    }

    public function test_it_updates_profile_and_toggles_activity(): void
    {
        $trainer = $this->trainer();

        $trainer->updateProfile(' Олег ', ' Мастер спорта ');
        $trainer->deactivate();

        $this->assertSame('Олег', $trainer->name());
        $this->assertSame('Мастер спорта', $trainer->bio());
        $this->assertFalse($trainer->isActive());

        $trainer->activate();
        $this->assertTrue($trainer->isActive());
    }

    public function test_it_rejects_blank_or_too_long_name_and_too_long_bio(): void
    {
        $invalid = [
            fn () => $this->trainer('   '),
            fn () => $this->trainer(str_repeat('я', Trainer::NAME_MAX + 1)),
            fn () => $this->trainer('Иван', str_repeat('я', Trainer::BIO_MAX + 1)),
            fn () => $this->trainer()->updateProfile('', null),
        ];

        foreach ($invalid as $index => $factory) {
            try {
                $factory();
                $this->fail("Case {$index} must be rejected.");
            } catch (InvalidTrainerProfile) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(Trainer::NAME_MAX, mb_strlen($this->trainer(str_repeat('я', Trainer::NAME_MAX))->name()));
    }
}
