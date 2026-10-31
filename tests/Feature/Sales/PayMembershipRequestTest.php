<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Memberships\Application\Commands\CreateMembershipTypeCommand;
use App\Modules\Memberships\Application\CreateMembershipType;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Sales\Domain\Events\MembershipRequestPaid;
use App\Modules\Sales\Domain\Events\PaymentMarked;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class PayMembershipRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{int, int, int, User}
     */
    private function setupCompanyWithStaff(): array
    {
        $owner = User::factory()->create();

        /** @var CompanyRepositoryInterface $companies */
        $companies = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companies->create($owner->id, 'Champion Gym');

        /** @var StaffMemberRepositoryInterface $staff */
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $staff->create($owner->id, $companyId, Role::OWNER);

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($companyId, 'Central Branch', 'Somoni ave 1');

        /** @var CreateMembershipType $createType */
        $createType = $this->app->make(CreateMembershipType::class);
        $typeId = $createType->execute(new CreateMembershipTypeCommand(
            companyId: $companyId,
            name: '12 Visits Champion',
            priceMinor: 50000,
            currency: 'TJS',
            kind: MembershipKind::Visits,
            startMode: StartMode::FirstVisit,
            visitsCount: 12,
            durationDays: null,
            validityDays: 60,
            dailyLimit: 1,
            timeFromMinute: null,
            timeToMinute: null,
            freezeMaxDays: 0,
            freezeMaxTimes: 0
        ));

        return [$companyId, $gymId, $typeId, $owner];
    }

    public function test_guest_cannot_pay_membership_request(): void
    {
        $this->postJson('/api/v1/business/membership-requests/1/pay', [
            'payment_method' => 'cash',
        ])->assertStatus(401);
    }

    public function test_non_staff_is_forbidden_to_pay(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/business/membership-requests/1/pay', [
                'payment_method' => 'cash',
            ])->assertStatus(403);
    }

    public function test_staff_pays_membership_request_and_issues_membership(): void
    {
        Event::fake([PaymentMarked::class, MembershipRequestPaid::class]);

        [$companyId, $gymId, $typeId, $owner] = $this->setupCompanyWithStaff();

        $clientUser = User::factory()->create([
            'name' => 'Dilshod Juraev',
            'phone' => '+992911111111',
        ]);

        /** @var MembershipRequestRepositoryInterface $requests */
        $requests = $this->app->make(MembershipRequestRepositoryInterface::class);
        $request = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $clientUser->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/membership-requests/{$request->id()}/pay", [
                'payment_method' => 'card_terminal',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'request_id',
                    'sale_id',
                    'membership_id',
                    'client_id',
                    'status',
                    'paid_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'request_id' => $request->id(),
                    'status' => 'paid',
                ],
            ]);

        $saleId = (int) $response->json('data.sale_id');
        $membershipId = (int) $response->json('data.membership_id');
        $clientId = (int) $response->json('data.client_id');

        // Проверяем статус заявки в БД
        $this->assertDatabaseHas('sales_membership_requests', [
            'id' => $request->id(),
            'status' => 'paid',
            'sale_id' => $saleId,
            'membership_id' => $membershipId,
            'client_id' => $clientId,
        ]);

        // Проверяем создание продажи
        $this->assertDatabaseHas('sales_sales', [
            'id' => $saleId,
            'company_id' => $companyId,
            'client_id' => $clientId,
            'payment_method' => 'card_terminal',
            'total_minor' => 50000,
        ]);

        // Проверяем выпуск абонемента
        $this->assertDatabaseHas('memberships', [
            'id' => $membershipId,
            'company_id' => $companyId,
            'client_id' => $clientId,
            'type_id' => $typeId,
            'remaining_visits' => 12,
        ]);

        Event::assertDispatched(PaymentMarked::class);
        Event::assertDispatched(MembershipRequestPaid::class, function (MembershipRequestPaid $e) use ($request, $companyId, $saleId, $membershipId) {
            return $e->requestId === $request->id()
                && $e->companyId === $companyId
                && $e->saleId === $saleId
                && $e->membershipId === $membershipId;
        });
    }

    public function test_pay_request_idempotency(): void
    {
        [$companyId, $gymId, $typeId, $owner] = $this->setupCompanyWithStaff();

        $clientUser = User::factory()->create();

        /** @var MembershipRequestRepositoryInterface $requests */
        $requests = $this->app->make(MembershipRequestRepositoryInterface::class);
        $request = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $clientUser->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $first = $this->actingAs($owner, 'sanctum')
            ->withHeader('Idempotency-Key', 'pay-req-idem-999')
            ->postJson("/api/v1/business/membership-requests/{$request->id()}/pay", [
                'payment_method' => 'cash',
            ])
            ->assertStatus(200);

        $second = $this->actingAs($owner, 'sanctum')
            ->withHeader('Idempotency-Key', 'pay-req-idem-999')
            ->postJson("/api/v1/business/membership-requests/{$request->id()}/pay", [
                'payment_method' => 'cash',
            ])
            ->assertStatus(200);

        $this->assertSame($first->json('data.sale_id'), $second->json('data.sale_id'));
        $this->assertSame($first->json('data.membership_id'), $second->json('data.membership_id'));

        $this->assertDatabaseCount('sales_sales', 1);
        $this->assertDatabaseCount('memberships', 1);
    }

    public function test_cannot_pay_expired_request(): void
    {
        [$companyId, $gymId, $typeId, $owner] = $this->setupCompanyWithStaff();

        $clientUser = User::factory()->create();

        /** @var MembershipRequestRepositoryInterface $requests */
        $requests = $this->app->make(MembershipRequestRepositoryInterface::class);
        $request = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $clientUser->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('-1 hour')
        );

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/membership-requests/{$request->id()}/pay", [
                'payment_method' => 'cash',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Срок действия заявки истёк.');
    }

    public function test_cannot_pay_request_of_another_company(): void
    {
        [$company1Id, $gym1Id, $type1Id, $owner1] = $this->setupCompanyWithStaff();
        [$company2Id, $gym2Id, $type2Id, $owner2] = $this->setupCompanyWithStaff();

        $clientUser = User::factory()->create();

        /** @var MembershipRequestRepositoryInterface $requests */
        $requests = $this->app->make(MembershipRequestRepositoryInterface::class);
        $req1 = $requests->create(
            companyId: $company1Id,
            gymId: $gym1Id,
            userId: $clientUser->id,
            clientId: null,
            membershipTypeId: $type1Id,
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        // Owner 2 tries to pay req1 (belongs to company 1)
        $this->actingAs($owner2, 'sanctum')
            ->postJson("/api/v1/business/membership-requests/{$req1->id()}/pay", [
                'payment_method' => 'cash',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Заявка не найдена.');
    }
}
