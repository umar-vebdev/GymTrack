<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Schedule\Application\CancelClassSession;
use App\Modules\Schedule\Application\CreateClassTemplate;
use App\Modules\Schedule\Application\CreateClassTemplateCommand;
use App\Modules\Schedule\Application\GenerateClassSessions;
use App\Modules\Schedule\Application\ListClassSessions;
use App\Modules\Schedule\Application\UpdateClassTemplate;
use App\Modules\Schedule\Application\UpdateClassTemplateCommand;
use App\Modules\Schedule\Domain\ClassSessionStatus;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassSession;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClassSessionUseCasesTest extends TestCase
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

    public function test_generate_creates_sessions_from_active_templates_idempotently_and_lists_in_order(): void
    {
        $setup = $this->createSetup();
        $createTemplate = $this->app->make(CreateClassTemplate::class);
        $updateTemplate = $this->app->make(UpdateClassTemplate::class);
        $generate = $this->app->make(GenerateClassSessions::class);
        $list = $this->app->make(ListClassSessions::class);

        // Понедельник 09:00–10:00
        $mondayTemplate = $createTemplate->execute(new CreateClassTemplateCommand(
            companyId: $setup['companyId'],
            gymId: $setup['gymId'],
            trainerId: $setup['trainerId'],
            title: 'Йога',
            description: null,
            weekday: 1,
            startTimeMinute: 540,
            durationMinutes: 60,
            capacity: 12,
        ));

        // Среда 18:30–19:45
        $wednesdayTemplate = $createTemplate->execute(new CreateClassTemplateCommand(
            companyId: $setup['companyId'],
            gymId: $setup['gymId'],
            trainerId: $setup['trainerId'],
            title: 'Бокс',
            description: null,
            weekday: 3,
            startTimeMinute: 1110,
            durationMinutes: 75,
            capacity: 10,
        ));

        // Неактивный шаблон на понедельник — не должен генерировать занятия
        $inactiveTemplate = $createTemplate->execute(new CreateClassTemplateCommand(
            companyId: $setup['companyId'],
            gymId: $setup['gymId'],
            trainerId: $setup['trainerId'],
            title: 'Архивный пилатес',
            description: null,
            weekday: 1,
            startTimeMinute: 600,
            durationMinutes: 60,
            capacity: 8,
        ));
        $updateTemplate->execute(new UpdateClassTemplateCommand(
            companyId: $setup['companyId'],
            templateId: $inactiveTemplate->id(),
            trainerId: $setup['trainerId'],
            title: 'Архивный пилатес',
            description: null,
            weekday: 1,
            startTimeMinute: 600,
            durationMinutes: 60,
            capacity: 8,
            isActive: false,
        ));

        // Неделя с понедельника 2026-11-09 по воскресенье 2026-11-15
        $from = new DateTimeImmutable('2026-11-09 00:00:00+00:00');
        $to = new DateTimeImmutable('2026-11-15 23:59:59+00:00');

        $firstRun = $generate->execute($setup['companyId'], $setup['gymId'], $from, $to);
        $this->assertCount(2, $firstRun);
        $this->assertSame($mondayTemplate->id(), $firstRun[0]->templateId());
        $this->assertSame('2026-11-09 09:00:00', $firstRun[0]->startsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 10:00:00', $firstRun[0]->endsAt()->format('Y-m-d H:i:s'));
        $this->assertSame($wednesdayTemplate->id(), $firstRun[1]->templateId());
        $this->assertSame('2026-11-11 18:30:00', $firstRun[1]->startsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-11 19:45:00', $firstRun[1]->endsAt()->format('Y-m-d H:i:s'));

        // Повторный запуск на тот же период идемпотентен: дубликаты не создаются
        $secondRun = $generate->execute($setup['companyId'], $setup['gymId'], $from, $to);
        $this->assertCount(0, $secondRun);

        $listed = $list->execute($setup['companyId'], $setup['gymId'], $from, $to);
        $this->assertCount(2, $listed);
        $this->assertSame([$firstRun[0]->id(), $firstRun[1]->id()], [$listed[0]->id(), $listed[1]->id()]);
    }

    public function test_cancel_marks_session_cancelled_and_enforces_tenant_isolation(): void
    {
        $a = $this->createSetup('A');
        $b = $this->createSetup('B');

        $createTemplate = $this->app->make(CreateClassTemplate::class);
        $generate = $this->app->make(GenerateClassSessions::class);
        $cancel = $this->app->make(CancelClassSession::class);
        $list = $this->app->make(ListClassSessions::class);

        $createTemplate->execute(new CreateClassTemplateCommand(
            companyId: $a['companyId'],
            gymId: $a['gymId'],
            trainerId: $a['trainerId'],
            title: 'Йога A',
            description: null,
            weekday: 1,
            startTimeMinute: 540,
            durationMinutes: 60,
            capacity: 10,
        ));

        $day = new DateTimeImmutable('2026-11-09 00:00:00+00:00');
        $sessions = $generate->execute($a['companyId'], $a['gymId'], $day, $day);
        $this->assertCount(1, $sessions);

        $cancelled = $cancel->execute($a['companyId'], $sessions[0]->id());
        $this->assertSame(ClassSessionStatus::CANCELLED, $cancelled->status());

        // Повторная отмена идемпотентна
        $cancelledAgain = $cancel->execute($a['companyId'], $sessions[0]->id());
        $this->assertSame(ClassSessionStatus::CANCELLED, $cancelledAgain->status());

        // Чужая компания не может отменить занятие компании A
        try {
            $cancel->execute($b['companyId'], $sessions[0]->id());
            $this->fail('Cancelling foreign session must throw ScheduleResourceNotFound.');
        } catch (ScheduleResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        $reloaded = $list->execute(
            $a['companyId'],
            $a['gymId'],
            new DateTimeImmutable('2026-11-09 00:00:00+00:00'),
            new DateTimeImmutable('2026-11-09 23:59:59+00:00'),
        );
        $this->assertCount(1, $reloaded);
        $this->assertSame(ClassSessionStatus::CANCELLED, $reloaded[0]->status());
    }

    public function test_generate_and_list_reject_foreign_gym_and_invalid_periods(): void
    {
        $a = $this->createSetup('A');
        $b = $this->createSetup('B');
        $generate = $this->app->make(GenerateClassSessions::class);
        $list = $this->app->make(ListClassSessions::class);

        $day1 = new DateTimeImmutable('2026-11-09 00:00:00+00:00');
        $day2 = new DateTimeImmutable('2026-11-10 00:00:00+00:00');
        $tooFar = new DateTimeImmutable('2027-02-01 00:00:00+00:00');

        try {
            $generate->execute($a['companyId'], $b['gymId'], $day1, $day2);
            $this->fail('Foreign gym on generate must throw ScheduleResourceNotFound.');
        } catch (ScheduleResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        try {
            $list->execute($a['companyId'], $b['gymId'], $day1, $day2);
            $this->fail('Foreign gym on list must throw ScheduleResourceNotFound.');
        } catch (ScheduleResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        try {
            $generate->execute($a['companyId'], $a['gymId'], $day2, $day1);
            $this->fail('Reversed period on generate must throw InvalidClassSession.');
        } catch (InvalidClassSession) {
            $this->addToAssertionCount(1);
        }

        try {
            $generate->execute($a['companyId'], $a['gymId'], $day1, $tooFar);
            $this->fail('Period exceeding MAX_PERIOD_DAYS must throw InvalidClassSession.');
        } catch (InvalidClassSession) {
            $this->addToAssertionCount(1);
        }

        try {
            $list->execute($a['companyId'], $a['gymId'], $day2, $day1);
            $this->fail('Reversed period on list must throw InvalidClassSession.');
        } catch (InvalidClassSession) {
            $this->addToAssertionCount(1);
        }
    }
}

