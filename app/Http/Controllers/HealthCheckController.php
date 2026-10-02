<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $status = [
            'app' => 'ok',
            'database' => 'error',
            'redis' => 'error',
        ];

        try {
            DB::connection()->getPdo();
            $status['database'] = 'ok';
        } catch (\Throwable $e) {}

        try {
            Redis::connection()->ping();
            $status['redis'] = 'ok';
        } catch (\Throwable $e) {}

        $httpStatus = ($status['database'] === 'ok' && $status['redis'] === 'ok') ? 200 : 503;

        return response()->json($status, $httpStatus);
    }
}
