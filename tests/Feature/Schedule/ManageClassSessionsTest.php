<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ManageClassSessionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, admin: User, trainerUser: User, companyId: int, gymId: int, trainerId: int}
     */
    private function createCompanySetup(string $suffix = 'A'): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $trainerUser = User::factory()->create();

        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$suffix}");
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $staff->create($owner->id, $companyId, Role::OWNER);
        $staff->create($admin->id, $companyId, Role::ADMIN);
        $staff->create($trainerUser->id, $companyId, Role::TRAINER);

        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, "Gym {$suffix}", 'ул. Рудаки, 1');
        $trainer = $this->app->make(TrainerRepositoryInterface::class)->create($companyId, $gymId, "Trainer {$suffix}");

        return [
            'owner' => $owner,
            'admin' => $admin,
            'trainerUser' => $trainerUser,
            'companyId' => $companyId,
            'gymId' => $gymId,
            'trainerId' => $trainer->id(),
        ];
    }

    public function test_owner_and_admin_can_generate_idempotently_list_and_cancel_class_sessions(): void
    {
        $setup = $this->createCompanySetup();
        $templates = $this->app->make(ClassTemplateRepositoryInterface::class);

        $template = $templates->create(
            companyId: $setup['companyId'],
            gymId: $setup['gymId'],
            trainerId: $setup['trainerId'],
            title: 'Утренняя йога',
            description: null,
            weekday: 1,
            startTimeMinute: 540,
            durationMinutes: 60,
            capacity: 15,
        );

        $generated = $this->actingAs($setup['admin'], 'sanctum')
            ->postJson('/api/v1/business/schedule/sessions/generate', [
                'gym_id' => $setup['gymId'],
                'from_date' => '2026-11-09',
                'to_date' => '2026-11-15',
            ]);

        $generated->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.gym_id', $setup['gymId'])
            ->assertJsonPath('data.0.template_id', $template->id())
            ->assertJsonPath('data.0.trainer_id', $setup['trainerId'])
            ->assertJsonPath('data.0.title', 'Утренняя йога')
            ->assertJsonPath('data.0.capacity', 15)
            ->assertJsonPath('data.0.booked_count', 0)
            ->assertJsonPath('data.0.available_seats', 15)
            ->assertJsonPath('data.0.starts_at', '2026-11-09T09:00:00+00:00')
            ->assertJsonPath('data.0.ends_at', '2026-11-09T10:00:00+00:00')
            ->assertJsonPath('data.0.status', 'scheduled');

        $sessionId = (int) $generated->json('data.0.id');

        // Повторный запуск на тот же период возвращает пустой массив новых слотов
        $this->actingAs($setup['owner'], 'sanctum')
            ->postJson('/api/v1/business/schedule/sessions/generate', [
                'gym_id' => $setup['gymId'],
                'from_date' => '2026-11-09',
                'to_date' => '2026-11-15',
            ])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $cancelled = $this->actingAs($setup['owner'], 'sanctum')
            ->postJson("/api/v1/business/schedule/sessions/{$sessionId}/cancel");

        $cancelled->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.status', 'cancelled');

        $listed = $this->actingAs($setup['admin'], 'sanctum')
            ->getJson('/api/v1/business/schedule/sessions?gym_id='.$setup['gymId'].'&from_date=2026-11-09&to_date=2026-11-15');

        $listed->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sessionId)
            ->assertJsonPath('data.0.status', 'cancelled');
    }

    public function test_permissions_tenant_isolation_and_validation_on_generate_list_and_cancel(): void
    {
        $a = $this->createCompanySetup('A');
        $b = $this->createCompanySetup('B');
        $stranger = User::factory()->create();

        $templates = $this->app->make(ClassTemplateRepositoryInterface::class);
        $foreignTemplate = $templates->create($b['companyId'], $b['gymId'], $b['trainerId'], 'Чужое', null, 1, 540, 60, 10);

        $sessions = $this->app->make(ClassSessionRepositoryInterface::class);
        $foreignSession = $sessions->createIfNotExistsFromTemplate(
            $foreignTemplate,
            new DateTimeImmutable('2026-11-09 09:00:00+00:00'),
            new DateTimeImmutable('2026-11-09 10:00:00+00:00'),
        );
        $this->assertNotNull($foreignSession);
        $foreignSessionId = $foreignSession->id();

        $this->getJson('/api/v1/business/schedule/sessions?gym_id='.$a['gymId'].'&from_date=2026-11-09&to_date=2026-11-09')
            ->assertStatus(401);

        $this->postJson('/api/v1/business/schedule/sessions/generate', [
            'gym_id' => $a['gymId'],
            'from_date' => '2026-11-09',
            'to_date' => '2026-11-09',
        ])->assertStatus(401);

        $this->postJson("/api/v1/business/schedule/sessions/{$foreignSessionId}/cancel")->assertStatus(401);

        foreach ([$a['trainerUser'], $stranger] as $actor) {
            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/business/schedule/sessions?gym_id='.$a['gymId'].'&from_date=2026-11-09&to_date=2026-11-09')
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');

            $this->actingAs($actor, 'sanctum')
                ->postJson('/api/v1/business/schedule/sessions/generate', [
                    'gym_id' => $a['gymId'],
                    'from_date' => '2026-11-09',
                    'to_date' => '2026-11-09',
                ])
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');

            $this->actingAs($actor, 'sanctum')
                ->postJson("/api/v1/business/schedule/sessions/{$foreignSessionId}/cancel")
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }

        // Чужой зал при списке и генерации -> 404 NOT_FOUND
        $this->actingAs($a['admin'], 'sanctum')
            ->getJson('/api/v1/business/schedule/sessions?gym_id='.$b['gymId'].'&from_date=2026-11-09&to_date=2026-11-09')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->actingAs($a['admin'], 'sanctum')
            ->postJson('/api/v1/business/schedule/sessions/generate', [
                'gym_id' => $b['gymId'],
                'from_date' => '2026-11-09',
                'to_date' => '2026-11-09',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        // Чужое занятие при отмене -> 404 NOT_FOUND
        $this->actingAs($a['admin'], 'sanctum')
            ->postJson("/api/v1/business/schedule/sessions/{$foreignSessionId}/cancel")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        // Невалидные даты и превышение 62 дней -> 422 VALIDATION_FAILED
        $this->actingAs($a['admin'], 'sanctum')
            ->getJson('/api/v1/business/schedule/sessions?gym_id='.$a['gymId'].'&from_date=2026-11-10&to_date=2026-11-09')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['to_date']]]);

        $this->actingAs($a['admin'], 'sanctum')
            ->postJson('/api/v1/business/schedule/sessions/generate', [
                'gym_id' => $a['gymId'],
                'from_date' => '2026-11-10',
                'to_date' => '2026-11-09',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['to_date']]]);

        $this->actingAs($a['admin'], 'sanctum')
            ->postJson('/api/v1/business/schedule/sessions/generate', [
                'gym_id' => $a['gymId'],
                'from_date' => '2026-11-01',
                'to_date' => '2027-02-01',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['to_date']]]);
    }
}
