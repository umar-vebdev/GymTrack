<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AcceptLanguageMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/v1/test-locale-validation', function (Request $request) {
            $request->validate(['title' => ['required', 'string']]);
        });
    }

    public function test_it_defaults_to_russian_when_accept_language_is_absent_or_unsupported(): void
    {
        $withoutHeader = $this->postJson('/api/v1/test-locale-validation', []);
        $withoutHeader->assertStatus(422)
            ->assertHeader('Content-Language', 'ru')
            ->assertJsonPath('error.message', 'Переданные данные не прошли проверку.')
            ->assertJsonPath('error.details.title.0', 'Поле title обязательно для заполнения.');

        $unsupported = $this->postJson('/api/v1/test-locale-validation', [], [
            'Accept-Language' => 'fr-FR, en;q=0.8',
        ]);
        $unsupported->assertStatus(422)
            ->assertHeader('Content-Language', 'ru')
            ->assertJsonPath('error.message', 'Переданные данные не прошли проверку.')
            ->assertJsonPath('error.details.title.0', 'Поле title обязательно для заполнения.');
    }

    public function test_it_switches_between_tajik_and_russian_according_to_accept_language_priority(): void
    {
        $tajik = $this->postJson('/api/v1/test-locale-validation', [], [
            'Accept-Language' => 'tg-TJ, ru;q=0.8',
        ]);
        $tajik->assertStatus(422)
            ->assertHeader('Content-Language', 'tg')
            ->assertJsonPath('error.message', 'Маълумоти пешниҳодшуда аз санҷиш нагузашт.')
            ->assertJsonPath('error.details.title.0', 'Майдони title ҳатмист.');

        $tajikNotFound = $this->getJson('/api/v1/non-existent-endpoint-locale-check', [
            'Accept-Language' => 'en-US, tg;q=0.9, ru;q=0.5',
        ]);
        $tajikNotFound->assertStatus(404)
            ->assertHeader('Content-Language', 'tg')
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.message', 'Захираи дархостшуда ёфт нашуд.');

        $russianHigherPriority = $this->postJson('/api/v1/test-locale-validation', [], [
            'Accept-Language' => 'tg;q=0.5, ru-RU;q=0.9',
        ]);
        $russianHigherPriority->assertStatus(422)
            ->assertHeader('Content-Language', 'ru')
            ->assertJsonPath('error.message', 'Переданные данные не прошли проверку.')
            ->assertJsonPath('error.details.title.0', 'Поле title обязательно для заполнения.');

        // Сброс обратно на русский при следующем запросе без заголовка
        $resetToDefault = $this->getJson('/api/v1/non-existent-endpoint-locale-check');
        $resetToDefault->assertStatus(404)
            ->assertHeader('Content-Language', 'ru')
            ->assertJsonPath('error.message', 'Запрашиваемый ресурс не найден.');
    }
}
