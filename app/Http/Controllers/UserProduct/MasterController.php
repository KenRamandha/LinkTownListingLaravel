<?php

namespace App\Http\Controllers\UserProduct;

use App\Http\Controllers\Controller;
use App\Models\UserProduct\MsProductDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterController extends Controller
{
    // GET /api/master/product-details?type={type} - Ambil data master berdasarkan tipe
    public function productDetails(Request $request): JsonResponse
    {
        $type = strtoupper($request->query('type', ''));

        if (empty($type)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter type diperlukan',
            ], 400);
        }

        if (!in_array($type, MsProductDetail::TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type tidak valid. Gunakan: ' . implode(', ', MsProductDetail::TYPES),
            ], 400);
        }

        $details = MsProductDetail::ofType($type)
            ->orderBy('id')
            ->get(['detail_id', 'description', 'icon']);

        return response()->json([
            'success' => true,
            'data' => $details->map(fn($item) => [
                'detail_id' => $item->detail_id,
                'description' => $item->description,
                'icon' => $item->icon,
            ])->values(),
        ]);
    }
}
