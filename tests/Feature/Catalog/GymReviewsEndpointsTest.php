<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitStatus;
use App\Modules\Visits\Infrastructure\Persistence\VisitModel;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class GymReviewsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $publishedGymId;

    private string $publishedGymPublicId;

    private int $secondPublishedGymId;

    private string $unpublishedGymPublicId;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->companyId = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Alpha Fitness'])->id;

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        /** @var GymProfileRepositoryInterface $profiles */
        $profiles = $this->app->make(GymProfileRepositoryInterface::class);

        $this->publishedGymId = $this->createGym($gyms, $profiles, $this->companyId, 'Main Arena', publish: true);
        $this->publishedGymPublicId = (string) GymModel::query()->withoutGlobalScopes()->findOrFail($this->publishedGymId)->public_id;

        $this->secondPublishedGymId = $this->createGym($gyms, $profiles, $this->companyId, 'Second Arena', publish: true);

        $unpublishedGymId = $this->createGym($gyms, $profiles, $this->companyId, 'Draft Arena', publish: false);
        $this->unpublishedGymPublicId = (string) GymModel::query()->withoutGlobalScopes()->findOrFail($unpublishedGymId)->public_id;
    }

    public function test_unauthenticated_client_cannot_submit_review(): void
    {
        $response = $this->putJson("/api/v1/client/gyms/{$this->publishedGymPublicId}/review", [
            'rating' => 5,
            'comment' => 'Супер',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_missing_or_unpublished_gym_returns_404(): void
    {
        $client = User::factory()->create();
        Sanctum::actingAs($client);

        $this->getJson('/api/v1/public/gyms/01NOTFOUND0000000000000000/reviews')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->getJson("/api/v1/public/gyms/{$this->unpublishedGymPublicId}/reviews")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->putJson("/api/v1/client/gyms/{$this->unpublishedGymPublicId}/review", [
            'rating' => 5,
        ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_client_without_completed_visit_in_target_gym_receives_403(): void
    {
        $clientUser = User::factory()->create(['phone' => '+992900111333', 'is_phone_verified' => true]);
        Sanctum::actingAs($clientUser);

        // Посещение есть только в другом зале, и только отменённое — в целевом зале
        $issued = $this->issueClientMembership($clientUser, '+992900111333');
        VisitModel::query()->withoutGlobalScopes()->create([
            'company_id' => $this->companyId,
            'gym_id' => $this->secondPublishedGymId,
            'client_id' => $issued['clientId'],
            'membership_id' => $issued['membershipId'],
            'method' => VisitMethod::Qr->value,
            'status' => VisitStatus::Completed->value,
            'visited_at' => '2026-11-14 10:00:00',
            'visit_date' => '2026-11-14',
            'daily_entry_number' => 1,
        ]);
        VisitModel::query()->withoutGlobalScopes()->create([
            'company_id' => $this->companyId,
            'gym_id' => $this->publishedGymId,
            'client_id' => $issued['clientId'],
            'membership_id' => $issued['membershipId'],
            'method' => VisitMethod::Qr->value,
            'status' => VisitStatus::Cancelled->value,
            'visited_at' => '2026-11-15 10:00:00',
            'visit_date' => '2026-11-15',
            'daily_entry_number' => 1,
        ]);

        $this->putJson("/api/v1/client/gyms/{$this->publishedGymPublicId}/review", [
            'rating' => 5,
            'comment' => 'Пытаюсь оценить чужой зал',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'VISIT_REQUIRED_FOR_REVIEW');
    }

    public function test_validation_rules_for_upsert_and_list_reviews(): void
    {
        $clientUser = User::factory()->create();
        Sanctum::actingAs($clientUser);

        $this->putJson("/api/v1/client/gyms/{$this->publishedGymPublicId}/review", [
            'rating' => 6,
            'comment' => str_repeat('a', 1001),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['rating', 'comment']]]);

        $this->getJson("/api/v1/public/gyms/{$this->publishedGymPublicId}/reviews?per_page=101")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_client_can_create_and_update_review_and_catalog_reflects_rating(): void
    {
        $client1 = User::factory()->create(['name' => 'Алишер', 'phone' => '+992900222111', 'is_phone_verified' => true]);
        $client2 = User::factory()->create(['name' => 'Зарина', 'phone' => '+992900222222', 'is_phone_verified' => true]);

        $this->recordCompletedVisit($client1, '+992900222111', $this->publishedGymId);
        $this->recordCompletedVisit($client2, '+992900222222', $this->publishedGymId);

        Sanctum::actingAs($client1);
        $created = $this->putJson("/api/v1/client/gyms/{$this->publishedGymPublicId}/review", [
            'rating' => 4,
            'comment' => 'Хорошие тренажёры',
        ]);
        $created->assertStatus(200)
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.comment', 'Хорошие тренажёры')
            ->assertJsonPath('data.author_name', 'Алишер');

        /** @var string $reviewPublicId */
        $reviewPublicId = $created->json('data.id');
        $this->assertSame(26, strlen($reviewPublicId));

        // Повторный PUT от того же клиента обновляет отзыв
        $updated = $this->putJson("/api/v1/client/gyms/{$this->publishedGymPublicId}/review", [
            'rating' => 5,
            'comment' => 'Обновил оценку: всё супер!',
        ]);
        $updated->assertStatus(200)
            ->assertJsonPath('data.id', $reviewPublicId)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Обновил оценку: всё супер!');

        Sanctum::actingAs($client2);
        $this->putJson("/api/v1/client/gyms/{$this->publishedGymPublicId}/review", [
            'rating' => 4,
            'comment' => null,
        ])->assertStatus(200);

        // Публичный список отзывов с пагинацией
        $listPage1 = $this->getJson("/api/v1/public/gyms/{$this->publishedGymPublicId}/reviews?page=1&per_page=1");
        $listPage1->assertStatus(200)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.author_name', 'Зарина')
            ->assertJsonPath('data.0.rating', 4);

        $listPage2 = $this->getJson("/api/v1/public/gyms/{$this->publishedGymPublicId}/reviews?page=2&per_page=1");
        $listPage2->assertStatus(200)
            ->assertJsonPath('data.0.id', $reviewPublicId)
            ->assertJsonPath('data.0.author_name', 'Алишер')
            ->assertJsonPath('data.0.rating', 5);

        // Средний рейтинг и счётчик видны в карточке и списке каталога
        $cardResponse = $this->getJson("/api/v1/public/gyms/{$this->publishedGymPublicId}");
        $cardResponse->assertStatus(200)
            ->assertJsonPath('data.rating_avg', 4.5)
            ->assertJsonPath('data.reviews_count', 2);

        $catalogResponse = $this->getJson('/api/v1/public/gyms');
        $catalogResponse->assertStatus(200);
        /** @var list<array<string, mixed>> $items */
        $items = $catalogResponse->json('data');
        $byId = [];
        foreach ($items as $item) {
            /** @var string $id */
            $id = $item['id'];
            $byId[$id] = $item;
        }
        $this->assertEquals(4.5, $byId[$this->publishedGymPublicId]['rating_avg']);
        $this->assertSame(2, $byId[$this->publishedGymPublicId]['reviews_count']);
    }

    private function createGym(
        GymRepositoryInterface $gyms,
        GymProfileRepositoryInterface $profiles,
        int $companyId,
        string $name,
        bool $publish,
    ): int {
        $gymId = $gyms->create($companyId, $name, 'ул. Рудаки, 10');
        $profile = GymProfile::draft($gymId);
        $profile->update('Описание', 'ул. Рудаки, 10', 38.5598, 68.7870, '+992900000111', [], []);
        if ($publish) {
            $profile->submit();
            $profile->approve(new DateTimeImmutable('2026-11-15 10:00:00 UTC'));
            $profile->setPublication(true);
        }
        $profiles->save($profile);

        return $gymId;
    }

    /**
     * @return array{clientId: int, membershipId: int}
     */
    private function issueClientMembership(User $user, string $phone): array
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($this->companyId, $user->name ?? 'Клиент', $phone, $user->id);

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $this->companyId,
            'Безлимит',
            new Money(30000, 'TJS'),
            MembershipRules::create(
                kind: MembershipKind::Duration,
                startMode: StartMode::OnSale,
                durationDays: 30,
                dailyLimit: 1,
            ),
        );

        /** @var MembershipRepositoryInterface $memberships */
        $memberships = $this->app->make(MembershipRepositoryInterface::class);
        $membershipId = $memberships->create($this->companyId, $clientId, $typeId, MembershipStatus::Active, null, null, null);

        return ['clientId' => $clientId, 'membershipId' => $membershipId];
    }

    private function recordCompletedVisit(User $user, string $phone, int $gymId): void
    {
        $issued = $this->issueClientMembership($user, $phone);
        VisitModel::query()->withoutGlobalScopes()->create([
            'company_id' => $this->companyId,
            'gym_id' => $gymId,
            'client_id' => $issued['clientId'],
            'membership_id' => $issued['membershipId'],
            'method' => VisitMethod::Qr->value,
            'status' => VisitStatus::Completed->value,
            'visited_at' => '2026-11-15 10:00:00',
            'visit_date' => '2026-11-15',
            'daily_entry_number' => 1,
        ]);
    }
}
