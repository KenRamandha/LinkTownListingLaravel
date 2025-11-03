<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Models\Products\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductController extends Controller
{
    public function home(Request $request)
    {
        $validated = $request->validate([
            'property_status' => 'nullable|string',
            'limit'           => 'nullable|integer|min:1|max:20',
        ]);

        $limit = $validated['limit'] ?? 5;
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

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search'            => 'nullable|string',
            'status'            => 'nullable|string',
            'property_status'   => 'nullable',
            'property_statuses' => 'nullable',
            'product_type_id'   => 'nullable',
            'product_type_ids'  => 'nullable',
            'developer_id'      => 'nullable',
            'project_id'        => 'nullable',
            'user_id'           => 'nullable',
            'place_id'          => 'nullable',
            'price_min'         => 'nullable|numeric|min:0',
            'price_max'         => 'nullable|numeric|min:0',
            'featured_partner'  => 'nullable',
            'label'             => 'nullable|string',
            'tags'              => 'nullable',
            'sort'              => 'nullable|in:newest,oldest,price_asc,price_desc',
            'per_page'          => 'nullable|integer|min:1|max:100',
            'page'              => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['per_page'] ?? 12;
        $sort = $validated['sort'] ?? 'newest';
        $statusFilter = $validated['status'] ?? 'Published';

        try {
            $query = Product::query()->with(['images', 'locations']);

            if ($statusFilter) {
                $query->where('status', $statusFilter);
            }

            $propertyStatuses = $this->parseStringInputs($request->input('property_status'), $request->input('property_statuses'));
            if (!empty($propertyStatuses)) {
                $query->whereIn('property_status', $propertyStatuses);
            } else {
                $propertyStatuses = [];
            }

            $productTypeIds = $this->parseIntegerInputs($request->input('product_type_id'), $request->input('product_type_ids'));
            if (!empty($productTypeIds)) {
                $query->whereIn('product_type_id', $productTypeIds);
            }

            foreach ([
                'developer_id' => 'developer_id',
                'project_id'   => 'project_id',
                'user_id'      => 'user_id',
            ] as $input => $column) {
                $value = $request->input($input);
                if (!is_null($value) && $value !== '') {
                    $query->where($column, $value);
                }
            }

            $placeIds = $this->parseIntegerInputs($request->input('place_id'));
            if (!empty($placeIds)) {
                $query->whereHas('locations', function (Builder $builder) use ($placeIds) {
                    $builder->whereIn('place_id', $placeIds);
                });
            }

            if (!is_null($validated['price_min'] ?? null)) {
                $query->where('price', '>=', $validated['price_min']);
            }

            if (!is_null($validated['price_max'] ?? null)) {
                $query->where('price', '<=', $validated['price_max']);
            }

            foreach ($this->parseStringInputs($request->input('tags')) as $tag) {
                $query->where(function (Builder $builder) use ($tag) {
                    $builder->where('tags', 'like', '%' . $tag . '%');
                });
            }

            if ($label = $validated['label'] ?? null) {
                $query->where('label', $label);
            }

            if (!is_null($request->input('featured_partner'))) {
                $featured = filter_var($request->input('featured_partner'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if (!is_null($featured)) {
                    $query->where('featured_partner', $featured);
                }
            }

            if ($search = $validated['search'] ?? null) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('title', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('benefits', 'like', '%' . $search . '%')
                        ->orWhere('tags', 'like', '%' . $search . '%')
                        ->orWhere('around', 'like', '%' . $search . '%');
                });
            }

            $query->when($sort === 'newest', fn (Builder $builder) => $builder->orderByDesc('created_at'))
                ->when($sort === 'oldest', fn (Builder $builder) => $builder->orderBy('created_at'))
                ->when($sort === 'price_asc', fn (Builder $builder) => $builder->orderBy('price')->orderByDesc('created_at'))
                ->when($sort === 'price_desc', fn (Builder $builder) => $builder->orderByDesc('price')->orderByDesc('created_at'));

            if (!in_array($sort, ['newest', 'oldest', 'price_asc', 'price_desc'], true)) {
                $query->orderByDesc('created_at');
            }

            /** @var LengthAwarePaginator $paginator */
            $paginator = $query->paginate($perPage)->withQueryString();

            $items = $paginator->getCollection()
                ->map(fn (Product $product) => $this->transformProduct($product))
                ->all();

            $paginator->setCollection(collect($items));

            return $this->ok([
                'items' => $items,
            ], 'Daftar produk', [
                'pagination' => [
                    'total'        => $paginator->total(),
                    'per_page'     => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                ],
                'applied_filters' => [
                    'status'           => $statusFilter,
                    'property_status'  => $propertyStatuses,
                    'product_type_ids' => $productTypeIds,
                    'place_ids'        => $placeIds,
                    'price_min'        => $validated['price_min'] ?? null,
                    'price_max'        => $validated['price_max'] ?? null,
                    'label'            => $label ?? null,
                    'featured_partner' => isset($featured) ? $featured : null,
                    'sort'             => $sort,
                    'search'           => $search ?? null,
                    'tags'             => $this->parseStringInputs($request->input('tags')),
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat daftar produk', 500, 'SERVER_ERROR');
        }
    }

    public function filters()
    {
        try {
            $payload = $this->buildFiltersPayload();

            return $this->ok($payload, 'Pilihan filter produk');
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat filter produk', 500, 'SERVER_ERROR');
        }
    }

    public function show(Product $product)
    {
        try {
            $payload = $this->buildProductDetail($product);

            return $this->ok($payload, 'Detail produk');
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memuat detail produk', 500, 'SERVER_ERROR');
        }
    }

    private function transformProduct(Product $product, bool $withRelations = false): array
    {
        $product->loadMissing(['images', 'locations']);

        $featuredImage = $product->images
            ->sortByDesc(fn ($image) => (int) $image->featured)
            ->first();

        $base = [
            'id'                => $product->id,
            'slug'              => $product->slug,
            'title'             => $product->title,
            'code'              => $product->code,
            'property_status'   => $product->property_status,
            'status'            => $product->status,
            'price'             => is_null($product->price) ? null : (float) $product->price,
            'price_formatted'   => $this->formatPrice($product->price),
            'price_header'      => $product->price_header,
            'promo'             => $product->promo,
            'label'             => $product->label,
            'label_color'       => $product->label_color,
            'featured_partner'  => (bool) $product->featured_partner,
            'featured_image_url'=> $this->mediaUrl($featuredImage?->url),
            'location'          => $this->transformLocation($product->locations->first()),
            'tags'              => $product->tags ?? [],
            'benefits'          => $product->benefits ?? [],
            'created_at'        => $product->created_at?->toDateTimeString(),
            'updated_at'        => $product->updated_at?->toDateTimeString(),
        ];

        if ($withRelations) {
            $base = array_merge($base, [
                'description'     => $product->description,
                'meta_title'      => $product->meta_title,
                'meta_description'=> $product->meta_description,
                'around'          => $product->around ?? [],
                'hero_title'      => $product->hero_title,
                'hero_subtitle'   => $product->hero_subtitle,
                'hero_list'       => $product->hero_list ?? [],
                'tenant'          => $product->tenant ?? [],
                'link'            => $product->link,
                'youtube'         => $product->youtube,
                'cicilan_per_bulan' => $product->cicilan_per_bulan,
                'developer_id'    => $product->developer_id,
                'product_type_id' => $product->product_type_id,
                'project_id'      => $product->project_id,
                'user_id'         => $product->user_id,
                'image_location'  => $this->mediaUrl($product->image_location),
                'nowa'            => $product->nowa,
                'namawa'          => $product->namawa,
                'rental_terms'    => $product->rental_terms,
                'specifications'  => $product->specifications
                    ->map(fn ($spec) => [
                        'id'    => $spec->id,
                        'value' => $spec->value ?? [],
                    ])
                    ->all(),
                'locations'       => $product->locations
                    ->map(fn ($location) => $this->transformLocation($location))
                    ->all(),
                'layouts'         => $product->layouts
                    ->map(fn ($layout) => [
                        'id'          => $layout->id,
                        'image_url'   => $this->mediaUrl($layout->image),
                        'description' => $layout->description,
                    ])
                    ->all(),
                'images'          => $product->images
                    ->map(fn ($image) => [
                        'id'       => $image->id,
                        'url'      => $this->mediaUrl($image->url),
                        'featured' => (bool) $image->featured,
                    ])
                    ->all(),
            ]);
        }

        return $base;
    }

    private function transformLocation($location): ?array
    {
        if (!$location) {
            return null;
        }

        return [
            'id'        => $location->id,
            'address'   => $location->address,
            'latitude'  => is_null($location->latitude) ? null : (float) $location->latitude,
            'longitude' => is_null($location->longitude) ? null : (float) $location->longitude,
            'place_id'  => $location->place_id,
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        if (Storage::disk('public')->exists($normalized)) {
            return asset('storage/' . $normalized);
        }

        return asset($normalized);
    }

    private function formatPrice($price): ?string
    {
        if (is_null($price)) {
            return null;
        }

        $numeric = is_numeric($price) ? (float) $price : null;

        if (is_null($numeric)) {
            return (string) $price;
        }

        return number_format($numeric, 0, ',', '.');
    }

    private function buildHomePayload(?string $propertyStatus, int $limit): array
    {
        $baseQuery = Product::query()
            ->with(['images', 'locations'])
            ->published()
            ->propertyStatus($propertyStatus);

        $latestProperties = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => $this->transformProduct($product))
            ->values()
            ->all();

        $latestListings = (clone $baseQuery)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => $this->transformProduct($product))
            ->values()
            ->all();

        return [
            'latest_properties' => $latestProperties,
            'latest_listings'   => $latestListings,
        ];
    }

    private function buildFiltersPayload(): array
    {
        $propertyStatuses = Product::query()
            ->whereNotNull('property_status')
            ->distinct()
            ->orderBy('property_status')
            ->pluck('property_status')
            ->values()
            ->all();

        $labels = Product::query()
            ->whereNotNull('label')
            ->distinct()
            ->orderBy('label')
            ->pluck('label')
            ->values()
            ->all();

        $priceRange = Product::query()
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $productTypes = $this->safeLookup('product_types', ['id', 'name']);
        $developers = $this->safeLookup('partners', ['id', 'name']);
        $projects = $this->safeLookup('projects', ['id', 'name']);
        $places = [];

        if (Schema::hasTable('places') && Schema::hasTable('product_locations')) {
            $places = DB::table('places')
                ->join('product_locations', 'product_locations.place_id', '=', 'places.id')
                ->select('places.id', 'places.name')
                ->distinct()
                ->orderBy('places.name')
                ->get()
                ->map(fn ($row) => [
                    'id'   => $row->id,
                    'name' => $row->name,
                ])
                ->all();
        }

        $tags = Product::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(function ($value) {
                $decoded = is_array($value) ? $value : json_decode($value ?? '[]', true) ?? [];
                return collect($decoded)->filter(fn ($tag) => is_string($tag) && $tag !== '');
            })
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'property_statuses' => $propertyStatuses,
            'labels'            => $labels,
            'price_range'       => [
                'min' => $priceRange?->min_price ? (float) $priceRange->min_price : null,
                'max' => $priceRange?->max_price ? (float) $priceRange->max_price : null,
            ],
            'product_types'     => $productTypes,
            'developers'        => $developers,
            'projects'          => $projects,
            'places'            => $places,
            'tags'              => $tags,
        ];
    }

    private function buildProductDetail(Product $product): array
    {
        $product->load([
            'specifications',
            'locations',
            'layouts',
            'images',
        ]);

        return $this->transformProduct($product, true);
    }

    private function parseStringInputs(...$values): array
    {
        $results = [];
        foreach ($values as $value) {
            if (is_null($value)) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $results = array_merge($results, $this->parseStringInputs($item));
                }
                continue;
            }

            if (is_string($value)) {
                $segments = preg_split('/[,|]/', $value);
                foreach ($segments as $segment) {
                    $segment = trim($segment);
                    if ($segment !== '') {
                        $results[] = $segment;
                    }
                }
                continue;
            }

            $results[] = (string) $value;
        }

        return array_values(array_unique($results));
    }

    private function parseIntegerInputs(...$values): array
    {
        return array_values(array_unique(array_map(
            fn ($value) => (int) $value,
            array_filter($this->parseStringInputs(...$values), fn ($item) => is_numeric($item))
        )));
    }

    private function safeLookup(string $table, array $columns): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $selectColumns = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $selectColumns[] = $column;
            }
        }

        if (empty($selectColumns)) {
            return [];
        }

        return DB::table($table)
            ->select($selectColumns)
            ->orderBy($selectColumns[1] ?? $selectColumns[0])
            ->get()
            ->map(function ($row) use ($selectColumns) {
                $payload = [];
                foreach ($selectColumns as $column) {
                    $payload[$column] = $row->{$column};
                }
                return $payload;
            })
            ->all();
    }
}
