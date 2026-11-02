<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class DeviceTokensEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_register_device_token(): void
    {
        $response = $this->postJson('/api/v1/client/device-tokens', [
            'token' => 'device-token-123',
            'platform' => 'android',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_unregister_device_token(): void
    {
        $response = $this->deleteJson('/api/v1/client/device-tokens', [
            'token' => 'device-token-123',
        ]);

        $response->assertStatus(401);
    }

    public function test_client_can_register_device_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/client/device-tokens', [
            'token' => 'fcm-device-token-abc',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'registered' => true,
            ],
        ]);

        $this->assertDatabaseHas('notifications_device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-device-token-abc',
            'platform' => 'android',
        ]);
    }

    public function test_client_register_is_idempotent_and_updates_platform(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/client/device-tokens', [
            'token' => 'fcm-device-token-abc',
            'platform' => 'android',
        ])->assertStatus(200);

        // Re-register same token with different platform
        $response = $this->postJson('/api/v1/client/device-tokens', [
            'token' => 'fcm-device-token-abc',
            'platform' => 'ios',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('notifications_device_tokens', 1);
        $this->assertDatabaseHas('notifications_device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-device-token-abc',
            'platform' => 'ios',
        ]);
    }

    public function test_client_register_validation_errors(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/client/device-tokens', [
            'token' => '',
            'platform' => 'windows_phone',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertArrayHasKey('token', $response->json('error.details'));
        $this->assertArrayHasKey('platform', $response->json('error.details'));
    }

    public function test_client_can_unregister_device_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/client/device-tokens', [
            'token' => 'fcm-device-token-abc',
            'platform' => 'android',
        ])->assertStatus(200);

        $response = $this->deleteJson('/api/v1/client/device-tokens', [
            'token' => 'fcm-device-token-abc',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'unregistered' => true,
            ],
        ]);

        $this->assertDatabaseMissing('notifications_device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-device-token-abc',
        ]);
    }

    public function test_unregister_is_user_isolated(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Sanctum::actingAs($userA);
        $this->postJson('/api/v1/client/device-tokens', [
            'token' => 'shared-token-value',
            'platform' => 'android',
        ])->assertStatus(200);

        Sanctum::actingAs($userB);
        $this->postJson('/api/v1/client/device-tokens', [
            'token' => 'shared-token-value',
            'platform' => 'ios',
        ])->assertStatus(200);

        // User A unregisters the token
        Sanctum::actingAs($userA);
        $this->deleteJson('/api/v1/client/device-tokens', [
            'token' => 'shared-token-value',
        ])->assertStatus(200);

        // User A's token deleted, User B's token remains
        $this->assertDatabaseMissing('notifications_device_tokens', [
            'user_id' => $userA->id,
            'token' => 'shared-token-value',
        ]);
        $this->assertDatabaseHas('notifications_device_tokens', [
            'user_id' => $userB->id,
            'token' => 'shared-token-value',
        ]);
    }
}
