<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Notifications\Application\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Application\Jobs\SendSmsNotificationJob;
use App\Modules\Notifications\Domain\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class SendGymAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $trainer;

    private User $outsider;

    private int $companyId;

    private int $otherCompanyId;

    private User $linkedClientUser;

    private int $linkedClientId;

    private int $phoneOnlyClientId;

    private int $userOnlyClientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->trainer = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->companyId = CompanyModel::create(['owner_id' => $this->owner->id, 'name' => 'Alpha Club'])->id;
        $otherOwner = User::factory()->create();
        $this->otherCompanyId = CompanyModel::create(['owner_id' => $otherOwner->id, 'name' => 'Beta Club'])->id;

        /** @var StaffMemberRepositoryInterface $staff */
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $staff->create($this->owner->id, $this->companyId, Role::OWNER);
        $staff->create($this->admin->id, $this->companyId, Role::ADMIN);
        $staff->create($this->trainer->id, $this->companyId, Role::TRAINER);
        $staff->create($otherOwner->id, $this->otherCompanyId, Role::OWNER);

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);

        $this->linkedClientUser = User::factory()->create(['phone' => '+992900111222', 'is_phone_verified' => true]);
        $this->linkedClientId = $clients->create($this->companyId, 'Самир', '+992900111222', $this->linkedClientUser->id);

        // Клиент только с телефоном (без user_id)
        $this->phoneOnlyClientId = $clients->create($this->companyId, 'Оффлайн Клиент', '+992900333444', null);

        // Клиент с user_id, но с пустым телефоном (для проверки фильтрации SMS)
        $userWithoutPhone = User::factory()->create(['phone' => null]);
        $this->userOnlyClientId = $clients->create($this->companyId, 'Только Push', '', $userWithoutPhone->id);

        // Клиент чужой компании не должен попадать в рассылку
        $foreignUser = User::factory()->create(['phone' => '+992900999888', 'is_phone_verified' => true]);
        $clients->create($this->otherCompanyId, 'Чужой', '+992900999888', $foreignUser->id);
    }

    public function test_unauthenticated_and_non_backoffice_users_are_rejected(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/business/notifications/announcements', [
            'channel' => 'push',
            'title' => 'Новость',
            'body' => 'Текст объявления',
        ])->assertStatus(401);

        Sanctum::actingAs($this->outsider);
        $this->postJson('/api/v1/business/notifications/announcements', [
            'channel' => 'push',
            'title' => 'Новость',
            'body' => 'Текст объявления',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        Sanctum::actingAs($this->trainer);
        $this->postJson('/api/v1/business/notifications/announcements', [
            'channel' => 'sms',
            'title' => 'Новость',
            'body' => 'Текст объявления',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        Queue::assertNothingPushed();
    }

    public function test_validation_rejects_invalid_channel_and_empty_or_overlong_fields(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/business/notifications/announcements', [
            'channel' => 'email',
            'title' => '',
            'body' => str_repeat('x', 1001),
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['channel', 'title', 'body']]]);

        Queue::assertNothingPushed();
    }

    public function test_owner_queues_push_announcement_only_for_company_clients_with_user_id(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/business/notifications/announcements', [
            'channel' => 'push',
            'title' => '  Санитарный день  ',
            'body' => '  В воскресенье зал закрыт до 12:00  ',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.queued_count', 2)
            ->assertJsonPath('data.channel', 'push');

        Queue::assertPushed(SendPushNotificationJob::class, 2);
        Queue::assertNotPushed(SendSmsNotificationJob::class);

        Queue::assertPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job): bool => $job->companyId === $this->companyId
                && $job->clientId === $this->linkedClientId
                && $job->userId === $this->linkedClientUser->id
                && $job->title === 'Санитарный день'
                && $job->body === 'В воскресенье зал закрыт до 12:00'
                && $job->type === NotificationType::GymAnnouncement,
        );

        Queue::assertPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job): bool => $job->companyId === $this->companyId
                && $job->clientId === $this->userOnlyClientId
                && $job->type === NotificationType::GymAnnouncement,
        );
    }

    public function test_admin_queues_sms_announcement_only_for_company_clients_with_non_empty_phone(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/business/notifications/announcements', [
            'channel' => 'sms',
            'title' => 'Новое оборудование',
            'body' => 'Обновили кардио-зону!',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.queued_count', 2)
            ->assertJsonPath('data.channel', 'sms');

        Queue::assertPushed(SendSmsNotificationJob::class, 2);
        Queue::assertNotPushed(SendPushNotificationJob::class);

        Queue::assertPushed(
            SendSmsNotificationJob::class,
            fn (SendSmsNotificationJob $job): bool => $job->companyId === $this->companyId
                && $job->clientId === $this->linkedClientId
                && $job->phone === '+992900111222'
                && $job->message === 'Новое оборудование: Обновили кардио-зону!'
                && $job->type === NotificationType::GymAnnouncement,
        );

        Queue::assertPushed(
            SendSmsNotificationJob::class,
            fn (SendSmsNotificationJob $job): bool => $job->companyId === $this->companyId
                && $job->clientId === $this->phoneOnlyClientId
                && $job->userId === null
                && $job->phone === '+992900333444'
                && $job->type === NotificationType::GymAnnouncement,
        );
    }
}
