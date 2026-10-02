<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use App\Models\User;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Shared\Domain\AuditableEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class FakeAuditableEvent implements AuditableEvent
{
    public function __construct(
        private readonly int $userId,
        private readonly int $companyId
    ) {}

    /** @phpstan-ignore return.unusedType */
    public function userId(): ?int
    {
        return $this->userId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function action(): string
    {
        return 'MEMBERSHIP_ISSUED';
    }

    public function entityType(): string
    {
        return 'Membership';
    }

    public function entityId(): string
    {
        return '123';
    }

    /**
     * @return array<string, mixed>|null
     *
     * @phpstan-ignore return.unusedType
     */
    public function payload(): ?array
    {
        return ['price' => 1000];
    }

    /** @phpstan-ignore return.unusedType */
    public function ipAddress(): ?string
    {
        return '192.168.1.1';
    }
}

final class AuditEventListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_listens_to_auditable_events_and_creates_log(): void
    {
        $user = User::factory()->create();
        $company = CompanyModel::create(['owner_id' => $user->id, 'name' => 'Audit Test']);

        $event = new FakeAuditableEvent($user->id, $company->id);

        Event::dispatch($event);

        $this->assertDatabaseHas('audit_entries', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'action' => 'MEMBERSHIP_ISSUED',
            'entity_type' => 'Membership',
            'entity_id' => '123',
            'ip_address' => '192.168.1.1',
        ]);
    }
}
