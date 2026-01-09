<?php

namespace App\Http\Controllers\UserProduct;

use App\Http\Controllers\Controller;
use App\Models\Products\City;
use App\Models\Products\Place;
use App\Models\UserProduct\MsArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    // GET /api/locations/provinces - Ambil daftar provinsi
    public function provinces(): JsonResponse
    {
        // Table 'cities' actually stores Provinces
        $provinces = City::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $provinces,
        ]);
    }

    // GET /api/locations/cities?province_id={id} - Ambil daftar kota berdasarkan provinsi
    public function cities(Request $request): JsonResponse
    {
        // 'province_id' parameter refers to 'cities.id'
        $provinceId = $request->query('province_id') ?? $request->query('province');

        if (empty($provinceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter province_id diperlukan',
            ], 400);
        }

        // Table 'places' actually stores Cities/Kabupaten
        // Filter by city_id (which is actually Province ID)
        $cities = Place::where('city_id', $provinceId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $cities,
        ]);
    }

    // GET /api/locations/areas?city_id={id} - Ambil daftar area/kecamatan berdasarkan kota
    public function areas(Request $request): JsonResponse
    {
        // 'city_id' parameter refers to 'places.id'
        $cityId = $request->query('city_id') ?? $request->query('place_id');

        if (empty($cityId)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter city_id diperlukan',
            ], 400);
        }

        // Table 'ms_areas' stores Kecamatan
        // Filter by place_id (which is actually City/Kab ID)
        $areas = MsArea::where('place_id', $cityId)
            ->active()
            ->ordered()
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $areas,
        ]);
    }
}
