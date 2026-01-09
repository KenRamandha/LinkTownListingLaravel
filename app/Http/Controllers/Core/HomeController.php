<?php


namespace App\Http\Controllers\Core;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class HomeController extends Controller
{
    // GET /api/home - Ambil daftar listing untuk halaman home
    public function index(Request $r)
    {
        try {
            $user = $r->user();
            $companyId = $user->company_id ?? 'CMP-LT';

            $q = DB::table('listings')
                ->join('properties', 'properties.id', '=', 'listings.property_id')
                ->where('properties.company_id', $companyId);

            if (!$user) $q->where('listings.status', 'published');

            $data = $q->select(
                'listings.id',
                'listings.listing_type',
                'listings.price',
                'listings.currency',
                'listings.status',
                'listings.thumbnail_url',
                'properties.title',
                'properties.type',
                'properties.address',
                'properties.cover_photo_url'
            )->orderByDesc('listings.updated_at')->limit(20)->get();

            return $this->ok([
                'company_id' => $companyId,
                'listings'   => $data
            ], 'Home loaded', ['guest' => !$user]);
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat Home', 500, 'SERVER_ERROR');
        }
    }
}
