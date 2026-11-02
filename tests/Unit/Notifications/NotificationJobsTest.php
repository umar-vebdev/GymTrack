<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Modules\Notifications\Application\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Application\Jobs\SendSmsNotificationJob;
use App\Modules\Notifications\Application\NotificationSender;
use App\Modules\Notifications\Domain\DeviceTokenRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationType;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class InMemoryDeviceTokenRepository implements DeviceTokenRepositoryInterface
{
    /** @var array<int, list<string>> */
    public array $userTokens = [];

    public function upsertToken(int $userId, string $token, string $platform): void
    {
        $this->userTokens[$userId][] = $token;
    }

    public function deleteToken(int $userId, string $token): void
    {
        if (isset($this->userTokens[$userId])) {
            $this->userTokens[$userId] = array_values(
                array_filter($this->userTokens[$userId], fn (string $t) => $t !== $token)
            );
        }
    }

    public function findTokensForUser(int $userId): array
    {
        return $this->userTokens[$userId] ?? [];
    }
}

final class NotificationJobsTest extends TestCase
{
    private InMemoryNotificationLogRepository $logRepo;

    private FakePushGateway $pushGateway;

    private FakeSmsGateway $smsGateway;

    private FakeClock $clock;

    private NotificationSender $sender;

    private InMemoryDeviceTokenRepository $deviceTokenRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logRepo = new InMemoryNotificationLogRepository;
        $this->pushGateway = new FakePushGateway;
        $this->smsGateway = new FakeSmsGateway;
        $this->clock = new FakeClock(new \DateTimeImmutable('2026-11-02 12:00:00'));

        $this->sender = new NotificationSender(
            pushGateway: $this->pushGateway,
            smsGateway: $this->smsGateway,
            logs: $this->logRepo,
            clock: $this->clock
        );

        $this->deviceTokenRepo = new InMemoryDeviceTokenRepository;
    }

    public function test_send_sms_job_calls_sender(): void
    {
        $job = new SendSmsNotificationJob(
            companyId: 10,
            userId: 20,
            clientId: 30,
            phone: '+992900000001',
            message: 'СМС сообщение',
            type: NotificationType::VisitRegistered,
            entityType: 'visit',
            entityId: 123
        );

        $job->handle($this->sender);

        $this->assertCount(1, $this->smsGateway->sent);
        $this->assertSame('+992900000001', $this->smsGateway->sent[0]['phone']);
        $this->assertSame('СМС сообщение', $this->smsGateway->sent[0]['message']);
        $this->assertCount(1, $this->logRepo->entries);
        $this->assertSame(10, $this->logRepo->entries[1]->companyId);
        $this->assertSame('visit', $this->logRepo->entries[1]->entityType);
        $this->assertSame(123, $this->logRepo->entries[1]->entityId);
    }

    public function test_send_push_job_with_direct_token(): void
    {
        $job = new SendPushNotificationJob(
            companyId: 10,
            userId: 20,
            clientId: 30,
            title: 'Push заголовок',
            body: 'Push тело',
            type: NotificationType::MembershipRequestPaid,
            recipientToken: 'direct-token-xyz',
            entityType: 'request',
            entityId: 456,
            payload: ['action' => 'open_membership']
        );

        $job->handle($this->sender, $this->deviceTokenRepo);

        $this->assertCount(1, $this->pushGateway->sent);
        $this->assertSame('direct-token-xyz', $this->pushGateway->sent[0]['recipient']);
        $this->assertSame('Push заголовок', $this->pushGateway->sent[0]['title']);
        $this->assertSame(['action' => 'open_membership'], $this->pushGateway->sent[0]['payload']);
    }

    public function test_send_push_job_resolves_all_user_tokens(): void
    {
        $this->deviceTokenRepo->userTokens[20] = ['token-iphone', 'token-ipad'];

        $job = new SendPushNotificationJob(
            companyId: 10,
            userId: 20,
            clientId: 30,
            title: 'Много устройств',
            body: 'Уведомление для всех устройств',
            type: NotificationType::MembershipExpiring7Days,
            entityType: 'membership',
            entityId: 789
        );

        $job->handle($this->sender, $this->deviceTokenRepo);

        $this->assertCount(2, $this->pushGateway->sent);
        $this->assertSame('token-iphone', $this->pushGateway->sent[0]['recipient']);
        $this->assertSame('token-ipad', $this->pushGateway->sent[1]['recipient']);
        $this->assertCount(2, $this->logRepo->entries);
    }

    public function test_send_push_job_skips_when_user_has_no_tokens(): void
    {
        $job = new SendPushNotificationJob(
            companyId: 10,
            userId: 99,
            clientId: 30,
            title: 'Нет токенов',
            body: 'Тело',
            type: NotificationType::MembershipExpiring1Day
        );

        $job->handle($this->sender, $this->deviceTokenRepo);

        $this->assertCount(0, $this->pushGateway->sent);
        $this->assertCount(0, $this->logRepo->entries);
    }

    public function test_jobs_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        SendPushNotificationJob::dispatch(
            companyId: 1,
            userId: 2,
            clientId: 3,
            title: 'Заголовок',
            body: 'Текст',
            type: NotificationType::VisitRegistered
        );

        SendSmsNotificationJob::dispatch(
            companyId: 1,
            userId: 2,
            clientId: 3,
            phone: '+992900000000',
            message: 'Текст',
            type: NotificationType::VisitRegistered
        );

        Queue::assertPushed(SendPushNotificationJob::class);
        Queue::assertPushed(SendSmsNotificationJob::class);
    }
}
