<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Models\Products\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ProductController extends Controller
{
    private const PROPERTY_PRODUCT_TYPE_IDS = [1, 2, 3, 4];
    private const LISTING_PRODUCT_TYPE_IDS  = [5, 6, 7, 10, 11];

    public function search(Request $request)
    {
        $validated = Validator::validate($request->all(), [
            'q'               => ['nullable', 'string'],
            'property_status' => ['nullable', 'string'],
            'city'            => ['nullable', 'string'],
            'city_id'         => ['nullable', 'integer'],
            'min_price'       => ['nullable', 'numeric', 'min:0'],
            'max_price'       => ['nullable', 'numeric', 'min:0'],
            'page'            => ['nullable', 'integer', 'min:1'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search         = $validated['q'] ?? null;
        $propertyStatus = $validated['property_status'] ?? null;
        $cityId         = $validated['city_id'] ?? null;
        $city           = $validated['city'] ?? null;
        $minPrice       = $validated['min_price'] ?? null;
        $maxPrice       = $validated['max_price'] ?? null;
        $perPage        = (int) ($validated['per_page'] ?? 12);

        try {
            $productTypeIds = array_merge(self::PROPERTY_PRODUCT_TYPE_IDS, self::LISTING_PRODUCT_TYPE_IDS);

            /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
            $paginator = $this->homeBaseQuery($propertyStatus, $cityId, $productTypeIds)
                ->when($search, function (QueryBuilder $query, string $keyword) {
                    $like = '%' . $keyword . '%';

                    $query->where(function (QueryBuilder $inner) use ($like) {
                        $inner
                            ->where('a.title', 'like', $like)
                            ->orWhere('a.hero_subtitle', 'like', $like)
                            ->orWhere('ad1.name', 'like', $like) // place_name
                            ->orWhere('l.address', 'like', $like)
                            ->orWhere('ad2.name', 'like', $like) // city_name
                            ->orWhere('ad2.state', 'like', $like); // city_state
                    });
                })
                ->when($city, function (QueryBuilder $query, string $cityValue) {
                    $query->where(function (QueryBuilder $inner) use ($cityValue) {
                        $inner
                            ->where('ad2.slug', $cityValue)
                            ->orWhere('ad2.name', 'like', '%' . $cityValue . '%');
                    });
                })
                ->when($minPrice, fn (QueryBuilder $query, $min) => $query->where('a.price', '>=', $min))
                ->when($maxPrice, fn (QueryBuilder $query, $max) => $query->where('a.price', '<=', $max))
                ->orderByDesc('a.created_at')
                ->paginate($perPage);

            $products = $this->formatHomeResults(collect($paginator->items()));

            $payload = [
                'products'   => $products,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ];

            return $this->ok($payload, 'Berhasil memuat daftar produk', [
                'filters' => [
                    'q'               => $search,
                    'property_status' => $propertyStatus,
                    'city'            => $city,
                    'city_id'         => $cityId,
                    'min_price'       => $minPrice,
                    'max_price'       => $maxPrice,
                    'per_page'        => $perPage,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat daftar produk', 500, 'SERVER_ERROR');
        }
    }

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

    public function show(int $productId)
    {
        try {
            $product = Product::query()
                ->with([
                    'productType',
                    'images'  => fn ($query) => $query->orderByDesc('featured')->orderBy('id'),
                    'layouts' => fn ($query) => $query->orderBy('id'),
                    'locations.place.city',
                    'specifications',
                ])
                ->findOrFail($productId);

            $payload = $this->buildProductDetail($product);

            return $this->ok($payload, 'Berhasil memuat detail produk');
        } catch (ModelNotFoundException $e) {
            return $this->fail('Produk tidak ditemukan', 404, 'NOT_FOUND');
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat detail produk', 500, 'SERVER_ERROR');
        }
    }

    public function topByCity(Request $request, int $cityId)
    {
        $validated = Validator::validate($request->all(), [
            'limit'           => ['nullable', 'integer', 'min:1', 'max:20'],
            'property_status' => ['nullable', 'string'],
        ]);

        $limit = (int) ($validated['limit'] ?? 6);
        $propertyStatus = $validated['property_status'] ?? null;

        try {
            $products = $this->homeBaseQuery($propertyStatus, $cityId, self::LISTING_PRODUCT_TYPE_IDS)
                ->orderByDesc('a.created_at')
                ->limit($limit)
                ->get();

            $payload = [
                'products' => $this->formatHomeResults($products),
            ];

            return $this->ok($payload, 'Berhasil memuat produk berdasarkan kota', [
                'filters' => [
                    'city_id'         => $cityId,
                    'limit'           => $limit,
                    'property_status' => $propertyStatus,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat produk berdasarkan kota', 500, 'SERVER_ERROR');
        }
    }

    private function buildHomePayload(?string $propertyStatus, int $limit, ?int $cityId = null): array
    {
        $latestProperties = $this->homeBaseQuery($propertyStatus, $cityId, self::PROPERTY_PRODUCT_TYPE_IDS)
            ->limit($limit)
            ->get();

        $latestListings = $this->homeBaseQuery($propertyStatus, $cityId, self::LISTING_PRODUCT_TYPE_IDS)
            ->limit($limit)
            ->get();

        return [
            'latest_properties' => $this->formatHomeResults($latestProperties),
            'latest_listings'   => $this->formatHomeResults($latestListings),
        ];
    }

    private function buildProductDetail(Product $product): array
    {
        $primaryLocation = $product->locations->first();
        $place = $primaryLocation?->place;
        $city = $place?->city;

        return [
            'id'                 => $product->id,
            'slug'               => $product->slug,
            'title'              => $product->title,
            'code'               => $product->code,
            'meta_description'   => $product->meta_description,
            'meta_title'         => $product->meta_title,
            'around'             => $this->formatArray($product->around),
            'promo'              => $product->promo,
            'description'        => $product->description,
            'benefits'           => $this->formatArray($product->benefits),
            'tags'               => $this->formatArray($product->tags),
            'price'              => is_null($product->price) ? null : (float) $product->price,
            'cicilan_per_bulan'  => $product->cicilan_per_bulan,
            'label'              => $product->label,
            'label_color'        => $product->label_color,
            'product_type_id'    => $product->product_type_id,
            'link'               => $product->link,
            'order'              => $product->order,
            'status'             => $product->status,
            'image_location'     => $product->image_location,
            'youtube'            => $product->youtube,
            'hero_title'         => $product->hero_title,
            'hero_list'          => $this->formatArray($product->hero_list),
            'price_header'       => $this->formatArray($product->price_header),
            'hero_subtitle'      => $product->hero_subtitle,
            'developer_id'       => $product->developer_id,
            'tenant'             => $this->formatArray($product->tenant),
            'featured_partner'   => (bool) $product->featured_partner,
            'project_id'         => $product->project_id,
            'user_id'            => $product->user_id,
            'property_status'    => $product->property_status,
            'nowa'               => $product->nowa,
            'namawa'             => $product->namawa,
            'rental_terms'       => $product->rental_terms,
            'created_at'         => optional($product->created_at)?->toDateTimeString(),
            'updated_at'         => optional($product->updated_at)?->toDateTimeString(),
            'product_type'       => $product->productType ? [
                'id'          => $product->productType->id,
                'name'        => $product->productType->name,
                'slug'        => $product->productType->slug,
                'title'       => $product->productType->title,
                'description' => $product->productType->description,
                'meta_title'  => $product->productType->meta_title,
                'meta_description' => $product->productType->meta_description,
                'color'       => $product->productType->color,
                'position'    => $product->productType->position,
                'image'       => $product->productType->image,
            ] : null,
            'place'              => $place ? [
                'id'         => $place->id,
                'city_id'    => $place->city_id,
                'name'       => $place->name,
                'slug'       => $place->slug,
                'featured'   => (bool) $place->featured,
                'image'      => $place->image,
                'icon'       => $place->icon,
                'hero'       => $place->hero,
                'price'      => is_null($place->price) ? null : (float) $place->price,
                'price_text' => $place->price_text,
                'order'      => $place->order,
                'latitude'   => $place->latitude,
                'longitude'  => $place->longitude,
            ] : null,
            'city'               => $city ? [
                'id'      => $city->id,
                'slug'    => $city->slug,
                'name'    => $city->name,
                'state'   => $city->state,
                'country' => $city->country,
                'image'   => $city->image,
            ] : null,
            'specifications'     => $this->formatSpecifications($product->specifications),
            'images'             => $this->formatImages($product->images),
            'layouts'            => $this->formatLayouts($product->layouts),
            'locations'          => $this->formatLocations($product->locations),
        ];
    }

    private function homeBaseQuery(?string $propertyStatus, ?int $cityId = null, ?array $productTypeIds = null): QueryBuilder
    {
        $typeFilter = $productTypeIds ?: self::LISTING_PRODUCT_TYPE_IDS;

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
            ->selectSub(function ($sub) {
                $sub->from('product_images as gi')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('gi.product_id', 'a.id');
            }, 'photos_count')
            ->join('product_locations as l', 'a.id', '=', 'l.product_id')
            ->join('product_types as t', 'a.product_type_id', '=', 't.id')
            ->join('product_specifications as s', 'a.id', '=', 's.product_id')
            ->join('places as ad1', 'ad1.id', '=', 'l.place_id')
            ->join('cities as ad2', 'ad1.city_id', '=', 'ad2.id')
            ->whereIn('t.id', $typeFilter)
            ->when($propertyStatus, fn(QueryBuilder $query, string $status) => $query->where('a.property_status', $status))
            ->when($cityId, fn(QueryBuilder $query, int $id) => $query->where('ad2.id', $id));
    }

    private function formatHomeResults(Collection $items): array
    {
        return $items
            ->map(function ($row) {
                $payload = (array) $row;
                $payload['product_id'] = (int) $row->product_id;
                $payload['id'] = (int) $row->product_id;
                $payload['price'] = is_null($row->price) ? null : (float) $row->price;
                if (array_key_exists('foto', $payload)) {
                    $payload['featured_image_url'] = $payload['foto'];
                }

                return $payload;
            })
            ->values()
            ->all();
    }

    private function formatSpecifications(Collection $specifications): array
    {
        return $specifications
            ->sortBy('id')
            ->flatMap(function ($specification) {
                $value = $specification->value ?? [];

                if (!is_array($value)) {
                    return [$value];
                }

                return array_values($value);
            })
            ->values()
            ->all();
    }

    private function formatImages(Collection $images): array
    {
        return $images
            ->map(function ($image) {
                return [
                    'id'         => $image->id,
                    'url'        => $image->url,
                    'featured'   => (bool) $image->featured,
                    'created_at' => optional($image->created_at)?->toDateTimeString(),
                    'updated_at' => optional($image->updated_at)?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    private function formatLayouts(Collection $layouts): array
    {
        return $layouts
            ->map(function ($layout) {
                return [
                    'id'          => $layout->id,
                    'image'       => $layout->image,
                    'description' => $layout->description,
                    'created_at'  => optional($layout->created_at)?->toDateTimeString(),
                    'updated_at'  => optional($layout->updated_at)?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    private function formatLocations(Collection $locations): array
    {
        return $locations
            ->map(function ($location) {
                return [
                    'id'         => $location->id,
                    'address'    => $location->address,
                    'latitude'   => $location->latitude,
                    'longitude'  => $location->longitude,
                    'place_id'   => $location->place_id,
                    'product_id' => $location->product_id,
                    'created_at' => optional($location->created_at)?->toDateTimeString(),
                    'updated_at' => optional($location->updated_at)?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    private function formatArray($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_null($value)) {
            return [];
        }

        return [$value];
    }
}
