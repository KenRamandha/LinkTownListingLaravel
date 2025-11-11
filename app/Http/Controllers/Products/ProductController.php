<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ProductController extends Controller
{
    private const PRODUCT_TYPE_IDS = [5, 6, 7, 10, 11];

    public function home(Request $request)
    {
        $validated = Validator::validate($request->all(), [
            'property_status' => ['nullable', 'string'],
            'limit'           => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $limit = (int) ($validated['limit'] ?? 5);
        $propertyStatus = $validated['property_status'] ?? null;

        try {
            $payload = $this->buildHomePayload($propertyStatus, $limit);

            return $this->ok($payload, 'Berhasil memuat produk home', [
                'filters' => [
                    'property_status' => $propertyStatus,
                    'limit'           => $limit,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat data produk', 500, 'SERVER_ERROR');
        }
    }

    private function buildHomePayload(?string $propertyStatus, int $limit): array
    {
        $latestProperties = $this->homeBaseQuery($propertyStatus)
            ->orderByDesc('a.created_at')
            ->limit($limit)
            ->get();

        $latestListings = $this->homeBaseQuery($propertyStatus)
            ->when($latestProperties->isNotEmpty(), fn(QueryBuilder $query) => $query->whereNotIn('a.id', $latestProperties->pluck('product_id')))
            ->orderByDesc('a.updated_at')
            ->limit($limit)
            ->get();

        return [
            'latest_properties' => $this->formatHomeResults($latestProperties),
            'latest_listings'   => $this->formatHomeResults($latestListings),
        ];
    }

    private function homeBaseQuery(?string $propertyStatus): QueryBuilder
    {
        return DB::table('products as a')
            ->select([
                'a.id as product_id',
                'a.price',
                'a.title',
                'a.property_status',
                'a.nowa',
                'a.namawa',
                'l.address',
                't.name as product_type_name',
                'a.description',
                's.value as specification_value',
                'a.hero_subtitle',
                'ad1.name as place_name',
                'ad2.name as city_name',
                'ad2.state as city_state',
            ])
            ->selectSub(function ($sub) {
                $sub->from('product_images as g')
                    ->select('g.url')
                    ->whereColumn('g.product_id', 'a.id')
                    ->orderByDesc('g.featured')
                    ->orderBy('g.id')
                    ->limit(1);
            }, 'foto')
            ->join('product_locations as l', 'a.id', '=', 'l.product_id')
            ->join('product_types as t', 'a.product_type_id', '=', 't.id')
            ->join('product_specifications as s', 'a.id', '=', 's.product_id')
            ->join('places as ad1', 'ad1.id', '=', 'l.place_id')
            ->join('cities as ad2', 'ad1.city_id', '=', 'ad2.id')
            ->whereIn('t.id', self::PRODUCT_TYPE_IDS)
            ->when($propertyStatus, fn(QueryBuilder $query, string $status) => $query->where('a.property_status', $status));
    }

    private function formatHomeResults(Collection $items): array
    {
        return $items
            ->map(function ($row) {
                $payload = (array) $row;
                $payload['price'] = is_null($row->price) ? null : (float) $row->price;
                unset($payload['product_id']);

                return $payload;
            })
            ->values()
            ->all();
    }
}
