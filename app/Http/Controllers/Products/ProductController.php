<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Models\Products\Product;
use App\Models\Products\ProductType;
use App\Models\Products\City;
use App\Models\Products\Place;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ProductController extends Controller
{
    private const PROPERTY_PRODUCT_TYPE_IDS = [1, 2, 3, 4];
    private const LISTING_PRODUCT_TYPE_IDS  = [5, 6, 7, 10, 11];

    public function search(Request $request)
    {
        $validated = Validator::validate($request->all(), [
            'q'                 => ['nullable', 'string'],
            'property_statuses' => ['nullable', 'array'],
            'property_statuses.*' => ['string'],
            'city_ids'          => ['nullable', 'array'],
            'city_ids.*'        => ['integer'],
            'min_price'         => ['nullable', 'numeric', 'min:0'],
            'max_price'         => ['nullable', 'numeric', 'min:0'],
            'page'              => ['nullable', 'integer', 'min:1'],
            'per_page'          => ['nullable', 'integer', 'min:1', 'max:50'],
            'product_type_id'   => ['nullable', 'integer'],
            'product_type_ids'  => ['nullable', 'array'],
            'product_type_ids.*'=> ['integer'],
            'place_id'          => ['nullable', 'integer'],
            'place_ids'         => ['nullable', 'array'],
            'place_ids.*'       => ['integer'],
            'sort'              => ['nullable', 'string', 'in:relevance,newest,oldest,price_asc,price_desc'],
        ]);

        $search   = $validated['q'] ?? null;
        $minPrice = $validated['min_price'] ?? null;
        $maxPrice = $validated['max_price'] ?? null;
        $perPage  = (int) ($validated['per_page'] ?? 12);

        $sort = $validated['sort'] ?? 'relevance';

        $propertyStatuses = [];
        if (!empty($validated['property_statuses']) && is_array($validated['property_statuses'])) {
            foreach ($validated['property_statuses'] as $status) {
                if (!is_null($status) && $status !== '') {
                    $propertyStatuses[] = (string) $status;
                }
            }
        }
        $propertyStatuses = array_values(array_unique($propertyStatuses));

        $cityIds = [];
        if (!empty($validated['city_ids']) && is_array($validated['city_ids'])) {
            foreach ($validated['city_ids'] as $id) {
                if (!is_null($id) && $id !== '') {
                    $cityIds[] = (int) $id;
                }
            }
        }
        $cityIds = array_values(array_unique($cityIds));

        $productTypeIds = [];
        if (array_key_exists('product_type_id', $validated) && !is_null($validated['product_type_id'])) {
            $productTypeIds[] = (int) $validated['product_type_id'];
        }
        if (!empty($validated['product_type_ids']) && is_array($validated['product_type_ids'])) {
            foreach ($validated['product_type_ids'] as $id) {
                $productTypeIds[] = (int) $id;
            }
        }

        $placeIds = [];
        if (array_key_exists('place_id', $validated) && !is_null($validated['place_id'])) {
            $placeIds[] = (int) $validated['place_id'];
        }
        if (!empty($validated['place_ids']) && is_array($validated['place_ids'])) {
            foreach ($validated['place_ids'] as $id) {
                $placeIds[] = (int) $id;
            }
        }

        $productTypeIds = array_values(array_unique(array_filter($productTypeIds)));
        $placeIds       = array_values(array_unique(array_filter($placeIds)));

        try {
            $defaultProductTypeIds = array_merge(self::PROPERTY_PRODUCT_TYPE_IDS, self::LISTING_PRODUCT_TYPE_IDS);

            if (empty($productTypeIds)) {
                $productTypeIds = $defaultProductTypeIds;
            } else {
                $productTypeIds = array_values(array_intersect($productTypeIds, $defaultProductTypeIds));

                if (empty($productTypeIds)) {
                    $productTypeIds = [-1];
                }
            }

            /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
            $query = $this->homeBaseQuery(null, null, $productTypeIds, $placeIds, $cityIds)
                ->when($search, function (QueryBuilder $query, string $keyword) {
                    $like = '%' . $keyword . '%';

                    $query->where(function (QueryBuilder $inner) use ($like) {
                        $inner
                            ->where('a.title', 'like', $like)
                            ->orWhere('a.hero_subtitle', 'like', $like)
                            ->orWhere('ad1.name', 'like', $like)
                            ->orWhere('l.address', 'like', $like)
                            ->orWhere('ad2.name', 'like', $like)
                            ->orWhere('ad2.state', 'like', $like);
                    });
                })
                ->when(!empty($propertyStatuses), fn(QueryBuilder $query) => $query->whereIn('a.property_status', $propertyStatuses))
                ->when($minPrice, fn(QueryBuilder $query, $min) => $query->where('a.price', '>=', $min))
                ->when($maxPrice, fn(QueryBuilder $query, $max) => $query->where('a.price', '<=', $max));

            switch ($sort) {
                case 'price_asc':
                    $query->orderBy('a.price', 'asc')->orderByDesc('a.created_at');
                    break;
                case 'price_desc':
                    $query->orderBy('a.price', 'desc')->orderByDesc('a.created_at');
                    break;
                case 'oldest':
                    $query->orderBy('a.created_at', 'asc');
                    break;
                case 'newest':
                case 'relevance':
                default:
                    $query->orderByDesc('a.created_at');
                    break;
            }

            $paginator = $query->paginate($perPage);

            $items = collect($paginator->items());
            $productIds = $items
                ->pluck('product_id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->all();

            $imagesByProductId = [];
            if (!empty($productIds)) {
                $images = DB::table('product_images')
                    ->select(['product_id', 'url'])
                    ->whereIn('product_id', $productIds)
                    ->orderByDesc('featured')
                    ->orderBy('id')
                    ->get();

                foreach ($images as $image) {
                    $pid = (int) $image->product_id;
                    if (!array_key_exists($pid, $imagesByProductId)) {
                        $imagesByProductId[$pid] = [];
                    }
                    $imagesByProductId[$pid][] = $this->publicUrl($image->url);
                }
            }

            $products = $items
                ->map(function ($row) use ($imagesByProductId) {
                    $payload = (array) $row;
                    $productId = (int) $row->product_id;

                    $payload['product_id'] = $productId;
                    $payload['id']         = $productId;
                    $payload['price']      = is_null($row->price) ? null : (float) $row->price;

                    $urls = $imagesByProductId[$productId] ?? null;
                    if (is_array($urls) && !empty($urls)) {
                        $payload['featured_image_url'] = array_values($urls);
                    } elseif (array_key_exists('foto', $payload) && !empty($payload['foto'])) {
                        $payload['featured_image_url'] = [$this->publicUrl($payload['foto'])];
                    } else {
                        $payload['featured_image_url'] = [];
                    }

                    return $payload;
                })
                ->values()
                ->all();

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
                    'q'                => $search,
                    'property_statuses'=> $propertyStatuses,
                    'city_ids'         => $cityIds,
                    'min_price'        => $minPrice,
                    'max_price'        => $maxPrice,
                    'per_page'         => $perPage,
                    'sort'             => $sort,
                    'product_type_ids' => $productTypeIds,
                    'place_ids'        => $placeIds,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat daftar produk', 500, 'SERVER_ERROR');
        }
    }

    public function searchFilters()
    {
        try {
            $allProductTypeIds = array_merge(self::PROPERTY_PRODUCT_TYPE_IDS, self::LISTING_PRODUCT_TYPE_IDS);

            $productTypes = ProductType::query()
                ->whereIn('id', $allProductTypeIds)
                ->orderBy('position')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'title', 'color'])
                ->map(function (ProductType $type) {
                    return [
                        'id'    => $type->id,
                        'name'  => $type->name,
                        'slug'  => $type->slug,
                        'title' => $type->title,
                        'color' => $type->color,
                    ];
                })
                ->values()
                ->all();

            $propertyStatuses = Product::query()
                ->published()
                ->whereIn('product_type_id', $allProductTypeIds)
                ->whereNotNull('property_status')
                ->select('property_status')
                ->distinct()
                ->orderBy('property_status')
                ->pluck('property_status')
                ->values()
                ->all();

            $priceAggregate = Product::query()
                ->published()
                ->whereIn('product_type_id', $allProductTypeIds)
                ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                ->first();

            $priceRange = [
                'min' => $priceAggregate && $priceAggregate->min_price !== null
                    ? (float) $priceAggregate->min_price
                    : null,
                'max' => $priceAggregate && $priceAggregate->max_price !== null
                    ? (float) $priceAggregate->max_price
                    : null,
            ];

            $cities = City::query()
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'state'])
                ->map(function (City $city) {
                    return [
                        'id'    => $city->id,
                        'slug'  => $city->slug,
                        'name'  => $city->name,
                        'state' => $city->state,
                    ];
                })
                ->values()
                ->all();

            $places = Place::query()
                ->with('city:id,name')
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'city_id', 'name', 'slug', 'order'])
                ->map(function (Place $place) {
                    return [
                        'id'        => $place->id,
                        'city_id'   => $place->city_id,
                        'city_name' => $place->city?->name,
                        'name'      => $place->name,
                        'slug'      => $place->slug,
                        'order'     => $place->order,
                    ];
                })
                ->values()
                ->all();

            $payload = [
                'property_statuses' => $propertyStatuses,
                'product_types'     => $productTypes,
                'price_range'       => $priceRange,
                'cities'            => $cities,
                'places'            => $places,
            ];

            return $this->ok($payload, 'Berhasil memuat opsi filter pencarian produk');
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat opsi filter pencarian produk', 500, 'SERVER_ERROR');
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
                    'images'  => fn($query) => $query->orderByDesc('featured')->orderBy('id'),
                    'layouts' => fn($query) => $query->orderBy('id'),
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
            'image_location'     => $this->publicUrl($product->image_location),
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
                'image'       => $this->publicUrl($product->productType->image),
            ] : null,
            'place'              => $place ? [
                'id'         => $place->id,
                'city_id'    => $place->city_id,
                'name'       => $place->name,
                'slug'       => $place->slug,
                'featured'   => (bool) $place->featured,
                'image'      => $this->publicUrl($place->image),
                'icon'       => $this->publicUrl($place->icon),
                'hero'       => $this->publicUrl($place->hero),
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
                'image'   => $this->publicUrl($city->image),
            ] : null,
            'specifications'     => $this->formatSpecifications($product->specifications),
            'images'             => $this->formatImages($product->images),
            'layouts'            => $this->formatLayouts($product->layouts),
            'locations'          => $this->formatLocations($product->locations),
        ];
    }

    private function homeBaseQuery(?string $propertyStatus, ?int $cityId = null, ?array $productTypeIds = null, ?array $placeIds = null, ?array $cityIds = null): QueryBuilder
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
            ->when($cityId, fn(QueryBuilder $query, int $id) => $query->where('ad2.id', $id))
            ->when($cityIds, fn(QueryBuilder $query, array $ids) => $query->whereIn('ad2.id', $ids))
            ->when($placeIds, fn(QueryBuilder $query, array $ids) => $query->whereIn('ad1.id', $ids));
    }

    private function formatHomeResults(Collection $items): array
    {
        return $items
            ->map(function ($row) {
                $payload = (array) $row;
                $payload['product_id'] = (int) $row->product_id;
                $payload['id']         = (int) $row->product_id;
                $payload['price']      = is_null($row->price) ? null : (float) $row->price;
                if (array_key_exists('foto', $payload) && !empty($payload['foto'])) {
                    $payload['featured_image_url'] = $this->publicUrl($payload['foto']);
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
                    'url'        => $this->publicUrl($image->url),
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
                    'image'       => $this->publicUrl($layout->image),
                    'description' => $layout->description,
                    'created_at'  => optional($layout->created_at)?->toDateTimeString(),
                    'updated_at'  => optional($layout->updated_at)?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    private function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $path = ltrim($path, '/');

        return asset('storage/' . $path);
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
