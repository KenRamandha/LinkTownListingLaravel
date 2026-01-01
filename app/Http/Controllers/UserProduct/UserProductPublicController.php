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
    /**
     * Home endpoint - Get latest products
     * GET /api/user-products/home
     */
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

    /**
     * Search filters endpoint - Get available filter options
     * GET /api/user-products/search/filters
     */
    public function searchFilters()
    {
        try {
            // 1. Property Statuses (mapped from listing types descriptions)
            $propertyStatuses = MsProductDetail::listingTypes()
                ->orderBy('description')
                ->pluck('description')
                ->values()
                ->all();

            // 2. Product Names (mapped from property types)
            // Matches 'product_names' structure in ProductController
            $productNames = MsProductDetail::propertyTypes()
                ->orderBy('description')
                ->get(['id', 'detail_id', 'description', 'icon']) // Fetch 'id' as well
                ->map(function ($type) {
                    return [
                        'id'    => $type->id, // Use integer ID
                        'name'  => $type->description,
                        'slug'  => \Illuminate\Support\Str::slug($type->description), // Generate slug
                        'title' => $type->description,
                        'color' => null,
                        'icon'  => $type->icon, // Keep icon as it might be useful
                    ];
                })
                ->values()
                ->all();

            // 3. Product Types (slugs from conditions)
            $productTypes = MsProductDetail::conditions()
                ->orderBy('description')
                ->get(['description'])
                ->map(function ($condition) {
                    return ['slug' => \Illuminate\Support\Str::slug($condition->description)];
                })
                ->all();

            // 4. Price Range (Unified min/max)
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

    /**
     * Top products by city
     * GET /api/cities/{cityId}/user-products/top
     */
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

    /**
     * Show product detail
     * GET /api/user-products/{productId}
     */
    public function show(string $productId)
    {
        try {
            $product = MsProduct::where('product_id', $productId)
                ->published()
                ->with([
                    'images' => fn($query) => $query->orderBy('order'),
                    'displayImages',
                    'layoutImages',
                    'locations',
                    'listingTypeDetail',
                    'productTypeDetail',
                    'conditionDetail',
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

    /**
     * Search products
     * GET /api/user-products/search
     */
    public function search(Request $request)
    {
        $validated = Validator::validate($request->all(), [
            'q'                 => ['nullable', 'string'],
            'property_statuses' => ['nullable', 'array'], // Formerly listing_types
            'property_statuses.*' => ['string'],
            'product_type_ids'  => ['nullable', 'array'], // Formerly product_types
            'product_type_ids.*' => ['integer'], // Validated as integer to match ProductController
            // 'conditions'    => ['nullable', 'array'], // Removed as not in ProductController
            // 'conditions.*'  => ['string'],
            'city_ids'          => ['nullable', 'array'],
            'city_ids.*'        => ['integer'],
            'place_ids'         => ['nullable', 'array'],
            'place_ids.*'       => ['integer'],
            // 'area_ids'      => ['nullable', 'array'], // Removed as not in ProductController
            // 'area_ids.*'    => ['integer'],
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

        // Map property_statuses (descriptions) to listing_type IDs AND include original descriptions for mixed data support
        $propertyStatuses = $this->sanitizeArray($validated['property_statuses'] ?? []);
        $listingTypes = [];
        if (!empty($propertyStatuses)) {
            $listingTypeIds = DB::table('tr_product_detail')
                ->where('detail_type', 'LISTING_TYPE')
                ->whereIn('description', $propertyStatuses)
                ->pluck('detail_id')
                ->all();
            
            // Filter by both the ID (LISTING-TYPE-X) and the Description (Jual/Sewa) to handle mixed data
            $listingTypes = array_unique(array_merge($listingTypeIds, $propertyStatuses));
        }

        // Map product_type_ids (integers) to detail_ids (strings) AND descriptions for mixed data support
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

            // Filter by both ID (PROPERTY-TYPE-X) and Description (Rumah)
            $queryProductTypeIds = array_unique(array_merge($ptIds, $ptDescs));
        }
        
        $conditions = []; // Default empty
        
        $cityIds      = $this->sanitizeIntArray($validated['city_ids'] ?? []);
        $placeIds     = $this->sanitizeIntArray($validated['place_ids'] ?? []);
        $areaIds      = []; // Default empty

        try {
            $query = $this->searchBaseQuery(
                $listingTypes,
                $queryProductTypeIds, // Pass mapped IDs and descriptions
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
                ->when($minPrice, fn(QueryBuilder $query, $min) => $query->where(function($q) use ($min) {
                    $q->where('a.selling_price', '>=', $min)
                      ->orWhere('a.rental_price', '>=', $min);
                }))
                ->when($maxPrice, fn(QueryBuilder $query, $max) => $query->where(function($q) use ($max) {
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

                    // Use integer product_id like ProductController
                    $productId = is_numeric($row->product_id) ? (int) $row->product_id : $row->product_id;
                    $payload['product_id'] = $productId;
                    $payload['id']         = $productId;
                    
                    // Use 'price' field (prioritize selling_price, fallback to rental_price)
                    $payload['price'] = is_null($row->selling_price) 
                        ? (is_null($row->rental_price) ? null : (float) $row->rental_price)
                        : (float) $row->selling_price;

                    // Use 'featured_image_url' as array like ProductController search
                    $urls = $imagesByProductId[$productId] ?? null;
                    if (is_array($urls) && !empty($urls)) {
                        $payload['featured_image_url'] = array_values($urls);
                    } else {
                        $payload['featured_image_url'] = [];
                    }

                    // Add specifications array (supports both formats)
                    $payload['specifications'] = $this->parseSpecifications($row->specification);

                    // Add facilities array (supports both formats)
                    $payload['facilities'] = $this->parseFacilities($row->facility);

                    // Add label description (join with tr_product_detail)
                    if (!empty($row->label)) {
                        $labelDetail = DB::table('tr_product_detail')
                            ->where('detail_id', $row->label)
                            ->where('detail_type', 'LABEL')
                            ->first();
                        $payload['label'] = $labelDetail ? $labelDetail->description : $row->label;
                    } else {
                        $payload['label'] = null;
                    }

                    // Add legal description (join with tr_product_detail)
                    if (!empty($row->legal)) {
                        $legalDetail = DB::table('tr_product_detail')
                            ->where('detail_id', $row->legal)
                            ->where('detail_type', 'LEGAL')
                            ->first();
                        $payload['legal'] = $legalDetail ? $legalDetail->description : $row->legal;
                    } else {
                        $payload['legal'] = null;
                    }

                    // Add user-friendly fields for Flutter UI
                    $payload['hero_subtitle']     = $row->label_description ?? '-'; // From joined label description
                    $payload['place_name']        = $row->place_name ?? null;      // From joined places table
                    $payload['city_name']         = $row->city_name ?? null;       // From joined cities table
                    $payload['address']           = $row->address ?? null;
                    // $payload['city_state']     = $row->city_state ?? null; // Removed as column missing
                    $payload['contact_name']      = $row->user_name ?? 'Sales';    // Default to 'Sales'
                    $payload['product_type_name'] = $row->product_type_description ?? 'Rumah'; // Default to 'Rumah'
                    $payload['property_status']   = $row->listing_type_description ?? null;
                    
                    // Add agent details for Flutter UI
                    $payload['namawa']            = $row->user_name ?? 'Sales';
                    $payload['nowa']              = $row->user_phone ?? null;
                    $payload['agent_photo_url']   = $row->agent_photo_url ?? null; // Nullable
                    $payload['agency_name']       = $row->agency_name_db ?? 'Agen Independen'; // Default if null
                    
                    // Normalize specifications (same as ProductController)
                    $payload = $this->normalizeSpecificationsPayload($payload);
                    /*
                     * Remove fields that ProductController doesn't return
                     * But keep the new ones we just added!
                     */
                    unset(
                        $payload['selling_price'], 
                        $payload['rental_price'],
                        $payload['specification'],
                        $payload['facility'],
                        // 'province', 'city', 'area' are now mapped to names or used as IDs
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
                        // Clean up temporary join fields if you don't want them in final JSON output
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
                    'property_statuses' => $propertyStatuses, // matched ProductController
                    'city_ids'          => $cityIds,
                    'min_price'         => $minPrice,
                    'max_price'         => $maxPrice,
                    'per_page'          => $perPage,
                    'sort'              => $sort,
                    'product_type_ids'  => $inputProductTypeIds, // Return input IDs (integers)
                    'place_ids'         => $placeIds,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat daftar produk', 500, 'SERVER_ERROR');
        }
    }

    /**
     * Build home payload with latest products
     * Returns same structure as ProductController for Flutter compatibility
     * Separates properties (CONDITION-1/Baru) from listings (other conditions)
     */
    private function buildHomePayload(?string $listingType, int $limit): array
    {
        // Properties: Condition = CONDITION-1 (Baru)
        $latestProperties = $this->homeBaseQuery($listingType)
            ->where('a.condition', 'CONDITION-1')
            ->orderByDesc('a.created_at')
            ->limit($limit)
            ->get();

        // Listings: Condition != CONDITION-1 (Bekas, dll)
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

    /**
     * Build product detail payload
     * Matches ProductController format for Flutter compatibility
     */
    private function buildProductDetail(MsProduct $product): array
    {
        $location = $product->locations->first();
        
        // Get place and city information
        // Note: MsProduct uses different field mapping than Product
        // province -> City, city -> Place, area -> MsArea
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

        // Map MsProduct fields to ProductController format
        return [
            'id'                 => is_numeric($product->product_id) ? (int) $product->product_id : $product->product_id,
            'slug'               => null, // MsProduct doesn't have slug
            'title'              => $product->title,
            'code'               => $product->product_id, // Use product_id as code
            'meta_description'   => null, // MsProduct doesn't have meta fields
            'meta_title'         => null,
            'around'             => [], // MsProduct doesn't have around
            'promo'              => null, // MsProduct doesn't have promo
            'description'        => $product->description,
            'benefits'           => [], // MsProduct doesn't have benefits
            'tags'               => [], // MsProduct doesn't have tags
            'price'              => is_null($product->selling_price) 
                ? (is_null($product->rental_price) ? null : (float) $product->rental_price)
                : (float) $product->selling_price,
            'cicilan_per_bulan'  => null, // MsProduct doesn't have cicilan
            'label'              => $product->label,
            'label_color'        => null, // MsProduct doesn't have label_color
            'product_type_id'    => $product->product_type,
            'link'               => null, // MsProduct doesn't have link
            'order'              => null, // MsProduct doesn't have order
            'status'             => $product->status,
            'image_location'     => null, // MsProduct doesn't have image_location
            'youtube'            => null, // MsProduct doesn't have youtube
            'hero_title'         => null, // MsProduct doesn't have hero fields
            'hero_list'          => [],
            'price_header'       => [],
            'hero_subtitle'      => null,
            'developer_id'       => null, // MsProduct has developer name, not ID
            'tenant'             => [],
            'featured_partner'   => false,
            'project_id'         => null,
            'user_id'            => $product->created_by,
            'property_status'    => $product->listing_type, // Map listing_type to property_status
            'nowa'               => $product->user_phone,
            'namawa'             => $product->user_name,
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
            'locations'          => $this->formatLocationsDetail($product->locations),
        ];
    }

    /**
     * Base query for home and top by city
     */
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
                'a.specification',  // Add specification field
                'a.facility',       // Add facility field (JSON array)
                'a.label',          // Add label field (ID)
                'a.legal',          // Add legal field (ID)
                'a.user_name',      // Add for namawa
                'a.user_phone',     // Add for nowa
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
                // Count photos for photos_count field
                $sub->from('tr_product_image as img')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('img.product_id', 'a.product_id')
                    ->where('img.image_type', 'DISPLAY');
            }, 'photos_count')
            ->selectRaw('cities.name as city_name, cities.state as city_state')
            ->selectRaw('places.name as place_name')
            ->leftJoin('tr_product_detail as ltd', function($join) {
                $join->on('a.listing_type', '=', 'ltd.detail_id')
                     ->where('ltd.detail_type', '=', 'LISTING_TYPE');
            })
            ->leftJoin('tr_product_detail as ptd', function($join) {
                $join->on('a.product_type', '=', 'ptd.detail_id')
                     ->where('ptd.detail_type', '=', 'PROPERTY_TYPE');
            })
            ->leftJoin('tr_product_detail as cd', function($join) {
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

    /**
     * Base query for search
     */
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
                'a.label',          // Add label field
                'a.legal',          // Add legal field
                'a.user_name',      // Add user_name for contact_name
                'a.user_phone',     // Add user_phone for nowa
                // 'a.city_state',  // REMOVED: Column does not exist in tr_product
                'a.created_at',
                'ltd.description as listing_type_description',
                'ltd.icon as listing_type_icon',
                'ptd.description as product_type_description',
                'ptd.icon as product_type_icon',
                'cd.description as condition_description',
                'cd.icon as condition_icon',
                'cities.name as city_name', // Join cities for city_name
                'places.name as place_name', // Join places for place_name
                'ld.description as label_description', // Join label for hero_subtitle
                'up.avatar_url as agent_photo_url',    // Join user_profiles for agent photo
                'com.name as agency_name_db',          // Join companies for agency name
            ])
            ->leftJoin('tr_product_detail as ltd', function($join) {
                $join->on('a.listing_type', '=', 'ltd.detail_id')
                     ->where('ltd.detail_type', '=', 'LISTING_TYPE');
            })
            ->leftJoin('tr_product_detail as ptd', function($join) {
                $join->on('a.product_type', '=', 'ptd.detail_id')
                     ->where('ptd.detail_type', '=', 'PROPERTY_TYPE');
            })
            ->leftJoin('tr_product_detail as cd', function($join) {
                $join->on('a.condition', '=', 'cd.detail_id')
                     ->where('cd.detail_type', '=', 'CONDITION');
            })
            ->leftJoin('tr_product_detail as ld', function($join) { // Join for Label
                $join->on('a.label', '=', 'ld.detail_id')
                     ->where('ld.detail_type', '=', 'LABEL');
            })
            ->leftJoin('cities', 'a.province', '=', 'cities.id') // Join cities table
            ->leftJoin('places', 'a.city', '=', 'places.id')     // Join places table
            ->leftJoin('users', 'a.created_by', '=', 'users.id') // Join users table
            ->leftJoin('user_profiles as up', 'users.id', '=', 'up.user_id') // Join user_profiles table
            ->leftJoin('companies as com', 'users.company_id', '=', 'com.id') // Join companies table
            ->where('a.status', 'Publish')
            ->when(!empty($listingTypes), fn(QueryBuilder $query) => $query->whereIn('a.listing_type', $listingTypes))
            ->when(!empty($productTypes), fn(QueryBuilder $query) => $query->whereIn('a.product_type', $productTypes))
            ->when(!empty($conditions), fn(QueryBuilder $query) => $query->whereIn('a.condition', $conditions))
            ->when(!empty($cityIds), fn(QueryBuilder $query) => $query->whereIn('a.province', $cityIds))
            ->when(!empty($placeIds), fn(QueryBuilder $query) => $query->whereIn('a.city', $placeIds))
            ->when(!empty($areaIds), fn(QueryBuilder $query) => $query->whereIn('a.area', $areaIds));

        return $query;
    }

    /**
     * Format home results
     * Matches ProductController format for Flutter compatibility
     */
    private function formatHomeResults(Collection $items): array
    {
        return $items
            ->map(function ($row) {
                $payload = (array) $row;

                // Use integer product_id like ProductController
                $productId = is_numeric($row->product_id) ? (int) $row->product_id : $row->product_id;
                $payload['product_id'] = $productId;
                $payload['id']         = $productId;
                
                // Use 'price' field (prioritize selling_price, fallback to rental_price)
                $payload['price'] = is_null($row->selling_price) 
                    ? (is_null($row->rental_price) ? null : (float) $row->rental_price)
                    : (float) $row->selling_price;

                // Add property_status (from listing_type_description)
                $payload['property_status'] = $row->listing_type_description ?? null;

                // Add nowa and namawa
                $payload['nowa'] = $row->user_phone ?? null;
                $payload['namawa'] = $row->user_name ?? null;

                // Keep address
                $payload['address'] = $row->address ?? null;

                // Add product_type_name (from product_type_description)
                $payload['product_type_name'] = $row->product_type_description ?? null;

                // Add description (keep it)
                $payload['description'] = $row->description ?? null;

                // Add hero_subtitle (null for MsProduct)
                $payload['hero_subtitle'] = null;

                // Add place_name, city_name, city_state
                $payload['place_name'] = $row->place_name ?? null;
                $payload['city_name'] = $row->city_name ?? null;
                $payload['city_state'] = $row->city_state ?? null;

                // Add foto (original field before featured_image_url)
                $payload['foto'] = $row->main_image ?? null;

                // Add photos_count
                $payload['photos_count'] = (int) ($row->photos_count ?? 0);

                // Use 'featured_image_url' like ProductController (single string, not array)
                if (array_key_exists('main_image', $payload) && !empty($payload['main_image'])) {
                $payload['featured_image_url'] = $this->publicUrl($payload['main_image']);
                } else {
                    $payload['featured_image_url'] = null;
                }

                // Add specifications array (supports both formats)
                $payload['specifications'] = $this->parseSpecifications($row->specification);

                // Add facilities array (supports both formats)
                $payload['facilities'] = $this->parseFacilities($row->facility);

                // Add label description (join with tr_product_detail)
                if (!empty($row->label)) {
                    $labelDetail = DB::table('tr_product_detail')
                        ->where('detail_id', $row->label)
                        ->where('detail_type', 'LABEL')
                        ->first();
                    $payload['label'] = $labelDetail ? $labelDetail->description : $row->label;
                } else {
                    $payload['label'] = null;
                }

                // Add legal description (join with tr_product_detail)
                if (!empty($row->legal)) {
                    $legalDetail = DB::table('tr_product_detail')
                        ->where('detail_id', $row->legal)
                        ->where('detail_type', 'LEGAL')
                        ->first();
                    $payload['legal'] = $legalDetail ? $legalDetail->description : $row->legal;
                } else {
                    $payload['legal'] = null;
                }

                // Remove fields that ProductController doesn't return in home
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

    /**
     * Format images collection
     */
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

    /**
     * Parse specifications - supports both formats:
     * 1. ID-based: {"SPEC-1":"200"} -> joins with tr_product_detail
     * 2. Direct array: [{"key":"Luas Tanah","value":"90m²","icon":"..."}] -> returns as-is
     * 3. Double-encoded: "[{\"key\":\"...\"}]" -> decode twice
     */
    private function parseSpecifications(?string $specificationJson): array
    {
        if (empty($specificationJson)) {
            return [];
        }

        $decoded = json_decode($specificationJson, true);
        
        // Check if first decode resulted in a string (double-encoded JSON)
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        
        if (!is_array($decoded)) {
            return [];
        }

        // Check if it's already in the final format (has 'key', 'value', 'icon')
        if (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['key'])) {
            // Already in final format, return as-is
            return $decoded;
        }

        // It's ID-based format, need to join with tr_product_detail
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

    /**
     * Parse facilities - supports both formats:
     * 1. ID-based: ["FACILITY-1","FACILITY-5"] -> joins with tr_product_detail
     * 2. Direct array: [{"name":"Kolam Renang","icon":"..."}] -> returns as-is (with key mapping)
     * 3. Double-encoded: "[{\"key\":\"...\"}]" -> decode twice
     */
    private function parseFacilities(?string $facilityJson): array
    {
        if (empty($facilityJson)) {
            return [];
        }

        $decoded = json_decode($facilityJson, true);
        
        // Check if first decode resulted in a string (double-encoded JSON)
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        
        if (!is_array($decoded)) {
            return [];
        }

        // Check if it's already in object format (has 'key' or 'name')
        if (isset($decoded[0]) && is_array($decoded[0])) {
            // Map 'key' to 'name' if needed for consistency
            return array_map(function($item) {
                return [
                    'name' => $item['name'] ?? $item['key'] ?? '',
                    'icon' => $item['icon'] ?? '',
                ];
            }, $decoded);
        }

        // It's ID-based format (array of strings), need to join with tr_product_detail
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

    /**
     * Format specifications for detail endpoint (matches ProductController)
     * Supports both formats:
     * 1. ID-based: {"SPEC-1":"200"} -> joins with tr_product_detail
     * 2. Direct array: [{"key":"Luas Tanah","value":"90m²","icon":"..."}] -> returns as-is
     * 3. Double-encoded: "[{\"key\":\"...\"}]" -> decode twice
     */
    private function formatSpecificationsForDetail(?string $specificationJson): array
    {
        if (empty($specificationJson)) {
            return [];
        }

        $decoded = json_decode($specificationJson, true);
        
        // Check if first decode resulted in a string (double-encoded JSON)
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        
        if (!is_array($decoded)) {
            return [];
        }

        // Check if it's already in the final format (has 'key', 'value', 'icon')
        if (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['key'])) {
            // Already in final format, return as-is
            return $decoded;
        }

        // It's ID-based format, need to join with tr_product_detail
        $specIds = array_keys($decoded);
        
        // Get spec details from tr_product_detail
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

    /**
     * Format specifications for detail endpoint (matches ProductController)
     */
    private function formatSpecificationsDetail(?array $specifications): array
    {
        if (empty($specifications)) {
            return [];
        }

        // Flatten array if nested
        return array_values($specifications);
    }

    /**
     * Format images for detail endpoint (matches ProductController)
     */
    private function formatImagesDetail(Collection $images): array
    {
        return $images
            ->map(function ($image) {
                return [
                    'id'         => $image->id,
                    'url'        => $this->publicUrl($image->url),
                    'featured'   => (bool) $image->main, // Map 'main' to 'featured'
                    'created_at' => optional($image->created_at)?->toDateTimeString(),
                    'updated_at' => null, // MsProductImage doesn't have updated_at
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Format layouts for detail endpoint (matches ProductController)
     */
    private function formatLayoutsDetail(Collection $layouts): array
    {
        return $layouts
            ->map(function ($layout) {
                return [
                    'id'          => $layout->id,
                    'image'       => $this->publicUrl($layout->url), // Use 'url' field
                    'description' => null, // MsProductImage doesn't have description
                    'created_at'  => optional($layout->created_at)?->toDateTimeString(),
                    'updated_at'  => null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Format locations for detail endpoint (matches ProductController)
     */
    private function formatLocationsDetail(Collection $locations): array
    {
        return $locations
            ->map(function ($location) {
                return [
                    'id'         => $location->id,
                    'address'    => null, // MsProductLocation doesn't have address
                    'latitude'   => $location->latitude,
                    'longitude'  => $location->longitude,
                    'place_id'   => null, // MsProductLocation doesn't have place_id
                    'product_id' => $location->product_id,
                    'created_at' => optional($location->created_at)?->toDateTimeString(),
                    'updated_at' => optional($location->update_at)?->toDateTimeString(), // Note: typo in model
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Normalize JSON fields in payload
     */
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

    /**
     * Normalize specifications payload for listing endpoints (home, search, topByCity)
     * Matches ProductController format
     */
    private function normalizeSpecificationsPayload(array $payload): array
    {
        // Decode specification field if exists
        if (array_key_exists('specification', $payload) && $payload['specification'] !== null) {
            $decoded = json_decode($payload['specification'], true);
            $payload['specifications'] = is_array($decoded) ? array_values($decoded) : [];
        } else {
            $payload['specifications'] = [];
        }

        // Decode facility field if exists
        if (array_key_exists('facility', $payload) && $payload['facility'] !== null) {
            $decoded = json_decode($payload['facility'], true);
            $payload['facilities'] = is_array($decoded) ? array_values($decoded) : [];
        }

        // Remove original fields
        unset($payload['specification'], $payload['facility']);

        return $payload;
    }

    /**
     * Convert storage path to public URL
     */
    private function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $path = ltrim($path, '/');

        return asset($path);
    }

    /**
     * Sanitize array values
     */
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

    /**
     * Sanitize integer array values
     */
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
