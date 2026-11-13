<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Устанавливает локаль приложения (ru/tg) по заголовку Accept-Language с откатом на ru (Тех. ТЗ 7.2; Бизнес-ТЗ 10).
 */
final class SetLocaleFromHeader
{
    public const string DEFAULT_LOCALE = 'ru';

    /** @var list<string> */
    public const array SUPPORTED_LOCALES = ['ru', 'tg'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        App::setFallbackLocale(self::DEFAULT_LOCALE);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        foreach ($request->getLanguages() as $candidate) {
            $primary = strtolower(strtok(str_replace('-', '_', $candidate), '_') ?: '');

            if (in_array($primary, self::SUPPORTED_LOCALES, true)) {
                return $primary;
            }
        }

        return self::DEFAULT_LOCALE;
    }
}
