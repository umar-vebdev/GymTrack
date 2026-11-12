<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\ModerationStatus;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ListPublicGymScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function freezeClock(string $isoUtc): void
    {
        $fixed = new DateTimeImmutable($isoUtc, new DateTimeZone('UTC'));
        $this->app->instance(Clock::class, new class($fixed) implements Clock
        {
            public function __construct(private readonly DateTimeImmutable $now) {}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        });
    }

    /**
     * @return array{companyId: int, gymId: int, publicId: string}
     */
    private function createGymWithProfile(
        string $name,
        ModerationStatus $status = ModerationStatus::APPROVED,
        bool $isPublic = true,
        ?int $companyId = null,
    ): array {
        if ($companyId === null) {
            $owner = User::factory()->create();
            $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$name}");
        }

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

    public function test_guest_gets_upcoming_public_schedule_without_cancelled_past_or_internal_fields(): void
    {
        $this->freezeClock('2026-11-12 10:00:00');
        $gym = $this->createGymWithProfile('Олимп Центр');
        $secondBranch = $this->createGymWithProfile('Олимп Филиал 2', companyId: $gym['companyId']);

        $trainers = $this->app->make(TrainerRepositoryInterface::class);
        $templates = $this->app->make(ClassTemplateRepositoryInterface::class);
        $sessions = $this->app->make(ClassSessionRepositoryInterface::class);

        $trainer = $trainers->create($gym['companyId'], $gym['gymId'], 'Алишер', 'Тренер йоги');
        $branchTrainer = $trainers->create($gym['companyId'], $secondBranch['gymId'], 'Фарход');

        $yoga = $templates->create($gym['companyId'], $gym['gymId'], $trainer->id(), 'Йога', null, 4, 720, 60, 12);
        $pilates = $templates->create($gym['companyId'], $gym['gymId'], $trainer->id(), 'Пилатес', null, 6, 600, 45, 10);
        $branchTemplate = $templates->create($gym['companyId'], $secondBranch['gymId'], $branchTrainer->id(), 'Бокс', null, 4, 720, 60, 8);

        // Прошедшее -> не возвращается
        $sessions->createIfNotExistsFromTemplate(
            $yoga,
            new DateTimeImmutable('2026-11-12 09:30:00+00:00'),
            new DateTimeImmutable('2026-11-12 10:30:00+00:00'),
        );

        // Сегодня позже -> попадёт первым
        $first = $sessions->createIfNotExistsFromTemplate(
            $yoga,
            new DateTimeImmutable('2026-11-12 12:00:00+00:00'),
            new DateTimeImmutable('2026-11-12 13:00:00+00:00'),
        );
        $this->assertNotNull($first);
        $first->reserveSeat();
        $sessions->save($first);

        // Суббота -> попадёт вторым
        $second = $sessions->createIfNotExistsFromTemplate(
            $pilates,
            new DateTimeImmutable('2026-11-14 10:00:00+00:00'),
            new DateTimeImmutable('2026-11-14 10:45:00+00:00'),
        );
        $this->assertNotNull($second);

        // Отменённое -> не возвращается
        $cancelled = $sessions->createIfNotExistsFromTemplate(
            $yoga,
            new DateTimeImmutable('2026-11-15 12:00:00+00:00'),
            new DateTimeImmutable('2026-11-15 13:00:00+00:00'),
        );
        $this->assertNotNull($cancelled);
        $cancelled->cancel();
        $sessions->save($cancelled);

        // Через 2 недели -> не в дефолтном окне, но видно при явном периоде
        $future = $sessions->createIfNotExistsFromTemplate(
            $yoga,
            new DateTimeImmutable('2026-11-26 12:00:00+00:00'),
            new DateTimeImmutable('2026-11-26 13:00:00+00:00'),
        );
        $this->assertNotNull($future);

        // Занятие другого филиала той же компании -> не попадает в этот зал
        $sessions->createIfNotExistsFromTemplate(
            $branchTemplate,
            new DateTimeImmutable('2026-11-13 12:00:00+00:00'),
            new DateTimeImmutable('2026-11-13 13:00:00+00:00'),
        );

        $response = $this->getJson('/api/v1/public/gyms/'.$gym['publicId'].'/schedule');

        $response->assertOk()
            ->assertJsonPath('meta.from_date', '2026-11-12')
            ->assertJsonPath('meta.to_date', '2026-11-18')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->publicId())
            ->assertJsonPath('data.0.title', 'Йога')
            ->assertJsonPath('data.0.capacity', 12)
            ->assertJsonPath('data.0.available_seats', 11)
            ->assertJsonPath('data.0.starts_at', '2026-11-12T12:00:00+00:00')
            ->assertJsonPath('data.0.ends_at', '2026-11-12T13:00:00+00:00')
            ->assertJsonPath('data.0.trainer.id', $trainer->publicId())
            ->assertJsonPath('data.0.trainer.name', 'Алишер')
            ->assertJsonPath('data.1.id', $second->publicId())
            ->assertJsonPath('data.1.title', 'Пилатес');

        /** @var array<string, mixed> $item */
        $item = $response->json('data.0');
        $this->assertArrayNotHasKey('gym_id', $item);
        $this->assertArrayNotHasKey('company_id', $item);
        $this->assertArrayNotHasKey('template_id', $item);
        $this->assertArrayNotHasKey('trainer_id', $item);
        $this->assertArrayNotHasKey('booked_count', $item);
        $this->assertArrayNotHasKey('status', $item);

        $custom = $this->getJson('/api/v1/public/gyms/'.$gym['publicId'].'/schedule?from_date=2026-11-20&to_date=2026-11-30');
        $custom->assertOk()
            ->assertJsonPath('meta.from_date', '2026-11-20')
            ->assertJsonPath('meta.to_date', '2026-11-30')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $future->publicId());
    }

    public function test_it_returns_404_for_hidden_non_approved_unknown_or_unpublished_gyms(): void
    {
        $this->freezeClock('2026-11-12 10:00:00');
        $hidden = $this->createGymWithProfile('Hidden', ModerationStatus::APPROVED, false);
        $draft = $this->createGymWithProfile('Draft', ModerationStatus::DRAFT, false);
        $pending = $this->createGymWithProfile('Pending', ModerationStatus::PENDING, false);
        $rejected = $this->createGymWithProfile('Rejected', ModerationStatus::REJECTED, false);

        foreach ([$hidden['publicId'], $draft['publicId'], $pending['publicId'], $rejected['publicId'], '01J00000000000000000000000'] as $id) {
            $this->getJson('/api/v1/public/gyms/'.$id.'/schedule')
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'NOT_FOUND');
        }

        $live = $this->createGymWithProfile('Live');
        $this->getJson('/api/v1/public/gyms/'.$live['publicId'].'/schedule')->assertOk();

        $profileRepo = $this->app->make(GymProfileRepositoryInterface::class);
        $profile = $profileRepo->findByGymId($live['gymId']) ?? $this->fail('Profile not found');
        $profile->markContentChanged();
        $profileRepo->save($profile);

        $this->getJson('/api/v1/public/gyms/'.$live['publicId'].'/schedule')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_it_returns_422_for_invalid_date_filters(): void
    {
        $this->freezeClock('2026-11-12 10:00:00');
        $gym = $this->createGymWithProfile('Validation Gym');
        $url = '/api/v1/public/gyms/'.$gym['publicId'].'/schedule';

        $this->getJson($url.'?from_date=12-11-2026')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['from_date']]]);

        $this->getJson($url.'?from_date=2026-11-15&to_date=2026-11-14')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['to_date']]]);

        $this->getJson($url.'?to_date=2026-11-11')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['to_date']]]);

        $this->getJson($url.'?from_date=2026-11-01&to_date=2027-01-10')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['to_date']]]);
    }
}

