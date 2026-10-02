<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use App\Models\User;
use App\Modules\Audit\Domain\AuditRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_audit_entry(): void
    {
        $user = User::factory()->create();
        $company = CompanyModel::create(['owner_id' => $user->id, 'name' => 'Test Company']);

        /** @var AuditRepositoryInterface $repo */
        $repo = $this->app->make(AuditRepositoryInterface::class);

        $id = $repo->create(
            userId: $user->id,
            companyId: $company->id,
            action: 'USER_LOGIN',
            entityType: 'User',
            entityId: (string) $user->id,
            payload: ['ip' => '127.0.0.1', 'device' => 'iPhone'],
            ipAddress: '127.0.0.1'
        );

        $this->assertDatabaseHas('audit_entries', [
            'id' => $id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'action' => 'USER_LOGIN',
            'entity_type' => 'User',
            'entity_id' => (string) $user->id,
            'ip_address' => '127.0.0.1',
        ]);
    }
}
