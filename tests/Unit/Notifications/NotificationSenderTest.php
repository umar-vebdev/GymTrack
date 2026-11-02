<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Modules\Notifications\Application\NotificationSender;
use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationLogEntry;
use App\Modules\Notifications\Domain\NotificationLogRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Domain\NotificationType;
use App\Modules\Notifications\Domain\PushGatewayInterface;
use App\Modules\Notifications\Domain\SmsGatewayInterface;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use RuntimeException;
use Tests\TestCase;

final class InMemoryNotificationLogRepository implements NotificationLogRepositoryInterface
{
    /** @var array<int, NotificationLogEntry> */
    public array $entries = [];

    private int $nextId = 1;

    public function create(NotificationLogEntry $entry): int
    {
        $id = $this->nextId++;
        $this->entries[$id] = new NotificationLogEntry(
            companyId: $entry->companyId,
            userId: $entry->userId,
            clientId: $entry->clientId,
            channel: $entry->channel,
            type: $entry->type,
            recipient: $entry->recipient,
            title: $entry->title,
            body: $entry->body,
            entityType: $entry->entityType,
            entityId: $entry->entityId,
            status: $entry->status,
            error: $entry->error,
            sentAt: $entry->sentAt,
            id: $id
        );

        return $id;
    }

    public function markSent(int $id, DateTimeImmutable $sentAt): void
    {
        if (isset($this->entries[$id])) {
            $e = $this->entries[$id];
            $this->entries[$id] = new NotificationLogEntry(
                companyId: $e->companyId,
                userId: $e->userId,
                clientId: $e->clientId,
                channel: $e->channel,
                type: $e->type,
                recipient: $e->recipient,
                title: $e->title,
                body: $e->body,
                entityType: $e->entityType,
                entityId: $e->entityId,
                status: NotificationStatus::Sent,
                error: null,
                sentAt: $sentAt,
                id: $id
            );
        }
    }

    public function markFailed(int $id, string $errorMessage): void
    {
        if (isset($this->entries[$id])) {
            $e = $this->entries[$id];
            $this->entries[$id] = new NotificationLogEntry(
                companyId: $e->companyId,
                userId: $e->userId,
                clientId: $e->clientId,
                channel: $e->channel,
                type: $e->type,
                recipient: $e->recipient,
                title: $e->title,
                body: $e->body,
                entityType: $e->entityType,
                entityId: $e->entityId,
                status: NotificationStatus::Failed,
                error: $errorMessage,
                sentAt: null,
                id: $id
            );
        }
    }

    public function alreadySent(
        NotificationType $type,
        string $entityType,
        int $entityId,
        ?DateTimeImmutable $since = null
    ): bool {
        foreach ($this->entries as $entry) {
            if (
                $entry->type === $type
                && $entry->entityType === $entityType
                && $entry->entityId === $entityId
                && $entry->status === NotificationStatus::Sent
            ) {
                if ($since !== null && $entry->sentAt !== null) {
                    if ($entry->sentAt < $since) {
                        continue;
                    }
                }

                return true;
            }
        }

        return false;
    }
}

final class FakeClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

final class FakePushGateway implements PushGatewayInterface
{
    /** @var list<array{recipient: string, title: string, body: string, payload: array<string, mixed>}> */
    public array $sent = [];

    public bool $shouldFail = false;

    public function send(string $recipient, string $title, string $body, array $payload = []): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('Push delivery error');
        }

        $this->sent[] = [
            'recipient' => $recipient,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
        ];
    }
}

final class FakeSmsGateway implements SmsGatewayInterface
{
    /** @var list<array{phone: string, message: string}> */
    public array $sent = [];

    public bool $shouldFail = false;

    public function send(string $phone, string $message): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('SMS provider error');
        }

        $this->sent[] = [
            'phone' => $phone,
            'message' => $message,
        ];
    }
}

final class NotificationSenderTest extends TestCase
{
    private InMemoryNotificationLogRepository $repo;

    private FakePushGateway $pushGateway;

    private FakeSmsGateway $smsGateway;

    private FakeClock $clock;

