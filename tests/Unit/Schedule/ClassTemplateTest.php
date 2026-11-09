<?php

declare(strict_types=1);

namespace Tests\Unit\Schedule;

use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassTemplate;
use PHPUnit\Framework\TestCase;

final class ClassTemplateTest extends TestCase
{
    private function makeTemplate(
        string $title = 'Утренняя йога',
        ?string $description = 'Для всех уровней',
        int $weekday = 1,
        int $startTimeMinute = 540,
        int $durationMinutes = 60,
        int $capacity = 15,
    ): ClassTemplate {
        return new ClassTemplate(
            id: 1,
            publicId: '01j00000000000000000000000',
            companyId: 10,
            gymId: 20,
            trainerId: 30,
            title: $title,
            description: $description,
            weekday: $weekday,
            startTimeMinute: $startTimeMinute,
            durationMinutes: $durationMinutes,
            capacity: $capacity,
            isActive: true,
        );
    }

    public function test_it_normalizes_title_and_description_and_updates_fields(): void
    {
        $template = $this->makeTemplate('  Кроссфит  ', '   ');

        $this->assertSame('Кроссфит', $template->title());
        $this->assertNull($template->description());
        $this->assertSame(1, $template->weekday());
        $this->assertSame(540, $template->startTimeMinute());
        $this->assertSame(60, $template->durationMinutes());
        $this->assertSame(15, $template->capacity());
        $this->assertTrue($template->isActive());

        $template->update(
            trainerId: 35,
            title: ' Вечерний бокс ',
            description: ' Перчатки свои ',
            weekday: 5,
            startTimeMinute: 1140,
            durationMinutes: 90,
            capacity: 12,
        );
        $template->deactivate();

        $this->assertSame(35, $template->trainerId());
        $this->assertSame('Вечерний бокс', $template->title());
        $this->assertSame('Перчатки свои', $template->description());
        $this->assertSame(5, $template->weekday());
        $this->assertSame(1140, $template->startTimeMinute());
        $this->assertSame(90, $template->durationMinutes());
        $this->assertSame(12, $template->capacity());
        $this->assertFalse($template->isActive());

        $template->activate();
        $this->assertTrue($template->isActive());
    }

    public function test_it_rejects_invalid_invariants_without_corrupting_state(): void
    {
        $invalid = [
            fn () => $this->makeTemplate(title: '   '),
            fn () => $this->makeTemplate(title: str_repeat('я', ClassTemplate::TITLE_MAX + 1)),
            fn () => $this->makeTemplate(description: str_repeat('я', ClassTemplate::DESCRIPTION_MAX + 1)),
            fn () => $this->makeTemplate(weekday: 0),
            fn () => $this->makeTemplate(weekday: 8),
            fn () => $this->makeTemplate(startTimeMinute: -1),
            fn () => $this->makeTemplate(startTimeMinute: 1440),
            fn () => $this->makeTemplate(durationMinutes: 14),
            fn () => $this->makeTemplate(durationMinutes: 241),
            fn () => $this->makeTemplate(capacity: 0),
            fn () => $this->makeTemplate(capacity: 201),
        ];

        foreach ($invalid as $index => $factory) {
            try {
                $factory();
                $this->fail("Case {$index} must throw InvalidClassTemplate.");
            } catch (InvalidClassTemplate) {
                $this->addToAssertionCount(1);
            }
        }

        $template = $this->makeTemplate();
        try {
            $template->update(30, 'Новое название', null, 1, 540, 60, 0);
            $this->fail('Update with capacity 0 must throw.');
        } catch (InvalidClassTemplate) {
            $this->assertSame('Утренняя йога', $template->title());
            $this->assertSame(15, $template->capacity());
        }
    }
}
