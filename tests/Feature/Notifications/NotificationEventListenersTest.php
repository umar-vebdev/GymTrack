<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Notifications\Application\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Application\Jobs\SendSmsNotificationJob;
use App\Modules\Notifications\Domain\NotificationType;
use App\Modules\Sales\Domain\Events\MembershipRequestPaid;
use App\Modules\Visits\Domain\Events\VisitRegistered;
use App\Modules\Visits\Domain\VisitMethod;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class NotificationEventListenersTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(): CompanyModel
    {
        $owner = User::factory()->create();

        return CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Notify Gym Company']);
    }

    public function test_visit_registered_dispatches_push_when_client_has_user_account(): void
    {
        Queue::fake();

        $company = $this->createCompany();
        $user = User::factory()->create();

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($company->id, 'John Doe', '+992900123456', $user->id);

        $event = new VisitRegistered(
            visitId: 101,
            companyId: $company->id,
            gymId: 1,
            clientId: $clientId,
            membershipId: 50,
            method: VisitMethod::Qr,
            visitedAt: new DateTimeImmutable,
            registeredByUserId: null
        );

        Event::dispatch($event);

        Queue::assertPushed(SendPushNotificationJob::class, function (SendPushNotificationJob $job) use ($company, $user, $clientId) {
            return $job->companyId === $company->id
                && $job->userId === $user->id
                && $job->clientId === $clientId
                && $job->type === NotificationType::VisitRegistered
                && $job->entityType === 'visit'
                && $job->entityId === 101;
        });

        Queue::assertNotPushed(SendSmsNotificationJob::class);
    }

    public function test_visit_registered_dispatches_sms_when_client_has_no_user_account(): void
    {
        Queue::fake();

        $company = $this->createCompany();
        $staff = User::factory()->create();

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($company->id, 'Walk-in Client', '+992900654321', null);

        $event = new VisitRegistered(
            visitId: 202,
            companyId: $company->id,
            gymId: 1,
            clientId: $clientId,
            membershipId: 51,
            method: VisitMethod::Manual,
            visitedAt: new DateTimeImmutable,
            registeredByUserId: $staff->id
        );

        Event::dispatch($event);

        Queue::assertPushed(SendSmsNotificationJob::class, function (SendSmsNotificationJob $job) use ($company, $clientId) {
            return $job->companyId === $company->id
                && $job->userId === null
                && $job->clientId === $clientId
                && $job->phone === '+992900654321'
                && $job->type === NotificationType::VisitRegistered
                && $job->entityType === 'visit'
                && $job->entityId === 202;
        });

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_membership_request_paid_dispatches_push_notification(): void
    {
        Queue::fake();

        $company = $this->createCompany();
        $user = User::factory()->create();
        $staff = User::factory()->create();

        $event = new MembershipRequestPaid(
            requestId: 303,
            companyId: $company->id,
            gymId: 1,
            userId: $user->id,
            clientId: 404,
            membershipTypeId: 5,
            saleId: 606,
            membershipId: 707,
            acceptedByUserId: $staff->id,
            paidAt: new DateTimeImmutable
        );

        Event::dispatch($event);

        Queue::assertPushed(SendPushNotificationJob::class, function (SendPushNotificationJob $job) use ($company, $user) {
            return $job->companyId === $company->id
                && $job->userId === $user->id
                && $job->clientId === 404
                && $job->type === NotificationType::MembershipRequestPaid
                && $job->entityType === 'membership_request'
                && $job->entityId === 303;
        });
    }
}
