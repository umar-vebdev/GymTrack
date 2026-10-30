<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Identity\Contracts\TokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class ClientProfileAndDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_views_profile_and_deletes_account_revoking_tokens_and_preserving_gym_history(): void
    {
        $owner = User::factory()->create();
        $company = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Gym A']);

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $phone = '+992900555666';
        $gymClientId = $clients->create($company->id, 'Фаррух', $phone);

        // Регистрация клиента и подтверждение телефона
        $regRes = $this->postJson('/api/v1/client/auth/register', [
            'phone' => $phone,
            'name' => 'Фаррух',
            'password' => 'secret1234',
            'birth_date' => '1996-08-20',
            'consent' => true,
        ])->assertCreated();

        $userId = (int) $regRes->json('data.id');
        /** @var string $otp */
        $otp = Cache::get("otp:{$phone}");

        $this->postJson('/api/v1/auth/verify-phone', [
            'phone' => $phone,
            'code' => $otp,
        ])->assertOk();

        /** @var TokenGenerator $tokens */
        $tokens = $this->app->make(TokenGenerator::class);
        $token = $tokens->generateForUser($userId, 'mobile-app');

        // Просмотр собственного профиля
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/client/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $userId)
            ->assertJsonPath('data.phone', $phone)
            ->assertJsonPath('data.name', 'Фаррух')
            ->assertJsonPath('data.birth_date', '1996-08-20')
            ->assertJsonPath('data.is_phone_verified', true);

        // Удаление/обезличивание аккаунта по запросу (Бизнес-ТЗ 10, Тех. ТЗ 8)
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/client/profile')
            ->assertOk()
            ->assertJsonPath('data.status', 'deleted');

        // Персональные данные пользователя обезличены, токены отозваны
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'name' => 'Deleted User',
            'phone' => null,
            'birth_date' => null,
            'is_phone_verified' => false,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Карточка клиента внутри зала сохранена
        $this->assertDatabaseHas('clients', [
            'id' => $gymClientId,
            'company_id' => $company->id,
        ]);
    }
}
