<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ApiErrorFormatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define some routes to trigger exceptions
        Route::get('/api/v1/test-404', function () {
            abort(404);
        });

        Route::post('/api/v1/test-422', function (Request $request) {
            $request->validate(['name' => 'required']);
        });

        Route::get('/api/v1/test-400', function () {
            throw new \InvalidArgumentException('Bad data.');
        });

        Route::get('/api/v1/test-500', function () {
            throw new \RuntimeException('Something went wrong.');
        });
    }

    public function test_it_returns_404_in_json_format(): void
    {
        $response = $this->getJson('/api/v1/test-404');

        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Запрашиваемый ресурс не найден.',
                ],
            ]);
    }

    public function test_it_returns_422_in_json_format(): void
    {
        $response = $this->postJson('/api/v1/test-422', []);

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Переданные данные не прошли проверку.',
                    'details' => [
                        'name' => ['The name field is required.'],
                    ],
                ],
            ]);
    }

    public function test_it_returns_400_in_json_format(): void
    {
        $response = $this->getJson('/api/v1/test-400');

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'code' => 'BAD_REQUEST',
                    'message' => 'Bad data.',
                ],
            ]);
    }
}
