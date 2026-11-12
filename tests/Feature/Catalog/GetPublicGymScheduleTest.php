<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Application\GetPublicGymSchedule;
use App\Modules\Companies\Contracts\PublicGymQueryInterface;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\ModerationStatus;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Modules\Schedule\Contracts\ScheduleCatalogQueryInterface;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class GetPublicGymScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function freezeClock(string $isoUtc): Clock
    {
        $fixed = new DateTimeImmutable($isoUtc, new DateTimeZone('UTC'));
        $clock = new class($fixed) implements Clock
        {
            public function __construct(private readonly DateTimeImmutable $now) {}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };

        $this->app->instance(Clock::class, $clock);

        return $clock;
    }

    /**
     * @return array{companyId: int, gymId: int, publicId: string}
     */
    private function createGymWithProfile(
        string $name,
        ModerationStatus $status = ModerationStatus::APPROVED,
        bool $isPublic = true,
    ): array {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$name}");
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, $name, 'ул. Рудаки, 1');
        $publicId = (string) GymModel::query()->withoutGlobalScopes()->whereKey($gymId)->value('public_id');

        $profile = GymProfile::draft($gymId);
        $profile->update(
            description: "Описание {$name}",
            address: 'ул. Рудаки, 10',
            latitude: 38.5598,
            longitude: 68.787,
            phone: '+992900000000',
            workingHours: ['mon' => '08:00-22:00'],
            amenities: ['shower'],
            district: 'Сино',
        );

        if ($status !== ModerationStatus::DRAFT) {
            $profile->submit();
        }
        if ($status === ModerationStatus::APPROVED) {
            $profile->approve(new DateTimeImmutable('2026-11-05 10:00:00'));
            $profile->setPublication($isPublic);
        } elseif ($status === ModerationStatus::REJECTED) {
            $profile->reject('Причина отклонения');
        }

        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        return [
            'companyId' => $companyId,
            'gymId' => $gymId,
            'publicId' => $publicId,
        ];
    }

    public function test_it_returns_upcoming_schedule_with_default_7_day_window_and_custom_period(): void
    {
        $this->freezeClock('2026-11-12 10:00:00');
        $gym = $this->createGymWithProfile('Олимп Центр');
        $otherGym = $this->createGymWithProfile('Соседний Зал');

        $trainers = $this->app->make(TrainerRepositoryInterface::class);
        $templates = $this->app->make(ClassTemplateRepositoryInterface::class);
        $sessions = $this->app->make(ClassSessionRepositoryInterface::class);

        $trainer = $trainers->create($gym['companyId'], $gym['gymId'], 'Алишер');
        $otherTrainer = $trainers->create($otherGym['companyId'], $otherGym['gymId'], 'Далер');

        $template = $templates->create($gym['companyId'], $gym['gymId'], $trainer->id(), 'Йога', null, 4, 720, 60, 12);
        $otherTemplate = $templates->create($otherGym['companyId'], $otherGym['gymId'], $otherTrainer->id(), 'Бокс', null, 4, 720, 60, 10);

        // Вчера -> не попадёт
        $sessions->createIfNotExistsFromTemplate(
            $template,
            new DateTimeImmutable('2026-11-12 09:00:00+00:00'),
            new DateTimeImmutable('2026-11-12 10:00:00+00:00'),
        );

        // Сегодня позже now -> попадёт в окно 7 дней (12..18)
        $todaySession = $sessions->createIfNotExistsFromTemplate(
            $template,
            new DateTimeImmutable('2026-11-12 12:00:00+00:00'),
            new DateTimeImmutable('2026-11-12 13:00:00+00:00'),
        );
        $this->assertNotNull($todaySession);

        // Отменено -> не попадёт
        $cancelled = $sessions->createIfNotExistsFromTemplate(
            $template,
            new DateTimeImmutable('2026-11-14 12:00:00+00:00'),
            new DateTimeImmutable('2026-11-14 13:00:00+00:00'),
        );
        $this->assertNotNull($cancelled);
        $cancelled->cancel();
        $sessions->save($cancelled);

        // На 8-й день (19 ноября) -> в дефолт 7 дней не попадёт, а в явный период попадёт
        $nextWeek = $sessions->createIfNotExistsFromTemplate(
            $template,
            new DateTimeImmutable('2026-11-19 12:00:00+00:00'),
            new DateTimeImmutable('2026-11-19 13:00:00+00:00'),
        );
        $this->assertNotNull($nextWeek);

        // Другой зал -> не попадёт
        $sessions->createIfNotExistsFromTemplate(
            $otherTemplate,
            new DateTimeImmutable('2026-11-13 12:00:00+00:00'),
            new DateTimeImmutable('2026-11-13 13:00:00+00:00'),
        );

        $useCase = $this->app->make(GetPublicGymSchedule::class);
        $defaultResult = $useCase->execute($gym['publicId']);

        $this->assertNotNull($defaultResult);
        $this->assertSame('2026-11-12', $defaultResult->fromDate->format('Y-m-d'));
        $this->assertSame('2026-11-18', $defaultResult->toDate->format('Y-m-d'));
        $this->assertCount(1, $defaultResult->sessions);
        $this->assertSame($todaySession->publicId(), $defaultResult->sessions[0]->publicId);
        $this->assertSame('Алишер', $defaultResult->sessions[0]->trainerName);

        $customResult = $useCase->execute(
            $gym['publicId'],
            new DateTimeImmutable('2026-11-18 00:00:00+00:00'),
            new DateTimeImmutable('2026-11-20 00:00:00+00:00'),
        );

        $this->assertNotNull($customResult);
        $this->assertSame('2026-11-18', $customResult->fromDate->format('Y-m-d'));
        $this->assertSame('2026-11-20', $customResult->toDate->format('Y-m-d'));
        $this->assertCount(1, $customResult->sessions);
        $this->assertSame($nextWeek->publicId(), $customResult->sessions[0]->publicId);
    }

    public function test_it_returns_null_and_skips_schedule_query_when_gym_is_hidden_or_missing(): void
    {
        $clock = $this->freezeClock('2026-11-12 10:00:00');
        $hidden = $this->createGymWithProfile('Hidden', ModerationStatus::APPROVED, false);
        $draft = $this->createGymWithProfile('Draft', ModerationStatus::DRAFT, false);
        $pending = $this->createGymWithProfile('Pending', ModerationStatus::PENDING, false);
        $rejected = $this->createGymWithProfile('Rejected', ModerationStatus::REJECTED, false);

        $spySchedule = new class implements ScheduleCatalogQueryInterface
        {
            public bool $called = false;

            public function listUpcomingForGym(
                int $gymId,
                DateTimeImmutable $from,
                DateTimeImmutable $to,
                DateTimeImmutable $now,
            ): array {
                $this->called = true;

                return [];
            }
        };

        $useCase = new GetPublicGymSchedule(
            $this->app->make(PublicGymQueryInterface::class),
            $spySchedule,
            $clock,
        );

        foreach ([$hidden['publicId'], $draft['publicId'], $pending['publicId'], $rejected['publicId'], '01J00000000000000000000000'] as $id) {
            $this->assertNull($useCase->execute($id));
        }
        $this->assertFalse($spySchedule->called);
    }

    public function test_it_hides_schedule_immediately_after_unpublishing_and_validates_period(): void
    {
        $this->freezeClock('2026-11-12 10:00:00');
        $gym = $this->createGymWithProfile('Dynamic Gym');
        $profileRepo = $this->app->make(GymProfileRepositoryInterface::class);
        $useCase = $this->app->make(GetPublicGymSchedule::class);

        $this->assertNotNull($useCase->execute($gym['publicId']));

        $profile = $profileRepo->findByGymId($gym['gymId']) ?? $this->fail('Profile not found');
        $profile->setPublication(false);
        $profileRepo->save($profile);

        $this->assertNull($useCase->execute($gym['publicId']));

        try {
            $useCase->execute(
                $gym['publicId'],
                new DateTimeImmutable('2026-11-15'),
                new DateTimeImmutable('2026-11-14'),
            );
            $this->fail('Reversed period must be rejected.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            $useCase->execute(
                $gym['publicId'],
                new DateTimeImmutable('2026-11-01'),
                new DateTimeImmutable('2027-01-10'),
            );
            $this->fail('Period > 62 days must be rejected.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }
}
