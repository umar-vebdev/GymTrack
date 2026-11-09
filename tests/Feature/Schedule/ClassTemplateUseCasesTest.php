<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Schedule\Application\CreateClassTemplate;
use App\Modules\Schedule\Application\CreateClassTemplateCommand;
use App\Modules\Schedule\Application\ListClassTemplates;
use App\Modules\Schedule\Application\UpdateClassTemplate;
use App\Modules\Schedule\Application\UpdateClassTemplateCommand;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassTemplate;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Schedule\Infrastructure\Persistence\ClassTemplateModel;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClassTemplateUseCasesTest extends TestCase
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

    public function test_create_update_and_list_class_templates_for_gym(): void
    {
        $setup = $this->createSetup();
        $secondTrainer = $this->app->make(TrainerRepositoryInterface::class)->create(
            $setup['companyId'],
            $setup['gymId'],
            'Second Trainer',
        );

        $create = $this->app->make(CreateClassTemplate::class);
        $update = $this->app->make(UpdateClassTemplate::class);
        $list = $this->app->make(ListClassTemplates::class);

        $template = $create->execute(new CreateClassTemplateCommand(
            companyId: $setup['companyId'],
            gymId: $setup['gymId'],
            trainerId: $setup['trainerId'],
            title: '  Утренняя йога  ',
            description: '  Коврики в зале  ',
            weekday: 1,
            startTimeMinute: 540,
            durationMinutes: 60,
            capacity: 15,
        ));

        $this->assertSame('Утренняя йога', $template->title());
        $this->assertSame('Коврики в зале', $template->description());
        $this->assertTrue($template->isActive());

        $updated = $update->execute(new UpdateClassTemplateCommand(
            companyId: $setup['companyId'],
            templateId: $template->id(),
            trainerId: $secondTrainer->id(),
            title: 'Вечерняя йога',
            description: null,
            weekday: 3,
            startTimeMinute: 1140,
            durationMinutes: 75,
            capacity: 20,
            isActive: false,
        ));

        $this->assertSame($secondTrainer->id(), $updated->trainerId());
        $this->assertSame('Вечерняя йога', $updated->title());
        $this->assertNull($updated->description());
        $this->assertSame(3, $updated->weekday());
        $this->assertSame(1140, $updated->startTimeMinute());
        $this->assertSame(75, $updated->durationMinutes());
        $this->assertSame(20, $updated->capacity());
        $this->assertFalse($updated->isActive());

        $this->assertCount(1, $list->execute($setup['companyId'], $setup['gymId']));
        $this->assertCount(0, $list->execute($setup['companyId'], $setup['gymId'], true));
    }

    public function test_create_rejects_foreign_gym_inactive_or_other_gym_trainer_and_invalid_fields(): void
    {
        $a = $this->createSetup('A');
        $b = $this->createSetup('B');
        $secondGymInA = $this->app->make(GymRepositoryInterface::class)->create($a['companyId'], 'Gym A2', null);
        $trainerRepo = $this->app->make(TrainerRepositoryInterface::class);
        $otherGymTrainer = $trainerRepo->create($a['companyId'], $secondGymInA, 'Trainer A2');

        $inactiveTrainer = $trainerRepo->create($a['companyId'], $a['gymId'], 'Inactive');
        $inactiveTrainer->deactivate();
        $trainerRepo->save($inactiveTrainer);

        $create = $this->app->make(CreateClassTemplate::class);

        $rejectedCases = [
            fn () => $create->execute(new CreateClassTemplateCommand(
                $a['companyId'],
                $b['gymId'],
                $a['trainerId'],
                'Йога',
                null,
                1,
                540,
                60,
                10,
            )),
            fn () => $create->execute(new CreateClassTemplateCommand(
                $a['companyId'],
                $a['gymId'],
                $b['trainerId'],
                'Йога',
                null,
                1,
                540,
                60,
                10,
            )),
            fn () => $create->execute(new CreateClassTemplateCommand(
                $a['companyId'],
                $a['gymId'],
                $otherGymTrainer->id(),
                'Йога',
                null,
                1,
                540,
                60,
                10,
            )),
            fn () => $create->execute(new CreateClassTemplateCommand(
                $a['companyId'],
                $a['gymId'],
                $inactiveTrainer->id(),
                'Йога',
                null,
                1,
                540,
                60,
                10,
            )),
        ];

        foreach ($rejectedCases as $index => $call) {
            try {
                $call();
                $this->fail("Case {$index} must throw ScheduleResourceNotFound.");
            } catch (ScheduleResourceNotFound) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $create->execute(new CreateClassTemplateCommand(
                $a['companyId'],
                $a['gymId'],
                $a['trainerId'],
                'Йога',
                null,
                9,
                540,
                60,
                10,
            ));
            $this->fail('Invalid weekday must throw InvalidClassTemplate.');
        } catch (InvalidClassTemplate) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, ClassTemplateModel::query()->withoutGlobalScopes()->count());
    }

    public function test_update_and_list_enforce_tenant_and_trainer_gym_boundaries(): void
    {
        $a = $this->createSetup('A');
        $b = $this->createSetup('B');
        $create = $this->app->make(CreateClassTemplate::class);
        $update = $this->app->make(UpdateClassTemplate::class);
        $list = $this->app->make(ListClassTemplates::class);

        $foreignTemplate = $create->execute(new CreateClassTemplateCommand(
            $b['companyId'],
            $b['gymId'],
            $b['trainerId'],
            'Чужой шаблон',
            null,
            2,
            600,
            60,
            12,
        ));

        $ownTemplate = $create->execute(new CreateClassTemplateCommand(
            $a['companyId'],
            $a['gymId'],
            $a['trainerId'],
            'Свой шаблон',
            null,
            1,
            540,
            60,
            15,
        ));

        try {
            $update->execute(new UpdateClassTemplateCommand(
                $a['companyId'],
                $foreignTemplate->id(),
                $a['trainerId'],
                'Взлом',
                null,
                1,
                540,
                60,
                10,
            ));
            $this->fail('Foreign template must throw ScheduleResourceNotFound.');
        } catch (ScheduleResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        try {
            $update->execute(new UpdateClassTemplateCommand(
                $a['companyId'],
                $ownTemplate->id(),
                $b['trainerId'],
                'Свой шаблон',
                null,
                1,
                540,
                60,
                15,
            ));
            $this->fail('Foreign trainer on update must throw ScheduleResourceNotFound.');
        } catch (ScheduleResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        try {
            $list->execute($a['companyId'], $b['gymId']);
            $this->fail('Foreign gym on list must throw ScheduleResourceNotFound.');
        } catch (ScheduleResourceNotFound) {
            $this->addToAssertionCount(1);
        }
    }
}