    private NotificationSender $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new InMemoryNotificationLogRepository;
        $this->pushGateway = new FakePushGateway;
        $this->smsGateway = new FakeSmsGateway;
        $this->clock = new FakeClock(new DateTimeImmutable('2026-11-02 12:00:00'));

        $this->sender = new NotificationSender(
            pushGateway: $this->pushGateway,
            smsGateway: $this->smsGateway,
            logs: $this->repo,
            clock: $this->clock
        );
    }

    public function test_send_sms_successful(): void
    {
        $logId = $this->sender->sendSms(
            companyId: 1,
            userId: 2,
            clientId: 3,
            phone: '+992900000001',
            message: 'Ваш визит подтверждён',
            type: NotificationType::VisitRegistered,
            entityType: 'visit',
            entityId: 100
        );

        $this->assertSame(1, $logId);
        $this->assertCount(1, $this->smsGateway->sent);
        $this->assertSame('+992900000001', $this->smsGateway->sent[0]['phone']);
        $this->assertSame('Ваш визит подтверждён', $this->smsGateway->sent[0]['message']);

        $entry = $this->repo->entries[$logId];
        $this->assertSame(NotificationStatus::Sent, $entry->status);
        $this->assertSame(NotificationChannel::Sms, $entry->channel);
        $this->assertSame('2026-11-02 12:00:00', $entry->sentAt?->format('Y-m-d H:i:s'));
        $this->assertNull($entry->error);

        $this->assertTrue(
            $this->repo->alreadySent(NotificationType::VisitRegistered, 'visit', 100)
        );
    }

    public function test_send_sms_failure_marks_log_failed(): void
    {
        $this->smsGateway->shouldFail = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMS provider error');

        try {
            $this->sender->sendSms(
                companyId: 1,
                userId: 2,
                clientId: 3,
                phone: '+992900000001',
                message: 'Test message',
                type: NotificationType::VisitRegistered
            );
        } finally {
            $this->assertCount(1, $this->repo->entries);
            $entry = $this->repo->entries[1];
            $this->assertSame(NotificationStatus::Failed, $entry->status);
            $this->assertSame('SMS provider error', $entry->error);
            $this->assertNull($entry->sentAt);
        }
    }

    public function test_send_push_successful(): void
    {
        $logId = $this->sender->sendPush(
            companyId: 1,
            userId: 2,
            clientId: 3,
            recipient: 'token-device-xyz',
            title: 'Абонемент',
            body: 'Абонемент активирован',
            type: NotificationType::MembershipRequestPaid,
            entityType: 'membership_request',
            entityId: 50,
            payload: ['key' => 'val']
        );

        $this->assertSame(1, $logId);
        $this->assertCount(1, $this->pushGateway->sent);
        $this->assertSame('token-device-xyz', $this->pushGateway->sent[0]['recipient']);
        $this->assertSame('Абонемент', $this->pushGateway->sent[0]['title']);
        $this->assertSame('Абонемент активирован', $this->pushGateway->sent[0]['body']);
        $this->assertSame(['key' => 'val'], $this->pushGateway->sent[0]['payload']);

        $entry = $this->repo->entries[$logId];
        $this->assertSame(NotificationStatus::Sent, $entry->status);
        $this->assertSame(NotificationChannel::Push, $entry->channel);
        $this->assertSame('2026-11-02 12:00:00', $entry->sentAt?->format('Y-m-d H:i:s'));
    }

    public function test_send_push_failure_marks_log_failed(): void
    {
        $this->pushGateway->shouldFail = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Push delivery error');

        try {
            $this->sender->sendPush(
                companyId: 1,
                userId: 2,
                clientId: 3,
                recipient: 'token-xyz',
                title: 'Title',
                body: 'Body',
                type: NotificationType::MembershipExpiring7Days
            );
        } finally {
            $this->assertCount(1, $this->repo->entries);
            $entry = $this->repo->entries[1];
            $this->assertSame(NotificationStatus::Failed, $entry->status);
            $this->assertSame('Push delivery error', $entry->error);
        }
    }
}
