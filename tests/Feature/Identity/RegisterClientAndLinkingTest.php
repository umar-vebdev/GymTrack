<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class RegisterClientAndLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_registers_verifies_phone_and_automatically_links_gym_cards_preserving_history(): void
    {
        $owner = User::factory()->create();
        $companyA = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Gym A']);
        $companyB = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Gym B']);

        StaffMemberModel::create([
            'company_id' => $companyA->id,
            'user_id' => $owner->id,
            'role' => Role::OWNER->value,
        ]);

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);

        $phone = '+992900777888';

        // Карточки клиента созданы вручную в двух залах до регистрации человека в приложении
        $cardAId = $clients->create($companyA->id, 'Рустам Каримов', $phone);
        $cardBId = $clients->create($companyB->id, 'Рустам', $phone);

        // Зал A продал клиенту абонемент на кассе
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $companyA->id,
            '12 занятий',
            new Money(20000, 'TJS'),
            MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 12, validityDays: 60)
        );

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/business/sales', [
            'client_id' => $cardAId,
            'payment_method' => 'cash',
            'membership_type_ids' => [$typeId],
        ])->assertCreated();

        // Человек регистрируется в мобильном приложении с тем же номером (критерий приёмки 12.9)
        $registerRes = $this->postJson('/api/v1/client/auth/register', [
            'phone' => $phone,
            'name' => 'Рустам Каримов',
            'password' => 'secret1234',
            'birth_date' => '1998-04-12',
            'consent' => true,
        ]);

        $registerRes->assertCreated()
            ->assertJsonPath('data.phone', $phone)
            ->assertJsonPath('data.name', 'Рустам Каримов')
            ->assertJsonPath('data.birth_date', '1998-04-12')
            ->assertJsonPath('data.is_phone_verified', false);

        $userId = (int) $registerRes->json('data.id');

        // До подтверждения номера карточки залов ещё НЕ привязаны (D-002)
        $this->assertDatabaseHas('clients', [
            'id' => $cardAId,
            'user_id' => null,
        ]);

        // OTP-код был отправлен при регистрации
        $otpCode = Cache::get("otp:{$phone}");
        $this->assertIsString($otpCode);

        // Клиент подтверждает номер телефона
        $this->postJson('/api/v1/auth/verify-phone', [
            'phone' => $phone,
            'code' => $otpCode,
        ])->assertOk();

        // Обе карточки в разных залах автоматически привязаны к аккаунту, история сохранена
        $this->assertDatabaseHas('clients', [
            'id' => $cardAId,
            'user_id' => $userId,
        ]);
        $this->assertDatabaseHas('clients', [
            'id' => $cardBId,
            'user_id' => $userId,
        ]);
        $this->assertDatabaseHas('memberships', [
            'company_id' => $companyA->id,
            'client_id' => $cardAId,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('sales_sales', [
            'company_id' => $companyA->id,
            'client_id' => $cardAId,
            'total_minor' => 20000,
        ]);
    }

    public function test_underage_or_unconsented_or_duplicate_registration_is_rejected(): void
    {
        // Несовершеннолетний (младше 18 лет) — Бизнес-ТЗ 5.1
        $this->postJson('/api/v1/client/auth/register', [
            'phone' => '+992900111001',
            'name' => 'Подросток',
            'password' => 'secret1234',
            'birth_date' => '2012-06-01',
            'consent' => true,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'UNDERAGE_REGISTRATION_NOT_ALLOWED');

        // Без согласия на обработку персональных данных — Бизнес-ТЗ 5.1, 10
        $this->postJson('/api/v1/client/auth/register', [
            'phone' => '+992900111002',
            'name' => 'Без согласия',
            'password' => 'secret1234',
            'birth_date' => '1995-06-01',
            'consent' => false,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'PERSONAL_DATA_CONSENT_REQUIRED');

        // Повторная регистрация с тем же номером
        $this->postJson('/api/v1/client/auth/register', [
            'phone' => '+992900111003',
            'name' => 'Первый',
            'password' => 'secret1234',
            'birth_date' => '1995-06-01',
            'consent' => true,
        ])->assertCreated();

        $this->postJson('/api/v1/client/auth/register', [
            'phone' => '+992900111003',
            'name' => 'Дубликат',
            'password' => 'secret1234',
            'birth_date' => '1995-06-01',
            'consent' => true,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'PHONE_ALREADY_REGISTERED');
    }
}
