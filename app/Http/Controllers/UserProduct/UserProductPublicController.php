<?php

namespace App\Http\Controllers\UserProduct;

use App\Http\Controllers\Controller;
use App\Models\UserProduct\MsProduct;
use App\Models\UserProduct\MsProductDetail;
use App\Models\UserProduct\MsProductImage;
use App\Models\UserProduct\MsProductLocation;
use App\Models\UserProduct\MsArea;
use App\Models\Products\City;
use App\Models\Products\Place;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UserProductPublicController extends Controller
{
    // Ambil produk user terbaru untuk halaman home
    public function home(Request $request)
    {
        $validated = Validator::validate($request->all(), [
            'listing_type' => ['nullable', 'string'],
            'limit'        => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $limit = (int) ($validated['limit'] ?? 5);
        $listingType = $validated['listing_type'] ?? null;

        try {
            $payload = $this->buildHomePayload($listingType, $limit);

            return $this->ok($payload, 'Berhasil memuat produk home', [
                'filters' => [
                    'listing_type' => $listingType,
                    'limit'        => $limit,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat data produk', 500, 'SERVER_ERROR');
        }
    }

    // Ambil opsi filter untuk pencarian produk user
    public function searchFilters()
    {
        try {
            $propertyStatuses = MsProductDetail::listingTypes()
                ->orderBy('description')
                ->pluck('description')
                ->values()
                ->all();

            $productNames = MsProductDetail::propertyTypes()
                ->orderBy('description')
                ->get(['id', 'detail_id', 'description', 'icon'])
                ->map(function ($type) {
                    return [
                        'id'    => $type->id,
                        'name'  => $type->description,
                        'slug'  => \Illuminate\Support\Str::slug($type->description),
                        'title' => $type->description,
                        'color' => null,
                        'icon'  => $type->icon,
                    ];
                })
                ->values()
                ->all();

            $productTypes = MsProductDetail::conditions()
                ->orderBy('description')
                ->get(['description'])
                ->map(function ($condition) {
                    return ['slug' => \Illuminate\Support\Str::slug($condition->description)];
                })
                ->all();

            $priceAggregate = MsProduct::published()
                ->selectRaw('MIN(selling_price) as min_selling, MAX(selling_price) as max_selling, MIN(rental_price) as min_rental, MAX(rental_price) as max_rental')
                ->first();

            $minPrices = array_filter([$priceAggregate->min_selling, $priceAggregate->min_rental], fn($v) => !is_null($v));
            $maxPrices = array_filter([$priceAggregate->max_selling, $priceAggregate->max_rental], fn($v) => !is_null($v));

            $priceRange = [
                'min' => !empty($minPrices) ? (float) min($minPrices) : null,
                'max' => !empty($maxPrices) ? (float) max($maxPrices) : null,
            ];

            $payload = [
                'property_statuses' => $propertyStatuses,
                'product_types'     => $productTypes,
                'product_names'     => $productNames,
                'price_range'       => $priceRange,
            ];

            return $this->ok($payload, 'Berhasil memuat opsi filter pencarian produk');
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat opsi filter pencarian produk', 500, 'SERVER_ERROR');
        }
    }

    // Ambil top produk user berdasarkan kota
    public function topByCity(Request $request, int $cityId)
    {
        $validated = Validator::validate($request->all(), [
            'limit'        => ['nullable', 'integer', 'min:1', 'max:20'],
            'listing_type' => ['nullable', 'string'],
        ]);

        $limit = (int) ($validated['limit'] ?? 6);
        $listingType = $validated['listing_type'] ?? null;

        try {
            $products = $this->homeBaseQuery($listingType, $cityId)
                ->orderByDesc('a.created_at')
                ->limit($limit)
                ->get();

            $payload = [
                'products' => $this->formatHomeResults($products),
            ];

            return $this->ok($payload, 'Berhasil memuat produk berdasarkan kota', [
                'filters' => [
                    'city_id'      => $cityId,
                    'limit'        => $limit,
                    'listing_type' => $listingType,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat produk berdasarkan kota', 500, 'SERVER_ERROR');
        }
    }

    // Ambil detail produk user untuk public
    public function show(string $productId)
    {
        try {
            $product = MsProduct::where('product_id', $productId)
                ->published()
                ->with([
                    'images' => fn($query) => $query->orderBy('order'),
                    'displayImages',
                    'layoutImages',
                    'brochureImage',
                    'locations',
                    'listingTypeDetail',
                    'productTypeDetail',
                    'conditionDetail',
                    'labelDetail',
                    'creator.profile',
                    'creator.company',
                ])
                ->firstOrFail();

            $payload = $this->buildProductDetail($product);

            return $this->ok($payload, 'Berhasil memuat detail produk');
        } catch (ModelNotFoundException $e) {
            return $this->fail('Produk tidak ditemukan', 404, 'NOT_FOUND');
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat detail produk', 500, 'SERVER_ERROR');
        }
    }

    // Cari produk user dengan filter
    public function search(Request $request)
    {
        $validated = Validator::validate($request->all(), [
            'q'                 => ['nullable', 'string'],
            'property_statuses' => ['nullable', 'array'],
            'property_statuses.*' => ['string'],
            'product_type_ids'  => ['nullable', 'array'],
            'product_type_ids.*' => ['integer'],
            'product_types'     => ['nullable', 'array'],
            'product_types.*'   => ['string'],
            'city_ids'          => ['nullable', 'array'],
            'city_ids.*'        => ['integer'],
            'place_ids'         => ['nullable', 'array'],
            'place_ids.*'       => ['integer'],
            'min_price'         => ['nullable', 'numeric', 'min:0'],
            'max_price'         => ['nullable', 'numeric', 'min:0'],
            'page'              => ['nullable', 'integer', 'min:1'],
            'per_page'          => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort'              => ['nullable', 'string', 'in:relevance,newest,oldest,price_asc,price_desc'],
        ]);

        $search   = $validated['q'] ?? null;
        $minPrice = $validated['min_price'] ?? null;
        $maxPrice = $validated['max_price'] ?? null;
        $perPage  = (int) ($validated['per_page'] ?? 12);
        $sort     = $validated['sort'] ?? 'relevance';

        $propertyStatuses = $this->sanitizeArray($validated['property_statuses'] ?? []);
        $listingTypes = [];

        if (!empty($propertyStatuses)) {
            $hasSemua = in_array('Semua', $propertyStatuses, true);

            if ($hasSemua) {
                $listingTypes = [];
            } else {
                $otherStatuses = array_filter($propertyStatuses, fn($status) => $status !== 'Semua');

                if (!empty($otherStatuses)) {
                    $listingTypeIds = DB::table('tr_product_detail')
                        ->where('detail_type', 'LISTING_TYPE')
                        ->whereIn('description', $otherStatuses)
                        ->pluck('detail_id')
                        ->all();

                    $listingTypes = array_unique(array_merge($listingTypeIds, $otherStatuses));
                }
            }
        }

        $inputProductTypeIds = $this->sanitizeIntArray($validated['product_type_ids'] ?? []);
        $queryProductTypeIds = [];
        if (!empty($inputProductTypeIds)) {
            $productTypes = DB::table('tr_product_detail')
                ->select(['detail_id', 'description'])
                ->where('detail_type', 'PROPERTY_TYPE')
                ->whereIn('id', $inputProductTypeIds)
                ->get();

            $ptIds = $productTypes->pluck('detail_id')->all();
            $ptDescs = $productTypes->pluck('description')->all();

            $queryProductTypeIds = array_unique(array_merge($ptIds, $ptDescs));
        }

        $inputProductTypes = $this->sanitizeArray($validated['product_types'] ?? []);
        $conditions = [];
        if (!empty($inputProductTypes)) {
            $conditionRecords = DB::table('tr_product_detail')
                ->select(['detail_id', 'description'])
                ->where('detail_type', 'CONDITION')
                ->get();
            
            foreach ($conditionRecords as $record) {
                $slug = \Illuminate\Support\Str::slug($record->description);
                if (in_array($slug, $inputProductTypes, true)) {
                    $conditions[] = $record->detail_id;
                }
            }
        }

        $cityIds      = $this->sanitizeIntArray($validated['city_ids'] ?? []);
        $placeIds     = $this->sanitizeIntArray($validated['place_ids'] ?? []);
        $areaIds      = [];

        try {
            $query = $this->searchBaseQuery(
                $listingTypes,
                $queryProductTypeIds,
                $conditions,
                $cityIds,
                $placeIds,
                $areaIds
            )
                ->when($search, function (QueryBuilder $query, string $keyword) {
                    $like = '%' . $keyword . '%';

                    $query->where(function (QueryBuilder $inner) use ($like) {
                        $inner
                            ->where('a.title', 'like', $like)
                            ->orWhere('a.description', 'like', $like)
                            ->orWhere('a.address', 'like', $like);
                    });
                })
                ->when($minPrice, fn(QueryBuilder $query, $min) => $query->where(function ($q) use ($min) {
                    $q->where('a.selling_price', '>=', $min)
                        ->orWhere('a.rental_price', '>=', $min);
                }))
                ->when($maxPrice, fn(QueryBuilder $query, $max) => $query->where(function ($q) use ($max) {
                    $q->where('a.selling_price', '<=', $max)
                        ->orWhere('a.rental_price', '<=', $max);
                }));

            switch ($sort) {
                case 'price_asc':
                    $query->orderByRaw('COALESCE(a.selling_price, a.rental_price) asc')->orderByDesc('a.created_at');
                    break;
                case 'price_desc':
                    $query->orderByRaw('COALESCE(a.selling_price, a.rental_price) desc')->orderByDesc('a.created_at');
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
                ->unique()
                ->all();

            $imagesByProductId = [];
            if (!empty($productIds)) {
                $images = DB::table('tr_product_image')
                    ->select(['product_id', 'url', 'main', 'order'])
                    ->whereIn('product_id', $productIds)
                    ->where('image_type', 'DISPLAY')
                    ->orderByDesc('main')
                    ->orderBy('order')
                    ->get();

                foreach ($images as $image) {
                    $pid = $image->product_id;
                    if (!array_key_exists($pid, $imagesByProductId)) {
                        $imagesByProductId[$pid] = [];
                    }
                    $imagesByProductId[$pid][] = $this->publicUrl($image->url);
                }
            }

            $products = $items
                ->map(function ($row) use ($imagesByProductId) {
                    $payload = (array) $row;

                    $productId = is_numeric($row->product_id) ? (int) $row->product_id : $row->product_id;
                    $payload['product_id'] = $productId;
                    $payload['id']         = $productId;

                    $payload['price'] = is_null($row->selling_price)
                        ? (is_null($row->rental_price) ? null : (float) $row->rental_price)
                        : (float) $row->selling_price;
                    $payload['sell_price'] = is_null($row->selling_price) ? null : (float) $row->selling_price;
                    $payload['rent_price'] = is_null($row->rental_price) ? null : (float) $row->rental_price;


                    $urls = $imagesByProductId[$productId] ?? null;
                    if (is_array($urls) && !empty($urls)) {
                        $payload['featured_image_url'] = array_values($urls);
                    } else {
                        $payload['featured_image_url'] = [];
                    }

                    $payload['specifications'] = $this->parseSpecifications($row->specification);

                    $payload['facilities'] = $this->parseFacilities($row->facility);

                    if (!empty($row->label)) {
                        $labelDetail = DB::table('tr_product_detail')
                            ->where('detail_id', $row->label)
                            ->where('detail_type', 'LABEL')
                            ->first();
                        $payload['label'] = $labelDetail ? $labelDetail->description : $row->label;
                    } else {
                        $payload['label'] = null;
                    }

                    if (!empty($row->legal)) {
                        $legalDetail = DB::table('tr_product_detail')
                            ->where('detail_id', $row->legal)
                            ->where('detail_type', 'LEGAL')
                            ->first();
                        $payload['legal'] = $legalDetail ? $legalDetail->description : $row->legal;
                    } else {
                        $payload['legal'] = null;
                    }

                    $payload['hero_subtitle']     = $row->label_description ?? '-';
                    $payload['place_name']        = $row->place_name ?? null;
                    $payload['city_name']         = $row->city_name ?? null;
                    $payload['address']           = $row->address ?? null;
                    $payload['contact_name']      = $row->user_name ?? 'Sales';
                    $payload['product_type_name'] = $row->product_type_description ?? 'Rumah';
                    $payload['property_status']   = $row->listing_type_description ?? null;

                    $payload['namawa']            = $row->user_name ?? 'Sales';
                    $payload['nowa']              = $row->user_phone ?? null;
                    $payload['agent_photo_url']   = $row->agent_photo_url ?? null;
                    $payload['agency_name']       = $row->agency_name_db ?? 'Agen Independen';

                    $payload = $this->normalizeSpecificationsPayload($payload);
                    unset(
                        $payload['selling_price'],
                        $payload['rental_price'],
                        $payload['specification'],
                        $payload['facility'],
                        $payload['province'],
                        $payload['city'],
                        $payload['area'],
                        $payload['listing_type'],
                        $payload['product_type'],
                        $payload['condition'],
                        $payload['listing_type_icon'],
                        $payload['product_type_icon'],
                        $payload['condition_icon'],
                        $payload['listing_type_description'],
                        $payload['product_type_description'],
                        $payload['condition_description'],
                        $payload['label_description'],
                        $payload['user_name'],
                        $payload['user_phone'],
                        $payload['agency_name_db']
                    );

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
                'debug_sql' => $query->toSql(),
                'debug_bindings' => $query->getBindings(),
                'filters' => [
                    'q'                 => $search,
                    'property_statuses' => $propertyStatuses,
                    'city_ids'          => $cityIds,
                    'min_price'         => $minPrice,
                    'max_price'         => $maxPrice,
                    'per_page'          => $perPage,
                    'sort'              => $sort,
                    'product_type_ids'  => $inputProductTypeIds,
                    'product_types'     => $inputProductTypes,
                    'place_ids'         => $placeIds,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat daftar produk', 500, 'SERVER_ERROR');
        }
    }

    // Build payload untuk home dengan produk terbaru
    private function buildHomePayload(?string $listingType, int $limit): array
    {
        // Produk baru (CONDITION-1)
        $latestProperties = $this->homeBaseQuery($listingType)
            ->where('a.condition', 'CONDITION-1')
            ->orderByDesc('a.created_at')
            ->limit($limit)
            ->get();

        // Produk bekas (selain CONDITION-1)
        $latestListings = $this->homeBaseQuery($listingType)
            ->where('a.condition', '!=', 'CONDITION-1')
            ->orderByDesc('a.created_at')
            ->limit($limit)
            ->get();

        return [
            'latest_properties' => $this->formatHomeResults($latestProperties),
            'latest_listings'   => $this->formatHomeResults($latestListings),
        ];
    }

    // Build payload detail produk untuk public
    private function buildProductDetail(MsProduct $product): array
    {
        $location = $product->locations->first();

        // Ambil informasi place dan city dari relasi produk
        $city = null;
        $place = null;
        $area = null;

        if ($product->province) {
            $city = City::find($product->province);
        }

        if ($product->city) {
            $place = Place::find($product->city);
        }

        if ($product->area) {
            $area = MsArea::find($product->area);
        }

        return [
            'id'                 => is_numeric($product->product_id) ? (int) $product->product_id : $product->product_id,
            'slug'               => null,
            'title'              => $product->title,
            'code'               => $product->product_id,
            'meta_description'   => null,
            'meta_title'         => null,
            'around'             => [],
            'promo'              => null,
            'description'        => $product->description,
            'benefits'           => [],
            'tags'               => [],
            'price'              => is_null($product->selling_price)
                ? (is_null($product->rental_price) ? null : (float) $product->rental_price)
                : (float) $product->selling_price,
            'sell_price'         => is_null($product->selling_price) ? null : (float) $product->selling_price,
            'rent_price'         => is_null($product->rental_price) ? null : (float) $product->rental_price,
            'cicilan_per_bulan'  => null,
            'label'              => $this->formatLabelsArray($product),
            'label_color'        => null,
            'product_type_id'    => $product->product_type,
            'link'               => null,
            'order'              => null,
            'status'             => $product->status,
            'image_location'     => null,
            'youtube'            => null,
            'hero_title'         => null,
            'hero_list'          => [],
            'price_header'       => [],
            'hero_subtitle'      => $product->labelDetail->description ?? $product->label ?? '-',
            'developer_id'       => null,
            'tenant'             => [],
            'featured_partner'   => false,
            'project_id'         => null,
            'user_id'            => $product->created_by,
            'property_status'    => $product->listingTypeDetail->description ?? $product->listing_type,
            'nowa'               => $product->user_phone,
            'namawa'             => $product->user_name ?? 'Sales',
            'agent_photo_url'    => $product->creator->profile->avatar_url ?? null,
            'agency_name'        => $product->creator->company->name ?? 'Agen Independen',
            'rental_terms'       => $product->rental_terms,
            'created_at'         => optional($product->created_at)?->toDateTimeString(),
            'updated_at'         => optional($product->updated_at)?->toDateTimeString(),
            'product_type'       => $product->productTypeDetail ? [
                'id'          => $product->product_type,
                'name'        => $product->productTypeDetail->description,
                'slug'        => null,
                'title'       => $product->productTypeDetail->description,
                'description' => $product->productTypeDetail->description,
                'meta_title'  => null,
                'meta_description' => null,
                'color'       => null,
                'position'    => null,
                'image'       => $this->publicUrl($product->productTypeDetail->icon),
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
            'specifications'     => $this->formatSpecificationsForDetail($product->specification),
            'images'             => $this->formatImagesDetail($product->displayImages),
            'layouts'            => $this->formatLayoutsDetail($product->layoutImages),
            'brochure'           => $this->publicUrl($product->brochureImage?->url),
            'locations'          => $this->formatLocationsDetail($product->locations),
        ];
    }

    // Base query untuk home dan top by city
    private function homeBaseQuery(?string $listingType = null, ?int $cityId = null): QueryBuilder
    {
        $query = DB::table('tr_product as a')
            ->select([
                'a.product_id',
                'a.title',
                'a.description',
                'a.address',
                'a.selling_price',
                'a.rental_price',
                'a.listing_type',
                'a.product_type',
                'a.condition',
                'a.specification',
                'a.facility',
                'a.label',
                'a.legal',
                'a.user_name',
                'a.user_phone',
                'a.created_at',
                'ltd.description as listing_type_description',
                'ltd.icon as listing_type_icon',
                'ptd.description as product_type_description',
                'ptd.icon as product_type_icon',
                'cd.description as condition_description',
                'cd.icon as condition_icon',
            ])
            ->selectSub(function ($sub) {
                $sub->from('tr_product_image as img')
                    ->select('img.url')
                    ->whereColumn('img.product_id', 'a.product_id')
                    ->where('img.image_type', 'DISPLAY')
                    ->orderByDesc('img.main')
                    ->orderBy('img.order')
                    ->limit(1);
            }, 'main_image')
            ->selectSub(function ($sub) {
                $sub->from('tr_product_image as img')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('img.product_id', 'a.product_id')
                    ->where('img.image_type', 'DISPLAY');
            }, 'photos_count')
            ->selectRaw('cities.name as city_name, cities.state as city_state')
            ->selectRaw('places.name as place_name')
            ->leftJoin('tr_product_detail as ltd', function ($join) {
                $join->on('a.listing_type', '=', 'ltd.detail_id')
                    ->where('ltd.detail_type', '=', 'LISTING_TYPE');
            })
            ->leftJoin('tr_product_detail as ptd', function ($join) {
                $join->on('a.product_type', '=', 'ptd.detail_id')
                    ->where('ptd.detail_type', '=', 'PROPERTY_TYPE');
            })
            ->leftJoin('tr_product_detail as cd', function ($join) {
                $join->on('a.condition', '=', 'cd.detail_id')
                    ->where('cd.detail_type', '=', 'CONDITION');
            })
            ->leftJoin('cities', 'a.province', '=', 'cities.id')
            ->leftJoin('places', 'a.city', '=', 'places.id')
            ->where('a.status', 'Publish')
            ->when($listingType, fn(QueryBuilder $query, string $type) => $query->where('a.listing_type', $type))
            ->when($cityId, fn(QueryBuilder $query, int $id) => $query->where('a.province', $id));

        return $query;
    }

    // Base query untuk search dengan filter
    private function searchBaseQuery(
        array $listingTypes = [],
        array $productTypes = [],
        array $conditions = [],
        array $cityIds = [],
        array $placeIds = [],
        array $areaIds = []
    ): QueryBuilder {
        $query = DB::table('tr_product as a')
            ->select([
                'a.product_id',
                'a.title',
                'a.description',
                'a.address',
                'a.selling_price',
                'a.rental_price',
                'a.listing_type',
                'a.product_type',
                'a.condition',
                'a.province',
                'a.city',
                'a.area',
                'a.specification',
                'a.facility',
                'a.label',
                'a.legal',
                'a.user_name',
                'a.user_phone',
                'a.created_at',
                'ltd.description as listing_type_description',
                'ltd.icon as listing_type_icon',
                'ptd.description as product_type_description',
                'ptd.icon as product_type_icon',
                'cd.description as condition_description',
                'cd.icon as condition_icon',
                'cities.name as city_name',
                'places.name as place_name',
                'ld.description as label_description',
                'up.avatar_url as agent_photo_url',
                'com.name as agency_name_db',
            ])
            ->leftJoin('tr_product_detail as ltd', function ($join) {
                $join->on('a.listing_type', '=', 'ltd.detail_id')
                    ->where('ltd.detail_type', '=', 'LISTING_TYPE');
            })
            ->leftJoin('tr_product_detail as ptd', function ($join) {
                $join->on('a.product_type', '=', 'ptd.detail_id')
                    ->where('ptd.detail_type', '=', 'PROPERTY_TYPE');
            })
            ->leftJoin('tr_product_detail as cd', function ($join) {
                $join->on('a.condition', '=', 'cd.detail_id')
                    ->where('cd.detail_type', '=', 'CONDITION');
            })
            ->leftJoin('tr_product_detail as ld', function ($join) {
                $join->on('a.label', '=', 'ld.detail_id')
                    ->where('ld.detail_type', '=', 'LABEL');
            })
            ->leftJoin('cities', 'a.province', '=', 'cities.id')
            ->leftJoin('places', 'a.city', '=', 'places.id')
            ->leftJoin('users', 'a.created_by', '=', 'users.id')
            ->leftJoin('user_profiles as up', 'users.id', '=', 'up.user_id')
            ->leftJoin('companies as com', 'users.company_id', '=', 'com.id')
            ->where('a.status', 'Publish')
            ->when(!empty($listingTypes), function (QueryBuilder $query) use ($listingTypes) {
                $expandedListingTypes = $listingTypes;

                $hasType1 = in_array('LISTING-TYPE-1', $listingTypes, true) || in_array('Jual', $listingTypes, true);
                $hasType2 = in_array('LISTING-TYPE-2', $listingTypes, true) || in_array('Sewa', $listingTypes, true);

                if ($hasType1 || $hasType2) {
                    $expandedListingTypes[] = 'LISTING-TYPE-3';
                    $expandedListingTypes = array_unique($expandedListingTypes);
                }

                return $query->whereIn('a.listing_type', $expandedListingTypes);
            })
            ->when(!empty($productTypes), fn(QueryBuilder $query) => $query->whereIn('a.product_type', $productTypes))
            ->when(!empty($conditions), fn(QueryBuilder $query) => $query->whereIn('a.condition', $conditions))
            ->when(!empty($cityIds), fn(QueryBuilder $query) => $query->whereIn('a.province', $cityIds))
            ->when(!empty($placeIds), fn(QueryBuilder $query) => $query->whereIn('a.city', $placeIds))
            ->when(!empty($areaIds), fn(QueryBuilder $query) => $query->whereIn('a.area', $areaIds));

        return $query;
    }

    // Format hasil home untuk response
    private function formatHomeResults(Collection $items): array
    {
        return $items
            ->map(function ($row) {
                $payload = (array) $row;

                $productId = is_numeric($row->product_id) ? (int) $row->product_id : $row->product_id;
                $payload['product_id'] = $productId;
                $payload['id']         = $productId;

                $payload['price'] = is_null($row->selling_price)
                    ? (is_null($row->rental_price) ? null : (float) $row->rental_price)
                    : (float) $row->selling_price;
                $payload['sell_price'] = is_null($row->selling_price) ? null : (float) $row->selling_price;
                $payload['rent_price'] = is_null($row->rental_price) ? null : (float) $row->rental_price;

                $payload['property_status'] = $row->listing_type_description ?? null;

                $payload['nowa'] = $row->user_phone ?? null;
                $payload['namawa'] = $row->user_name ?? null;

                $payload['address'] = $row->address ?? null;

                $payload['product_type_name'] = $row->product_type_description ?? null;

                $payload['description'] = $row->description ?? null;

                $payload['hero_subtitle'] = null;

                $payload['place_name'] = $row->place_name ?? null;
                $payload['city_name'] = $row->city_name ?? null;
                $payload['city_state'] = $row->city_state ?? null;

                $payload['foto'] = $row->main_image ?? null;

                $payload['photos_count'] = (int) ($row->photos_count ?? 0);

                if (array_key_exists('main_image', $payload) && !empty($payload['main_image'])) {
                    $payload['featured_image_url'] = $this->publicUrl($payload['main_image']);
                } else {
                    $payload['featured_image_url'] = null;
                }

                $payload['specifications'] = $this->parseSpecifications($row->specification);

                $payload['facilities'] = $this->parseFacilities($row->facility);

                if (!empty($row->label)) {
                    $labelDetail = DB::table('tr_product_detail')
                        ->where('detail_id', $row->label)
                        ->where('detail_type', 'LABEL')
                        ->first();
                    $payload['label'] = $labelDetail ? $labelDetail->description : $row->label;
                } else {
                    $payload['label'] = null;
                }

                if (!empty($row->legal)) {
                    $legalDetail = DB::table('tr_product_detail')
                        ->where('detail_id', $row->legal)
                        ->where('detail_type', 'LEGAL')
                        ->first();
                    $payload['legal'] = $legalDetail ? $legalDetail->description : $row->legal;
                } else {
                    $payload['legal'] = null;
                }

                unset(
                    $payload['selling_price'],
                    $payload['rental_price'],
                    $payload['main_image'],
                    $payload['specification'],
                    $payload['user_name'],
                    $payload['user_phone'],
                    $payload['listing_type'],
                    $payload['product_type'],
                    $payload['condition'],
                    $payload['listing_type_icon'],
                    $payload['product_type_icon'],
                    $payload['condition_icon'],
                    $payload['listing_type_description'],
                    $payload['product_type_description'],
                    $payload['condition_description']
                );

                return $payload;
            })
            ->values()
            ->all();
    }

    // Format koleksi images untuk response
    private function formatImages(Collection $images): array
    {
        return $images
            ->map(function ($image) {
                return [
                    'id'         => $image->id,
                    'url'        => $this->publicUrl($image->url),
                    'main'       => (bool) $image->main,
                    'order'      => $image->order,
                    'created_at' => optional($image->created_at)?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    // Parse JSON specifications menjadi array dengan support multiple format
    private function parseSpecifications(?string $specificationJson): array
    {
        if (empty($specificationJson)) {
            return [];
        }

        $decoded = json_decode($specificationJson, true);

        // Cek jika double-encoded JSON
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (!is_array($decoded)) {
            return [];
        }

        // Cek jika sudah dalam format final
        if (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['key'])) {
            return $decoded;
        }

        // Format ID-based, join dengan tr_product_detail
        $specIds = array_keys($decoded);

        $specDetails = DB::table('tr_product_detail')
            ->whereIn('detail_id', $specIds)
            ->where('detail_type', 'SPEC')
            ->get()
            ->keyBy('detail_id');

        $specifications = [];
        foreach ($decoded as $specId => $value) {
            $detail = $specDetails->get($specId);
            if ($detail) {
                $specifications[] = [
                    'key' => $detail->description,
                    'value' => $value,
                    'icon' => $detail->icon,
                ];
            }
        }

        return $specifications;
    }

    // Parse JSON facilities menjadi array dengan support multiple format
    private function parseFacilities(?string $facilityJson): array
    {
        if (empty($facilityJson)) {
            return [];
        }

        $decoded = json_decode($facilityJson, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded[0]) && is_array($decoded[0])) {
            return array_map(function ($item) {
                return [
                    'name' => $item['name'] ?? $item['key'] ?? '',
                    'icon' => $item['icon'] ?? '',
                ];
            }, $decoded);
        }

        $facilityDetails = DB::table('tr_product_detail')
            ->whereIn('detail_id', $decoded)
            ->where('detail_type', 'FACILITY')
            ->get()
            ->keyBy('detail_id');

        $facilities = [];
        foreach ($decoded as $facilityId) {
            $detail = $facilityDetails->get($facilityId);
            if ($detail) {
                $facilities[] = [
                    'name' => $detail->description,
                    'icon' => $detail->icon,
                ];
            }
        }

        return $facilities;
    }

    // Format specifications untuk detail endpoint dengan support multiple format
    private function formatSpecificationsForDetail(?string $specificationJson): array
    {
        if (empty($specificationJson)) {
            return [];
        }

        $decoded = json_decode($specificationJson, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['key'])) {
            return $decoded;
        }

        $specIds = array_keys($decoded);

        $specDetails = DB::table('tr_product_detail')
            ->whereIn('detail_id', $specIds)
            ->where('detail_type', 'SPEC')
            ->get()
            ->keyBy('detail_id');

        $specifications = [];
        foreach ($decoded as $specId => $value) {
            $detail = $specDetails->get($specId);
            if ($detail) {
                $specifications[] = [
                    'key' => $detail->description,
                    'value' => $value,
                    'icon' => $detail->icon,
                ];
            }
        }

        return $specifications;
    }

    // Format array specifications untuk detail endpoint
    private function formatSpecificationsDetail(?array $specifications): array
    {
        if (empty($specifications)) {
            return [];
        }

        return array_values($specifications);
    }

    // Format images untuk detail endpoint
    private function formatImagesDetail(Collection $images): array
    {
        return $images
            ->map(function ($image) {
                return [
                    'id'         => $image->id,
                    'url'        => $this->publicUrl($image->url),
                    'featured'   => (bool) $image->main,
                    'created_at' => optional($image->created_at)->toDateTimeString(),
                    'updated_at' => null,
                ];
            })
            ->values()
            ->all();
    }

    // Format layouts untuk detail endpoint
    private function formatLayoutsDetail(Collection $layouts): array
    {
        return $layouts
            ->map(function ($layout) {
                return [
                    'id'          => $layout->id,
                    'image'       => $this->publicUrl($layout->url),
                    'description' => null,
                    'created_at'  => optional($layout->created_at)?->toDateTimeString(),
                    'updated_at'  => null,
                ];
            })
            ->values()
            ->all();
    }

    // Format locations untuk detail endpoint
    private function formatLocationsDetail(Collection $locations): array
    {
        return $locations
            ->map(function ($location) {
                return [
                    'id'         => $location->id,
                    'address'    => null,
                    'latitude'   => $location->latitude,
                    'longitude'  => $location->longitude,
                    'place_id'   => null,
                    'product_id' => $location->product_id,
                    'created_at' => optional($location->created_at)?->toDateTimeString(),
                    'updated_at' => optional($location->update_at)->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    // Normalize field JSON dalam payload
    private function normalizeJsonFields(array $payload): array
    {
        if (array_key_exists('specification', $payload) && $payload['specification'] !== null) {
            $decoded = json_decode($payload['specification'], true);
            $payload['specification'] = is_array($decoded) ? $decoded : [];
        }

        if (array_key_exists('facility', $payload) && $payload['facility'] !== null) {
            $decoded = json_decode($payload['facility'], true);
            $payload['facility'] = is_array($decoded) ? $decoded : [];
        }

        return $payload;
    }

    // Normalize payload specifications untuk listing endpoints
    private function normalizeSpecificationsPayload(array $payload): array
    {
        if (array_key_exists('specification', $payload) && $payload['specification'] !== null) {
            $decoded = json_decode($payload['specification'], true);
            $payload['specifications'] = is_array($decoded) ? array_values($decoded) : [];
        } else {
            $payload['specifications'] = [];
        }

        if (array_key_exists('facility', $payload) && $payload['facility'] !== null) {
            $decoded = json_decode($payload['facility'], true);
            $payload['facilities'] = is_array($decoded) ? array_values($decoded) : [];
        }

        unset($payload['specification'], $payload['facility']);

        return $payload;
    }

    // Format labels array dengan description dari tr_product_detail
    private function formatLabelsArray(MsProduct $product): ?array
    {
        $labels = $product->label_array;
        if (empty($labels)) {
            return null;
        }

        $labelDetails = DB::table('tr_product_detail')
            ->whereIn('detail_id', $labels)
            ->where('detail_type', 'LABEL')
            ->pluck('description', 'detail_id');

        return collect($labels)->map(fn($id) => $labelDetails[$id] ?? $id)->values()->all();
    }

    // Convert path storage menjadi public URL
    private function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $path = ltrim($path, '/');

        return asset($path);
    }

    // Sanitize dan clean array values
    private function sanitizeArray(array $input): array
    {
        $result = [];
        foreach ($input as $value) {
            if (!is_null($value) && $value !== '') {
                $result[] = (string) $value;
            }
        }
        return array_values(array_unique($result));
    }

    // Sanitize dan convert array values ke integer
    private function sanitizeIntArray(array $input): array
    {
        $result = [];
        foreach ($input as $value) {
            if (!is_null($value) && $value !== '') {
                $result[] = (int) $value;
            }
        }
        return array_values(array_unique($result));
    }
}
