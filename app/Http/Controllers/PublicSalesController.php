<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublicSalesController extends Controller
{
    public function index(Request $r)
    {
        try {
            $user = $r->user();
            $companyId = $user->company_id ?? 'CMP-ACME';

            $q = DB::table('listings')
                ->join('properties','properties.id','=','listings.property_id')
                ->where('properties.company_id',$companyId);

            if (!$user) {
                $q->where('listings.status','published');
                $data = $q->select(
                    'listings.id','listings.listing_type','listings.price','listings.currency','listings.status',
                    'listings.thumbnail_url','listings.gallery_json','listings.video_url',
                    'properties.title','properties.type','properties.address','properties.cover_photo_url'
                )->orderByDesc('listings.updated_at')->limit(50)->get();

                return $this->ok($data, 'Public listings', ['public'=>true]);
            }

            $canView = $user->hasPermission('sales.listing:view');
            if (!$canView) $q->where('listings.status','published');

            $data = $q->select(
                'listings.*',
                'properties.title','properties.type','properties.address','properties.cover_photo_url'
            )->orderByDesc('listings.updated_at')->limit(50)->get();

            return $this->ok($data, 'Listings', ['public'=>!$canView]);
        } catch (Throwable $e) {
            report($e);
            return $this->fail('Gagal memuat listings', 500, 'SERVER_ERROR');
        }
    }
}
