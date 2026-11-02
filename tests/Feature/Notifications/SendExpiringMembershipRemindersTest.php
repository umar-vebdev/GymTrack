<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Notifications\Application\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Application\Jobs\SendSmsNotificationJob;
use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationLogEntry;
use App\Modules\Notifications\Domain\NotificationLogRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Domain\NotificationType;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SendExpiringMembershipRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(): int
    {
        $owner = User::factory()->create();

        return CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Reminder Test Gym'])->id;
    }

    private function bindFixedClock(string $time): void
    {
        $fixed = new DateTimeImmutable($time);
        $this->app->instance(Clock::class, new class($fixed) implements Clock
        {
            public function __construct(private readonly DateTimeImmutable $now) {}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        });
    }

    private function issueMembership(int $companyId, int $clientId, string $issuedAt, int $durationDays): int
    {
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $rules = MembershipRules::create(
            kind: MembershipKind::Duration,
            startMode: StartMode::OnSale,
            durationDays: $durationDays
        );
        $typeId = $types->create($companyId, 'Standard Plan', new Money(15000, 'TJS'), $rules);

        /** @var MembershipIssuerInterface $issuer */
        $issuer = $this->app->make(MembershipIssuerInterface::class);

        return $issuer->issue($companyId, $clientId, $typeId, new DateTimeImmutable($issuedAt));
    }

    public function test_command_sends_reminders_for_7_and_1_days_ahead(): void
    {
        Queue::fake();

        $this->bindFixedClock('2026-11-02 10:00:00');

        $companyId = $this->createCompany();

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);

        // Client A with user account: expires in 7 days (issued 2026-10-10, duration 30 -> ends 2026-11-09)
        $userA = User::factory()->create();
        $clientA = $clients->create($companyId, 'Client SevenDays', '+992900111111', $userA->id);
        $membershipA = $this->issueMembership($companyId, $clientA, '2026-10-10 10:00:00', durationDays: 30);

        // Client B without user account: expires in 1 day (issued 2026-10-04, duration 30 -> ends 2026-11-03)
        $clientB = $clients->create($companyId, 'Client OneDay', '+992900222222', null);
        $membershipB = $this->issueMembership($companyId, $clientB, '2026-10-04 10:00:00', durationDays: 30);

        // Client C: expires in 4 days (issued 2026-10-07, duration 30 -> ends 2026-11-06) - NOT 7 and NOT 1
        $clientC = $clients->create($companyId, 'Client FourDays', '+992900333333');
        $this->issueMembership($companyId, $clientC, '2026-10-07 10:00:00', durationDays: 30);

        $exitCode = Artisan::call('notifications:send-expiring-reminders');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Отправлено напоминаний об окончании абонементов: 2', Artisan::output());

        // Client A gets push for 7 days
        Queue::assertPushed(SendPushNotificationJob::class, function (SendPushNotificationJob $job) use ($companyId, $userA, $clientA, $membershipA) {
            return $job->companyId === $companyId
                && $job->userId === $userA->id
                && $job->clientId === $clientA
                && $job->type === NotificationType::MembershipExpiring7Days
                && $job->entityType === 'membership'
                && $job->entityId === $membershipA
                && str_contains($job->body, '7 дней');
        });

        // Client B gets SMS for 1 day
        Queue::assertPushed(SendSmsNotificationJob::class, function (SendSmsNotificationJob $job) use ($companyId, $clientB, $membershipB) {
            return $job->companyId === $companyId
                && $job->phone === '+992900222222'
                && $job->clientId === $clientB
                && $job->type === NotificationType::MembershipExpiring1Day
                && $job->entityType === 'membership'
                && $job->entityId === $membershipB
                && str_contains($job->message, '1 день');
        });
    }

    public function test_command_is_idempotent_and_skips_already_sent_reminders(): void
    {
        Queue::fake();

        $this->bindFixedClock('2026-11-02 10:00:00');

        $companyId = $this->createCompany();

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);

        $userA = User::factory()->create();
        $clientA = $clients->create($companyId, 'Client SevenDays', '+992900111111', $userA->id);
        $membershipA = $this->issueMembership($companyId, $clientA, '2026-10-10 10:00:00', durationDays: 30);

        /** @var NotificationLogRepositoryInterface $logs */
        $logs = $this->app->make(NotificationLogRepositoryInterface::class);

        // Pre-record that reminder for 7 days was already sent
        $logs->create(new NotificationLogEntry(
            companyId: $companyId,
            userId: $userA->id,
            clientId: $clientA,
            channel: NotificationChannel::Push,
            type: NotificationType::MembershipExpiring7Days,
            recipient: 'token',
            title: 'Окончание',
            body: 'Напоминание',
            entityType: 'membership',
            entityId: $membershipA,
            status: NotificationStatus::Sent,
            sentAt: new DateTimeImmutable('2026-11-02 08:00:00')
        ));

        $exitCode = Artisan::call('notifications:send-expiring-reminders');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Отправлено напоминаний об окончании абонементов: 0', Artisan::output());

        Queue::assertNothingPushed();
    }
}
