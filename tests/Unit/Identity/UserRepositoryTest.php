<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Models\User as EloquentUser;
use App\Modules\Identity\Domain\ClientRegistrationPolicy;
use App\Modules\Identity\Domain\Exceptions\ClientRegistrationDenied;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(UserRepositoryInterface::class);
    }

    public function test_it_creates_and_finds_user_by_id(): void
    {
        $birthDate = new DateTimeImmutable('2000-05-15');
        $consentedAt = new DateTimeImmutable('2026-10-30 10:00:00');

        $id = $this->repository->create(
            phone: '+992000000000',
            email: 'test@example.com',
            passwordHash: 'hashed_password',
            isPhoneVerified: false,
            name: 'Алишер',
            birthDate: $birthDate,
            personalDataConsentedAt: $consentedAt,
        );

        $user = $this->repository->findById($id);
        $this->assertNotNull($user);

        $this->assertSame($id, $user->id());
        $this->assertSame('+992000000000', $user->phone());
        $this->assertSame('test@example.com', $user->email());
        $this->assertSame('hashed_password', $user->passwordHash());
        $this->assertFalse($user->isPhoneVerified());
        $this->assertSame('Алишер', $user->name());
        $this->assertSame('2000-05-15', $user->birthDate()?->format('Y-m-d'));
        $this->assertSame('2026-10-30 10:00:00', $user->personalDataConsentedAt()?->format('Y-m-d H:i:s'));
    }

    public function test_it_finds_user_by_phone(): void
    {
        EloquentUser::factory()->create(['phone' => '+992111111111']);

        $user = $this->repository->findByPhone('+992111111111');

        $this->assertNotNull($user);
        $this->assertSame('+992111111111', $user->phone());
    }

    public function test_it_finds_user_by_email(): void
    {
        EloquentUser::factory()->create(['email' => 'findme@example.com']);

        $user = $this->repository->findByEmail('findme@example.com');

        $this->assertNotNull($user);
        $this->assertSame('findme@example.com', $user->email());
    }

    public function test_it_updates_and_anonymizes_user(): void
    {
        $id = $this->repository->create(
            phone: '+992000000000',
            email: 'client@example.com',
            passwordHash: 'hashed',
            isPhoneVerified: false,
            name: 'Рустам',
            birthDate: new DateTimeImmutable('1995-03-10'),
            personalDataConsentedAt: new DateTimeImmutable('2026-10-30 10:00:00'),
        );

        $user = $this->repository->findById($id);
        $this->assertNotNull($user);
        $user->markPhoneAsVerified();

        $this->repository->update($user);

        $updatedUser = $this->repository->findById($id);
        $this->assertNotNull($updatedUser);
        $this->assertTrue($updatedUser->isPhoneVerified());

        $updatedUser->anonymize('invalidated_hash');
        $this->repository->update($updatedUser);

        $anonymized = $this->repository->findById($id);
        $this->assertNotNull($anonymized);
        $this->assertSame('Deleted User', $anonymized->name());
        $this->assertNull($anonymized->phone());
        $this->assertNull($anonymized->email());
        $this->assertNull($anonymized->birthDate());
        $this->assertFalse($anonymized->isPhoneVerified());
        // Дата согласия при этом сохраняется для юридической отчётности
        $this->assertNotNull($anonymized->personalDataConsentedAt());
    }

    public function test_client_registration_policy_enforces_18_plus_and_consent(): void
    {
        $today = new DateTimeImmutable('2026-10-30 12:00:00');

        // Ровно 18 лет — разрешено
        ClientRegistrationPolicy::assertEligible(
            new DateTimeImmutable('2008-10-30'),
            true,
            $today,
        );

        // На 1 день младше 18 лет — запрещено
        try {
            ClientRegistrationPolicy::assertEligible(
                new DateTimeImmutable('2008-10-31'),
                true,
                $today,
            );
            $this->fail('Ожидалось исключение для несовершеннолетнего');
        } catch (ClientRegistrationDenied $e) {
            $this->assertSame('UNDERAGE_REGISTRATION_NOT_ALLOWED', $e->errorCode);
        }

        // Без согласия на обработку персональных данных — запрещено
        try {
            ClientRegistrationPolicy::assertEligible(
                new DateTimeImmutable('1990-01-01'),
                false,
                $today,
            );
            $this->fail('Ожидалось исключение при отсутствии согласия');
        } catch (ClientRegistrationDenied $e) {
            $this->assertSame('PERSONAL_DATA_CONSENT_REQUIRED', $e->errorCode);
        }
    }
}
