<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Audit\Infrastructure\Persistence\AuditEntryModel;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class GymProfileModerationAuditTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, int, int} */
    private function createPendingProfile(): array
    {
        $owner = User::factory()->create();
        $company = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, 'Corp');
        $gym = $this->app->make(GymRepositoryInterface::class)->create($company, 'Gym', null);
        $profile = GymProfile::draft($gym);
        $profile->update('Description', 'Address', null, null, '+992900000000', [], []);
        $profile->submit();
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        return [$owner, $company, $gym];
    }

    /** @return array<string, array{string, string, string}> */
    public static function decisions(): array
    {
        return [
            'approve' => ['approve', 'GYM_PROFILE_APPROVED', 'approved_at'],
            'reject' => ['reject', 'GYM_PROFILE_REJECTED', 'rejected_at'],
        ];
    }

    #[DataProvider('decisions')]
    public function test_real_moderation_request_writes_a_single_audit_entry(
        string $decision,
        string $action,
        string $timeKey,
    ): void {
        [, $company, $gym] = $this->createPendingProfile();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $before = new DateTimeImmutable;
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/platform/gyms/{$gym}/moderate", [
                'decision' => $decision, 'reason' => 'Некорректный адрес.',
            ])->assertOk();

        $this->assertDatabaseCount('audit_entries', 1);
        $entry = AuditEntryModel::query()->sole();
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame($company, $entry->company_id);
        $this->assertSame($action, $entry->action);
        $this->assertSame('GymProfile', $entry->entity_type);
        $this->assertSame((string) $gym, $entry->entity_id);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $entry->created_at->getTimestamp());
        $payload = $entry->payload ?? $this->fail('Audit payload is missing');
        $this->assertSame($company, $payload['company_id']);
        $this->assertArrayHasKey($timeKey, $payload);
        $occurredAt = new DateTimeImmutable($payload[$timeKey]);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $occurredAt->getTimestamp());
        $this->assertLessThanOrEqual(time(), $occurredAt->getTimestamp());
        if ($decision === 'reject') {
            $this->assertSame('Некорректный адрес.', $payload['reason']);
        } else {
            $this->assertArrayNotHasKey('reason', $payload);
            $this->assertSame($response->json('data.published_at'), $payload[$timeKey]);
        }
    }

    public function test_denied_invalid_and_repeated_requests_do_not_create_audit_entries(): void
    {
        [$owner, , $gym] = $this->createPendingProfile();
        $url = "/api/v1/platform/gyms/{$gym}/moderate";
        $this->postJson($url, ['decision' => 'approve'])->assertUnauthorized();
        $this->actingAs($owner, 'sanctum')->postJson($url, ['decision' => 'approve'])->assertForbidden();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin, 'sanctum')->postJson($url, ['decision' => 'reject'])->assertUnprocessable();
        $this->postJson('/api/v1/platform/gyms/999999/moderate', ['decision' => 'approve'])->assertNotFound();
        $this->assertDatabaseCount('audit_entries', 0);

        $this->postJson($url, ['decision' => 'approve'])->assertOk();
        $this->postJson($url, ['decision' => 'approve'])->assertBadRequest();
        $this->assertDatabaseCount('audit_entries', 1);
    }

    public function test_resubmission_and_other_company_decision_preserve_previous_audit_entries(): void
    {
        [, $company, $gym] = $this->createPendingProfile();
        [, $otherCompany, $otherGym] = $this->createPendingProfile();
        $firstAdmin = User::factory()->create(['is_platform_admin' => true]);
        $secondAdmin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($firstAdmin, 'sanctum')
            ->postJson("/api/v1/platform/gyms/{$gym}/moderate", [
                'decision' => 'reject', 'reason' => 'Уточните адрес.',
            ])->assertOk();
        $firstEntry = AuditEntryModel::query()->sole()->getAttributes();

        $repo = $this->app->make(GymProfileRepositoryInterface::class);
        $profile = $repo->findByGymId($gym) ?? $this->fail('Profile not found');
        $profile->submit();
        $repo->save($profile);
        $this->actingAs($secondAdmin, 'sanctum')
            ->postJson("/api/v1/platform/gyms/{$gym}/moderate", ['decision' => 'approve'])->assertOk();
        $this->postJson("/api/v1/platform/gyms/{$otherGym}/moderate", ['decision' => 'approve'])->assertOk();

        $this->assertDatabaseCount('audit_entries', 3);
        $this->assertSame($firstEntry, AuditEntryModel::query()->oldest('id')->firstOrFail()->getAttributes());
        $this->assertSame(2, AuditEntryModel::query()->where('company_id', $company)->count());
        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $company, 'user_id' => $secondAdmin->id,
            'action' => 'GYM_PROFILE_APPROVED', 'entity_id' => (string) $gym,
        ]);
        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $otherCompany, 'user_id' => $secondAdmin->id,
            'action' => 'GYM_PROFILE_APPROVED', 'entity_id' => (string) $otherGym,
        ]);
    }
}
