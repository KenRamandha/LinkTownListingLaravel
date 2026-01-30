<?php

namespace App\Services\Lamudi;

use App\Models\UserProduct\MsProduct;
use App\Models\UserProduct\MsProductImage;

class LamudiAdMapper
{
    private string $publisherId;

    public function __construct()
    {
        $this->publisherId = config('services.lamudi.publisher_id');
    }

    /**
     * Map MsProduct to Lamudi Ad format
     *
     * @param MsProduct $product
     * @param array $images
     * @return array
     */
    public function mapToLamudiAd(MsProduct $product, array $images = []): array
    {
        $location = $product->locations->first();
        $specification = $product->specification_array ?? [];

        $ad = [
            'referenceId' => $product->product_id,
            'publisher' => [
                'externalId' => $this->publisherId,
            ],
            'contact' => $this->mapContact($product),
            'property' => $this->mapProperty($product, $location, $specification),
            'operations' => $this->mapOperations($product),
            'title' => [
                'locale' => 'id-ID',
                'text' => $product->title,
            ],
            'description' => [
                'locale' => 'id-ID',
                'text' => $product->description ?? '',
            ],
        ];

        // Add multimedia if images exist
        if (!empty($images)) {
            $ad['multimedia'] = $this->mapMultimedia($images);
        }

        // Add optional fields
        $ad = array_merge($ad, $this->mapOptionalFields($product, $specification));

        return $ad;
    }

    /**
     * Map contact information
     */
    private function mapContact(MsProduct $product): array
    {
        $contact = [
            'name' => $product->user_name ?: $product->owner_name,
            'email' => $product->owner_email,
        ];

        // Format phone number
        $phone = $product->user_phone ?: $product->owner_phone;
        if ($phone) {
            $contact['phone'] = $this->formatPhoneNumber($phone);
            $contact['whatsapp'] = $this->formatPhoneNumber($phone);
        }

        return $contact;
    }

    /**
     * Map property information
     */
    private function mapProperty(MsProduct $product, ?object $location, ?array $specification): array
    {
        $property = [
            'type' => $this->mapPropertyType($product->product_type),
            'location' => [
                'countryCode' => 'ID',
                'visibility' => 'accurate',
                'coordinates' => [
                    'lat' => $location?->latitude,
                    'long' => $location?->longitude,
                ],
                'address' => $product->address,
            ],
        ];

        // Add floor if exists in specification
        if (isset($specification['floor'])) {
            $property['floor'] = (string) $specification['floor'];
        }

        return $property;
    }

    /**
     * Map operations (sell/rent)
     * Mapping from database:
     * - LISTING-TYPE-1 = Jual (Sell)
     * - LISTING-TYPE-2 = Sewa (Rent)
     * - LISTING-TYPE-3 = Semua (Both)
     */
    private function mapOperations(MsProduct $product): array
    {
        $operations = [];
        $listingType = $product->listing_type;

        // LISTING-TYPE-1 or LISTING-TYPE-3 = Jual/Semua → Add sell operation
        if (in_array($listingType, ['LISTING-TYPE-1', 'LISTING-TYPE-3']) && $product->selling_price > 0) {
            $operations[] = [
                'type' => 'sell',
                'price' => [
                    'value' => (float) $product->selling_price,
                    'currency' => 'IDR',
                ],
            ];
        }

        // LISTING-TYPE-2 or LISTING-TYPE-3 = Sewa/Semua → Add rent operation
        if (in_array($listingType, ['LISTING-TYPE-2', 'LISTING-TYPE-3']) && $product->rental_price > 0) {
            $operations[] = [
                'type' => 'rent',
                'price' => [
                    'value' => (float) $product->rental_price,
                    'currency' => 'IDR',
                    'periodicity' => 'monthly',
                ],
            ];
        }

        return $operations;
    }

    /**
     * Map multimedia (images)
     */
    private function mapMultimedia(array $images): array
    {
        $multimedia = ['pictures' => []];

        foreach ($images as $image) {
            $url = $image['url'];
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $multimedia['pictures'][] = ['url' => $url];
            }
        }

        // Add floor plans if layout images exist
        $floorPlans = array_filter($images, fn($img) => isset($img['type']) && $img['type'] === 'LAYOUT');
        if (!empty($floorPlans)) {
            $multimedia['floorPlans'] = [];
            foreach ($floorPlans as $floorPlan) {
                if (filter_var($floorPlan['url'], FILTER_VALIDATE_URL)) {
                    $multimedia['floorPlans'][] = ['url' => $floorPlan['url']];
                }
            }
        }

