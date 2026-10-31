<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Application\ListCurrencies;
use Illuminate\Http\JsonResponse;

final class ListCurrenciesController extends Controller
{
    public function __construct(
        private readonly ListCurrencies $listCurrencies
    ) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => $this->listCurrencies->execute(),
        ]);
    }
}
