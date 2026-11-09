<?php

declare(strict_types=1);

namespace Tests\Unit\Schedule;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassTemplate;
use App\Modules\Schedule\Infrastructure\Persistence\ClassTemplateModel;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClassTemplateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{companyId: int, gymId: int, trainerId: int}
     */
    private function createSetup(string $suffix = 'A'): array
    {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$suffix}");
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, "Gym {$suffix}", null);
        $trainer = $this->app->make(TrainerRepositoryInterface::class)->create($companyId, $gymId, "Trainer {$suffix}");

        return [
            'companyId' => $companyId,
            'gymId' => $gymId,
            'trainerId' => $trainer->id(),
        ];
    }

    public function test_it_creates_updates_and_lists_templates_in_chronological_order(): void
    {
        $setup = $this->createSetup();
        $repo = $this->app->make(ClassTemplateRepositoryInterface::class);

        $fridayEvening = $repo->create(
            companyId: $setup['companyId'],
            gymId: $setup['gymId'],
            trainerId: $setup['trainerId'],
            title: '  Бокс  ',
            description: '   ',
            weekday: 5,
            startTimeMinute: 1140,
            durationMinutes: 90,
            capacity: 12,
        );

        $mondayMorning = $repo->create(
            companyId: $setup['companyId'],
            gymId: $setup['gymId'],
            trainerId: $setup['trainerId'],
            title: 'Йога',
            description: 'Коврики в зале',
            weekday: 1,
            startTimeMinute: 540,
            durationMinutes: 60,
            capacity: 15,
        );

        $this->assertSame('Бокс', $fridayEvening->title());
        $this->assertNull($fridayEvening->description());
        $this->assertSame(26, strlen($fridayEvening->publicId()));
        $this->assertNotSame($fridayEvening->publicId(), $mondayMorning->publicId());

        $fridayEvening->update($setup['trainerId'], 'Вечерний бокс', 'Перчатки свои', 5, 1170, 90, 14);
        $fridayEvening->deactivate();
        $repo->save($fridayEvening);

        $all = $repo->listForGym($setup['companyId'], $setup['gymId']);
        $this->assertCount(2, $all);
        $this->assertSame([$mondayMorning->id(), $fridayEvening->id()], [$all[0]->id(), $all[1]->id()]);
        $this->assertSame('Вечерний бокс', $all[1]->title());
        $this->assertSame(1170, $all[1]->startTimeMinute());
        $this->assertSame(14, $all[1]->capacity());
        $this->assertFalse($all[1]->isActive());

        $onlyActive = $repo->listForGym($setup['companyId'], $setup['gymId'], true);
        $this->assertCount(1, $onlyActive);
        $this->assertSame($mondayMorning->id(), $onlyActive[0]->id());
    }

    public function test_it_isolates_companies_and_rejects_invalid_data_without_inserts(): void
    {
        $a = $this->createSetup('A');
        $b = $this->createSetup('B');
        $repo = $this->app->make(ClassTemplateRepositoryInterface::class);

        $foreign = $repo->create($b['companyId'], $b['gymId'], $b['trainerId'], 'Чужое занятие', null, 2, 600, 60, 10);

        $this->assertNull($repo->findById($a['companyId'], $foreign->id()));
        $this->assertSame([], $repo->listForGym($a['companyId'], $b['gymId']));

        $forged = new ClassTemplate(
            id: $foreign->id(),
            publicId: $foreign->publicId(),
            companyId: $a['companyId'],
            gymId: $a['gymId'],
            trainerId: $a['trainerId'],
            title: 'Взлом',
            description: null,
            weekday: 1,
            startTimeMinute: 500,
            durationMinutes: 60,
            capacity: 10,
            isActive: false,
        );
        $repo->save($forged);

        $untouched = $repo->findById($b['companyId'], $foreign->id());
        $this->assertNotNull($untouched);
        $this->assertSame('Чужое занятие', $untouched->title());
        $this->assertTrue($untouched->isActive());

        try {
            $repo->create($a['companyId'], $a['gymId'], $a['trainerId'], '  ', null, 1, 540, 60, 10);
            $this->fail('Blank title must throw.');
        } catch (InvalidClassTemplate) {
            $this->assertSame(1, ClassTemplateModel::query()->withoutGlobalScopes()->count());
        }
    }
}
