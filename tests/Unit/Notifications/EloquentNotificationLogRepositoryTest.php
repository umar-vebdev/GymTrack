<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationLogEntry;
use App\Modules\Notifications\Domain\NotificationLogRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Domain\NotificationType;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentNotificationLogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_updates_notification_log(): void
    {
        $user = User::factory()->create();
        $company = CompanyModel::create(['owner_id' => $user->id, 'name' => 'Notify Company']);

        /** @var NotificationLogRepositoryInterface $repo */
        $repo = $this->app->make(NotificationLogRepositoryInterface::class);

        $entry = new NotificationLogEntry(
            companyId: $company->id,
            userId: $user->id,
            clientId: null,
            channel: NotificationChannel::Push,
            type: NotificationType::VisitRegistered,
            recipient: 'token-abc',
            title: 'Визит',
            body: 'Посещение подтверждено',
            entityType: 'visit',
            entityId: 42,
            status: NotificationStatus::Pending
        );

        $id = $repo->create($entry);

        $this->assertDatabaseHas('notifications_logs', [
            'id' => $id,
            'company_id' => $company->id,
            'user_id' => $user->id,
            'channel' => 'push',
            'type' => 'visit_registered',
            'recipient' => 'token-abc',
            'status' => 'pending',
            'entity_type' => 'visit',
            'entity_id' => 42,
        ]);

        $this->assertFalse($repo->alreadySent(NotificationType::VisitRegistered, 'visit', 42));

        $sentAt = new DateTimeImmutable('2026-11-02 15:30:00');
        $repo->markSent($id, $sentAt);

        $this->assertDatabaseHas('notifications_logs', [
            'id' => $id,
            'status' => 'sent',
            'sent_at' => '2026-11-02 15:30:00',
        ]);

        $this->assertTrue($repo->alreadySent(NotificationType::VisitRegistered, 'visit', 42));

        // Test markFailed
        $entry2 = new NotificationLogEntry(
            companyId: $company->id,
            userId: $user->id,
            clientId: null,
            channel: NotificationChannel::Sms,
            type: NotificationType::MembershipRequestPaid,
            recipient: '+992900000000',
            title: null,
            body: 'Оплачено',
            entityType: 'membership_request',
            entityId: 99
        );
        $id2 = $repo->create($entry2);
        $repo->markFailed($id2, 'Connection timeout');

        $this->assertDatabaseHas('notifications_logs', [
            'id' => $id2,
            'status' => 'failed',
            'error' => 'Connection timeout',
        ]);
        $this->assertFalse($repo->alreadySent(NotificationType::MembershipRequestPaid, 'membership_request', 99));
    }
}
