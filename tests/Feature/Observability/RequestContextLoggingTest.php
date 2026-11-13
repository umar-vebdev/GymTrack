<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Http\Middleware\RequestContextMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RequestContextLoggingTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('logs/observability-test-'.Str::uuid()->toString().'.log');

        config([
            'logging.default' => 'json',
            'logging.channels.json.path' => $this->logPath,
        ]);

        Log::forgetChannel('json');
        Log::setDefaultDriver('json');

        Route::get('/api/v1/test-observability-log', function () {
            Log::info('Observability probe');

            return response()->json(['ok' => true]);
        });
    }

    protected function tearDown(): void
    {
        if (File::exists($this->logPath)) {
            File::delete($this->logPath);
        }

        Log::forgetChannel('json');

        parent::tearDown();
    }

    public function test_it_generates_valid_request_id_and_preserves_trusted_incoming_header(): void
    {
        $generated = $this->getJson('/api/v1/test-observability-log');
        $generated->assertOk();
        $generatedId = $generated->headers->get(RequestContextMiddleware::HEADER_NAME);
        $this->assertNotNull($generatedId);
        $this->assertTrue(Str::isUuid($generatedId));

        $echoed = $this->getJson('/api/v1/test-observability-log', [
            RequestContextMiddleware::HEADER_NAME => 'client-trace_01.ok',
        ]);
        $echoed->assertOk()
            ->assertHeader(RequestContextMiddleware::HEADER_NAME, 'client-trace_01.ok');

        $rejected = $this->getJson('/api/v1/test-observability-log', [
            RequestContextMiddleware::HEADER_NAME => "bad header\r\nwith spaces",
        ]);
        $rejected->assertOk();
        $rejectedId = $rejected->headers->get(RequestContextMiddleware::HEADER_NAME);
        $this->assertNotNull($rejectedId);
        $this->assertNotSame("bad header\r\nwith spaces", $rejectedId);
        $this->assertTrue(Str::isUuid($rejectedId));
    }

    public function test_it_writes_json_logs_with_context_resets_between_requests_and_never_logs_tokens(): void
    {
        $owner = User::factory()->create([
            'name' => 'Obs Owner',
            'phone' => '+992900900001',
            'is_phone_verified' => true,
        ]);

        $plainToken = $owner->createToken('observability-probe')->plainTextToken;

        $companyResponse = $this->postJson('/api/v1/business/companies', [
            'company_name' => 'Obs Gym Co',
            'gym_name' => 'Obs Gym',
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);
        $companyResponse->assertCreated();
        $companyId = (int) $companyResponse->json('data.company_id');

        $authed = $this->getJson('/api/v1/test-observability-log', [
            'Authorization' => 'Bearer '.$plainToken,
            RequestContextMiddleware::HEADER_NAME => 'trace-owner-01',
        ]);

        $authed->assertOk()
            ->assertHeader(RequestContextMiddleware::HEADER_NAME, 'trace-owner-01');

        // Имитируем следующий независимый HTTP-запрос от гостя без авторизации
        $this->app->make('auth')->forgetGuards();

        $guest = $this->getJson('/api/v1/test-observability-log', [
            RequestContextMiddleware::HEADER_NAME => 'trace-guest-02',
        ]);

        $guest->assertOk()
            ->assertHeader(RequestContextMiddleware::HEADER_NAME, 'trace-guest-02');

        $notFound = $this->getJson('/api/v1/non-existent-observability-endpoint', [
            RequestContextMiddleware::HEADER_NAME => 'trace-404-03',
        ]);

        $notFound->assertStatus(404)
            ->assertHeader(RequestContextMiddleware::HEADER_NAME, 'trace-404-03');

        $this->assertFileExists($this->logPath);
        $rawContent = (string) file_get_contents($this->logPath);
        $this->assertStringNotContainsString($plainToken, $rawContent);

        $lines = array_values(array_filter(explode("\n", trim($rawContent))));
        $records = array_map(
            static fn (string $line): array => (array) json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            $lines,
        );

        $probeRecords = array_values(array_filter(
            $records,
            static fn (array $record): bool => ($record['message'] ?? null) === 'Observability probe',
        ));

        $this->assertCount(2, $probeRecords);

        $ownerRecord = $probeRecords[0];
        $this->assertIsArray($ownerRecord['context'] ?? null);
        $this->assertSame('INFO', $ownerRecord['level_name'] ?? null);
        $this->assertSame('trace-owner-01', $ownerRecord['context']['request_id'] ?? null);
        $this->assertSame($owner->id, $ownerRecord['context']['user_id'] ?? null);
        $this->assertSame($companyId, $ownerRecord['context']['company_id'] ?? null);

        $guestRecord = $probeRecords[1];
        $this->assertIsArray($guestRecord['context'] ?? null);
        $this->assertSame('trace-guest-02', $guestRecord['context']['request_id'] ?? null);
        $this->assertArrayHasKey('user_id', $guestRecord['context']);
        $this->assertArrayHasKey('company_id', $guestRecord['context']);
        $this->assertNull($guestRecord['context']['user_id']);
        $this->assertNull($guestRecord['context']['company_id']);
    }
}
