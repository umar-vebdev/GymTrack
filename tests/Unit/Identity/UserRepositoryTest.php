<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Models\User as EloquentUser;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
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
        $id = $this->repository->create(
            phone: '+992000000000',
            email: 'test@example.com',
            passwordHash: 'hashed_password',
            isPhoneVerified: false
        );

        $user = $this->repository->findById($id);
        $this->assertNotNull($user);

        $this->assertSame($id, $user->id());
        $this->assertSame('+992000000000', $user->phone());
        $this->assertSame('test@example.com', $user->email());
        $this->assertSame('hashed_password', $user->passwordHash());
        $this->assertFalse($user->isPhoneVerified());
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

    public function test_it_updates_user(): void
    {
        $id = $this->repository->create(
            phone: '+992000000000',
            email: null,
            passwordHash: 'hashed',
            isPhoneVerified: false
        );

        // $this->repository->findById($id); // Wait, domain objects are passed around. Let's just find and modify state if it is mutable.
        // Actually, our User is somewhat mutable because of markPhoneAsVerified.

        $user = $this->repository->findById($id);
        $this->assertNotNull($user);
        $user->markPhoneAsVerified();

        $this->repository->update($user);

        $updatedUser = $this->repository->findById($id);
        $this->assertNotNull($updatedUser);

        $this->assertTrue($updatedUser->isPhoneVerified());
    }
}
