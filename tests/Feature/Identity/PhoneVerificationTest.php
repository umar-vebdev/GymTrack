<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Identity\Contracts\EmailVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_email_otp(): void
    {
        /** @var ArrayTransport $transport */
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $transport->flush();

        $response = $this->postJson('/api/v1/auth/send-otp', [
            'email' => 'Client@Example.TEST',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Код подтверждения отправлен.']);

        /** @var string|null $otp */
        $otp = Cache::get('otp:email:client@example.test');
        $this->assertNotNull($otp);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);

        $messages = $transport->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString($otp, $messages[0]->toString());
    }

    public function test_it_verifies_email_and_updates_user(): void
    {
        Event::fake([EmailVerified::class]);

        $user = User::factory()->create([
            'email' => 'client@example.test',
            'email_verified_at' => null,
        ]);

        Cache::put('otp:email:client@example.test', '123456', now()->addMinutes(5));

        $response = $this->postJson('/api/v1/auth/verify-email', [
            'email' => 'Client@Example.TEST',
            'code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Email успешно подтверждён.']);

        $this->assertFalse(Cache::has('otp:email:client@example.test'));

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        Event::assertDispatched(EmailVerified::class, static fn (EmailVerified $event): bool => $event->userId === $user->id
            && $event->email === 'client@example.test');
    }

    public function test_it_fails_verification_with_wrong_code(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.test',
            'email_verified_at' => null,
        ]);

        Cache::put('otp:email:client@example.test', '123456', now()->addMinutes(5));

        $response = $this->postJson('/api/v1/auth/verify-email', [
            'email' => 'client@example.test',
            'code' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }
}
