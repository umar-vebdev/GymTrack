<?php

declare(strict_types=1);

namespace Tests\Unit\Companies;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Contracts\GymReviewCatalogContractInterface;
use App\Modules\Companies\Contracts\PublicGymFilter;
use App\Modules\Companies\Contracts\PublicGymQueryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Modules\Companies\Infrastructure\Persistence\GymReviewModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Visits\Contracts\ClientGymVisitCheckInterface;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitStatus;
use App\Modules\Visits\Infrastructure\Persistence\VisitModel;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class GymReviewRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_visit_check_requires_completed_visit_in_specific_gym(): void
    {
        $owner = User::factory()->create();
        $companyId = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Alpha'])->id;
        $otherCompanyId = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Beta'])->id;

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymA = $gyms->create($companyId, 'Gym A', 'ул. Рудаки, 1');
        $gymB = $gyms->create($companyId, 'Gym B', 'ул. Сомони, 2');
        $foreignGym = $gyms->create($otherCompanyId, 'Foreign', 'ул. Айни, 3');

        $user = User::factory()->create(['phone' => '+992900111222', 'is_phone_verified' => true]);
        /** @var ClientGymVisitCheckInterface $visitCheck */
        $visitCheck = $this->app->make(ClientGymVisitCheckInterface::class);

        // Нет карточки клиента -> false
        $this->assertFalse($visitCheck->hasCompletedVisitInGym($user->id, $companyId, $gymA));

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($companyId, 'Али', '+992900111222', $user->id);

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $companyId,
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
        $membershipId = $memberships->create($companyId, $clientId, $typeId, MembershipStatus::Active, null, null, null);

        // Отменённое посещение не даёт права на отзыв
        VisitModel::query()->withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'gym_id' => $gymA,
            'client_id' => $clientId,
            'membership_id' => $membershipId,
            'method' => VisitMethod::Qr->value,
            'status' => VisitStatus::Cancelled->value,
            'visited_at' => '2026-11-14 10:00:00',
            'visit_date' => '2026-11-14',
            'daily_entry_number' => 1,
        ]);

        $this->assertFalse($visitCheck->hasCompletedVisitInGym($user->id, $companyId, $gymA));

        // Завершённое посещение в Gym A даёт право только для Gym A и своей компании
        VisitModel::query()->withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'gym_id' => $gymA,
            'client_id' => $clientId,
            'membership_id' => $membershipId,
            'method' => VisitMethod::Qr->value,
            'status' => VisitStatus::Completed->value,
            'visited_at' => '2026-11-15 10:00:00',
            'visit_date' => '2026-11-15',
            'daily_entry_number' => 1,
        ]);

        $this->assertTrue($visitCheck->hasCompletedVisitInGym($user->id, $companyId, $gymA));
        $this->assertFalse($visitCheck->hasCompletedVisitInGym($user->id, $companyId, $gymB));
        $this->assertFalse($visitCheck->hasCompletedVisitInGym($user->id, $otherCompanyId, $gymA));
        $this->assertFalse($visitCheck->hasCompletedVisitInGym($user->id, $otherCompanyId, $foreignGym));
    }

    public function test_upsert_validation_single_row_and_pagination(): void
    {
        $owner = User::factory()->create();
        $companyId = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Alpha'])->id;
        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($companyId, 'Main Gym', 'ул. Рудаки, 10');

        $user1 = User::factory()->create(['name' => 'Фарход']);
        $user2 = User::factory()->create(['name' => '   ']);

        /** @var GymReviewCatalogContractInterface $reviews */
        $reviews = $this->app->make(GymReviewCatalogContractInterface::class);

        try {
            $reviews->upsertReview($companyId, $gymId, $user1->id, 0, 'Плохо');
            $this->fail('Ожидалось исключение для оценки 0.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            $reviews->upsertReview($companyId, $gymId, $user1->id, 6, 'Супер');
            $this->fail('Ожидалось исключение для оценки 6.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            $reviews->upsertReview($companyId, $gymId, $user1->id, 5, str_repeat('a', 1001));
            $this->fail('Ожидалось исключение для комментария длиннее 1000 символов.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $created = $reviews->upsertReview($companyId, $gymId, $user1->id, 4, '  Хороший зал  ');
        $this->assertSame(26, strlen($created->publicId));
        $this->assertSame(4, $created->rating);
        $this->assertSame('Хороший зал', $created->comment);
        $this->assertSame('Фарход', $created->authorName);

        // Повторное сохранение тем же пользователем обновляет существующую запись и сохраняет publicId
        $updated = $reviews->upsertReview($companyId, $gymId, $user1->id, 5, '   ');
        $this->assertSame($created->publicId, $updated->publicId);
        $this->assertSame(5, $updated->rating);
        $this->assertNull($updated->comment);
        $this->assertSame(1, GymReviewModel::query()->withoutGlobalScopes()->where('gym_id', $gymId)->count());

        // Второй отзыв от пользователя с пустым именем -> fallback "Клиент"
        $second = $reviews->upsertReview($companyId, $gymId, $user2->id, 4, 'Удобно');
        $this->assertSame('Клиент', $second->authorName);

        $page1 = $reviews->listForGym($gymId, page: 1, perPage: 1);
        $this->assertSame(2, $page1['total']);
        $this->assertCount(1, $page1['items']);
        $this->assertSame($second->publicId, $page1['items'][0]->publicId);

        $page2 = $reviews->listForGym($gymId, page: 2, perPage: 1);
        $this->assertSame(2, $page2['total']);
        $this->assertCount(1, $page2['items']);
        $this->assertSame($created->publicId, $page2['items'][0]->publicId);
    }

    public function test_public_gym_query_aggregates_rating_and_reviews_count(): void
    {
        $owner = User::factory()->create();
        $companyId = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Alpha'])->id;
        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        /** @var GymProfileRepositoryInterface $profiles */
        $profiles = $this->app->make(GymProfileRepositoryInterface::class);

        $gym1Id = $this->createPublishedGym($gyms, $profiles, $companyId, 'Reviewed Gym');
        $gym2Id = $this->createPublishedGym($gyms, $profiles, $companyId, 'Empty Reviews Gym');

        /** @var GymReviewCatalogContractInterface $reviews */
        $reviews = $this->app->make(GymReviewCatalogContractInterface::class);
        $u1 = User::factory()->create(['name' => 'Один']);
        $u2 = User::factory()->create(['name' => 'Два']);
        $reviews->upsertReview($companyId, $gym1Id, $u1->id, 5, 'Топ');
        $reviews->upsertReview($companyId, $gym1Id, $u2->id, 4, 'Норм');

        /** @var PublicGymQueryInterface $publicQuery */
        $publicQuery = $this->app->make(PublicGymQueryInterface::class);

        $search = $publicQuery->search(new PublicGymFilter(perPage: 10));
        $this->assertSame(2, $search['total']);

        $byGymId = [];
        foreach ($search['items'] as $item) {
            $byGymId[$item->gymId] = $item;
        }

        $this->assertSame(4.5, $byGymId[$gym1Id]->ratingAvg);
        $this->assertSame(2, $byGymId[$gym1Id]->reviewsCount);
        $this->assertNull($byGymId[$gym2Id]->ratingAvg);
        $this->assertSame(0, $byGymId[$gym2Id]->reviewsCount);

        $gym1PublicId = (string) GymModel::query()->withoutGlobalScopes()->findOrFail($gym1Id)->public_id;
        $gym2PublicId = (string) GymModel::query()->withoutGlobalScopes()->findOrFail($gym2Id)->public_id;

        $found1 = $publicQuery->findByPublicId($gym1PublicId);
        $this->assertNotNull($found1);
        $this->assertSame(4.5, $found1->ratingAvg);
        $this->assertSame(2, $found1->reviewsCount);

        $found2 = $publicQuery->findByPublicId($gym2PublicId);
        $this->assertNotNull($found2);
        $this->assertNull($found2->ratingAvg);
        $this->assertSame(0, $found2->reviewsCount);
    }

    private function createPublishedGym(
        GymRepositoryInterface $gyms,
        GymProfileRepositoryInterface $profiles,
        int $companyId,
        string $name,
    ): int {
        $gymId = $gyms->create($companyId, $name, 'ул. Рудаки, 10');
        $profile = GymProfile::draft($gymId);
        $profile->update('Описание зала', 'ул. Рудаки, 10', 38.5598, 68.7870, '+992900000111', [], []);
        $profile->submit();
        $profile->approve(new DateTimeImmutable('2026-11-15 10:00:00 UTC'));
        $profile->setPublication(true);
        $profiles->save($profile);

        return $gymId;
    }
}

