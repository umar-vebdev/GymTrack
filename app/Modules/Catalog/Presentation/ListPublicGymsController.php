<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\SearchGyms;
use App\Modules\Catalog\Application\SearchGymsQuery;
use App\Modules\Catalog\Presentation\Requests\SearchGymsRequest;
use App\Modules\Catalog\Presentation\Resources\PublicGymResource;
use App\Modules\Companies\Contracts\PublicGymProfile;
use Illuminate\Http\JsonResponse;

final class ListPublicGymsController extends Controller
{
    public function __construct(
        private readonly SearchGyms $searchGyms,
    ) {}

    public function __invoke(SearchGymsRequest $request): JsonResponse
    {
        /** @var string|null $q */
        $q = $request->validated('q');
        /** @var string|null $district */
        $district = $request->validated('district');
        /** @var list<string> $amenities */
        $amenities = array_values((array) $request->validated('amenities', []));
        $priceMin = $request->validated('price_min') !== null ? (int) $request->validated('price_min') : null;
        $priceMax = $request->validated('price_max') !== null ? (int) $request->validated('price_max') : null;
        /** @var string|null $currency */
        $currency = $request->validated('currency');
        $page = (int) $request->validated('page', 1);
        $perPage = (int) $request->validated('per_page', 20);

        $query = new SearchGymsQuery(
            query: $q,
            district: $district,
            amenities: $amenities,
            priceMin: $priceMin,
            priceMax: $priceMax,
            currency: $currency,
            page: $page,
            perPage: $perPage,
        );

        $result = $this->searchGyms->execute($query);

        return response()->json([
            'data' => array_map(
                static fn (PublicGymProfile $profile): array => PublicGymResource::toArray($profile),
                $result['items'],
            ),
            'meta' => [
                'page' => $query->page,
                'per_page' => $query->perPage,
                'total' => $result['total'],
            ],
        ]);
    }
}