        return $multimedia;
    }

    /**
     * Map optional fields like area, bedrooms, bathrooms, etc.
     * Handle both format codes (SPEC-1) and Indonesian text (Kamar Tidur)
     */
    private function mapOptionalFields(MsProduct $product, ?array $specification): array
    {
        $fields = [];

        if (empty($specification)) {
            // Still map condition even without specification
            $fields['condition'] = $this->mapCondition($product->condition);
            return $fields;
        }

        // Parse specification - handle both code format and Indonesian text
        $specMapping = $this->parseSpecification($specification);

        // Map building area (Luas Bangunan / SPEC-2)
        if (!empty($specMapping['building_area'])) {
            $fields['floorArea'] = [
                'value' => (float) $specMapping['building_area'],
                'unit' => 'sqm',
            ];
        }

        // Map land area (Luas Tanah / SPEC-1)
        if (!empty($specMapping['land_area'])) {
            $fields['totalArea'] = [
                'value' => (float) $specMapping['land_area'],
                'unit' => 'sqm',
            ];
        }

        // Map bedrooms (Kamar Tidur / SPEC-4)
        if (!empty($specMapping['bedrooms'])) {
            $fields['bedrooms'] = (int) $specMapping['bedrooms'];
        }

        // Map bathrooms (Kamar Mandi / SPEC-3)
        if (!empty($specMapping['bathrooms'])) {
            $fields['bathrooms'] = (int) $specMapping['bathrooms'];
        }

        // Map condition
        $fields['condition'] = $this->mapCondition($product->condition);

        // Map furnished status
        if (!empty($specMapping['furnished'])) {
            $fields['furnished'] = $this->mapFurnished($specMapping['furnished']);
        }

        // Map construction year
        if (!empty($specMapping['year_built']) || !empty($specMapping['construction_year'])) {
            $year = (int) ($specMapping['year_built'] ?? $specMapping['construction_year']);
            if ($year >= 1500 && $year <= 2100) {
                $fields['constructionYear'] = $year;
            }
        }

        // Map amenities from facility
        $facilities = $product->facility_array ?? [];
        if (!empty($facilities)) {
            $fields['amenities'] = $this->mapFacilities($facilities);
        }

        return $fields;
    }

    /**
     * Parse specification array - handle both code format (SPEC-1) and Indonesian text
     */
    private function parseSpecification(array $specification): array
    {
        $parsed = [];

        // SPEC codes mapping from database
        $specCodeMapping = [
            'SPEC-1' => 'land_area',      // Luas Tanah
            'SPEC-2' => 'building_area',  // Luas Bangunan
            'SPEC-3' => 'bathrooms',      // Kamar Mandi
            'SPEC-4' => 'bedrooms',       // Kamar Tidur
        ];

        // Indonesian text mapping
        $textMapping = [
            'Luas Tanah' => 'land_area',
            'LT' => 'land_area',
            'Luas Bangunan' => 'building_area',
            'LB' => 'building_area',
            'Kamar Mandi' => 'bathrooms',
            'KM' => 'bathrooms',
            'Kamar Tidur' => 'bedrooms',
            'KT' => 'bedrooms',
            'Carport' => 'carport',
            'Garage' => 'garage',
            'Dibangun Tahun' => 'year_built',
            'Tahun Dibangun' => 'year_built',
            'Lantai' => 'floor',
            'Furnished' => 'furnished',
        ];

        foreach ($specification as $key => $value) {
            // Check if key is a SPEC code
            if (isset($specCodeMapping[$key])) {
                $parsed[$specCodeMapping[$key]] = $value;
            }
            // Check if key is Indonesian text
            elseif (isset($textMapping[$key])) {
                $parsed[$textMapping[$key]] = $value;
            }
            // Direct mapping
            else {
                $parsed[strtolower(str_replace([' ', '-'], '_', $key))] = $value;
            }
        }

        return $parsed;
    }

    /**
     * Map property type from system to Lamudi format
     * Mapping from database:
     * - PROPERTY-TYPE-1 = Rumah → house
     * - PROPERTY-TYPE-2 = Apartment → apartment
     * - PROPERTY-TYPE-3 = Ruko → commercial (shophouse)
     * - PROPERTY-TYPE-4 = Gudang → warehouse
     * - PROPERTY-TYPE-5 = Office → office
     */
    private function mapPropertyType(?string $productType): string
    {
        $typeMapping = [
            'PROPERTY-TYPE-1' => 'house',
            'PROPERTY-TYPE-2' => 'apartment',
            'PROPERTY-TYPE-3' => 'commercial', // Ruko → commercial/shophouse
            'PROPERTY-TYPE-4' => 'warehouse',  // Gudang → warehouse
            'PROPERTY-TYPE-5' => 'office',
        ];

        return $typeMapping[$productType] ?? 'house';
    }

    /**
     * Map condition to Lamudi format
     * Mapping from database:
     * - CONDITION-1 = Baru → new
     * - CONDITION-2 = Bekas → second hand
     */
    private function mapCondition(?string $condition): string
    {
        $conditionMapping = [
            'CONDITION-1' => 'new',         // Baru
            'CONDITION-2' => 'second hand', // Bekas
        ];

        return $conditionMapping[$condition] ?? 'normal';
    }

    /**
     * Map furnished status
     */
    private function mapFurnished($furnished): string
    {
        if (is_bool($furnished)) {
            return $furnished ? 'fully' : 'unfurnished';
        }

        $furnishedLower = strtolower((string) $furnished);
        if (in_array($furnishedLower, ['full', 'fully', 'yes', '1', 'true'])) {
            return 'fully';
        } elseif (in_array($furnishedLower, ['partial', 'partly', 'semi'])) {
            return 'partly';
        }

        return 'unfurnished';
    }

    /**
     * Map facilities to amenities
     * Handle both FACILITY codes (FACILITY-1) and Indonesian text
     */
    private function mapFacilities(array $facilities): array
    {
        // FACILITY codes mapping from database
        $facilityCodeMapping = [
            'FACILITY-1' => 'swimming pool',      // Kolam Renang
            'FACILITY-2' => 'jogging track',      // Jogging Track
            'FACILITY-3' => 'playground',         // Playground
            'FACILITY-4' => 'clubhouse',
            'FACILITY-5' => '24 hours security',  // Security 24 Jam
            'FACILITY-6' => 'one gate system',
            'FACILITY-7' => 'gym',                // Gym
            'FACILITY-8' => 'garden',             // Taman
            'FACILITY-9' => 'basketball court',
            'FACILITY-10' => 'commercial',        // Pasar
            'FACILITY-11' => 'nearby hospital',   // Rumah Sakit
            'FACILITY-12' => 'water park',
            'FACILITY-13' => 'culinary area',     // Culinary Area
            'FACILITY-14' => 'garden',            // Green Park
            'FACILITY-15' => 'lake view',
            'FACILITY-16' => 'multifunction room',
            'FACILITY-17' => 'mosque',
            'FACILITY-18' => 'bicycle track',
            'FACILITY-19' => 'tennis court',
            'FACILITY-20' => 'badminton court',
        ];

        // Indonesian text mapping
        $textMapping = [
            'Kolam Renang' => 'swimming pool',
            'Jogging Track' => 'jogging track',
            'Playground' => 'playground',
            'Clubhouse' => 'clubhouse',
            'Security 24 Jam' => '24 hours security',
            'One Gate System' => 'one gate system',
            'Gym' => 'gym',
            'Taman' => 'garden',
            'Basketball Court' => 'basketball court',
            'Pasar' => 'commercial',
            'Rumah Sakit' => 'nearby hospital',
            'Water Park' => 'water park',
            'Culinary Area' => 'culinary area',
            'Green Park' => 'garden',
            'Lake View' => 'lake view',
            'Multifunction Room' => 'multifunction room',
            'Mosque' => 'mosque',
            'Bicycle Track' => 'bicycle track',
            'Tennis Court' => 'tennis court',
            'Badminton Court' => 'badminton court',
        ];

        $amenities = [];
        foreach ($facilities as $facility) {
            // Check if facility is a FACILITY code
            if (isset($facilityCodeMapping[$facility])) {
                $amenities[] = $facilityCodeMapping[$facility];
            }
            // Check if facility is Indonesian text
            elseif (isset($textMapping[$facility])) {
                $amenities[] = $textMapping[$facility];
            }
            // Direct text as-is
            else {
                $amenities[] = $facility;
            }
        }

        return array_values(array_unique($amenities));
    }

    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If starts with 0, replace with +62
        if (str_starts_with($phone, '0')) {
            $phone = '+62' . substr($phone, 1);
        }

        // If doesn't start with +, add +62
        if (!str_starts_with($phone, '+')) {
            $phone = '+62' . $phone;
        }

        return $phone;
    }
}
