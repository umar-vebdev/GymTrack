<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Добавляет контекст трассировки (request_id, user_id, company_id) в структурированные логи
 * и возвращает заголовок X-Request-Id (Тех. ТЗ 12.1).
 */
final readonly class RequestContextMiddleware
{
    public const string HEADER_NAME = 'X-Request-Id';

    public const string ATTRIBUTE_NAME = 'request_id';

    public function __construct(
        private StaffCompanyResolverInterface $companies,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $userId = $this->resolveUserId($request);
        $companyId = $userId !== null ? $this->resolveCompanyId($userId) : null;

        $request->attributes->set(self::ATTRIBUTE_NAME, $requestId);

        Log::withContext([
            'request_id' => $requestId,
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);

        $response = $next($request);
        $response->headers->set(self::HEADER_NAME, $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = $request->header(self::HEADER_NAME);

        if (is_string($incoming) && preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $incoming) === 1) {
            return $incoming;
        }

        return Str::uuid()->toString();
    }

    private function resolveUserId(Request $request): ?int
    {
        $user = $request->user('sanctum') ?? $request->user();
        $authId = $user?->getAuthIdentifier();

        if (is_int($authId)) {
            return $authId;
        }

        if (is_string($authId) && ctype_digit($authId)) {
            return (int) $authId;
        }

        return null;
    }

    private function resolveCompanyId(int $userId): ?int
    {
        return $this->companies->companyIdForUser($userId)
            ?? $this->companies->companyIdForTrainer($userId);
    }
}
