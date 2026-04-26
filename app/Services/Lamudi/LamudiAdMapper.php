<?php

namespace App\Services\Lamudi;

use App\Models\UserProduct\MsProduct;
use App\Models\UserProduct\MsProductImage;
use Illuminate\Support\Facades\Log;

class LamudiAdMapper
{
    private string $publisherId;

    // Facilities that are NOT supported by Proppit API
    private const UNSUPPORTED_FACILITIES = [
        'FACILITY-11' => 'Rumah Sakit (Hospital)',
        'FACILITY-12' => 'Water Park',
        'FACILITY-13' => 'Culinary Area',
        'FACILITY-17' => 'Mosque',
        'FACILITY-18' => 'Bicycle Track',
    ];

    public function __construct(?string $publisherId = null)
    {
        $this->publisherId = $publisherId ?: config('services.lamudi.publisher_id');
    }

    /**
     * Map MsProduct to Lamudi Ad format
     *
     * @param MsProduct $product
     * @param array $images
     * @return array
     * @throws \Exception
     */
    public function mapToLamudiAd(MsProduct $product, array $images = []): array
    {
        // Validate required fields before mapping
        $this->validateRequiredFields($product);

        $location = $product->locations->first();
        $specification = $product->specification_array ?? [];

        // Validate coordinates
        $this->validateCoordinates($location);

        // Validate required fields based on property type
        $this->validateRequiredFieldsByPropertyType($product, $specification);

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
                'text' => $this->sanitizeText($product->title),
            ],
            'description' => [
                'locale' => 'id-ID',
                'text' => $this->sanitizeText($product->description ?? ''),
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
     * Preview data that will be sent to Proppit API
     * Useful for debugging and validation before actual sync
     *
     * @param MsProduct $product
     * @param array $images
     * @return array
     */
    public function previewLamudiAd(MsProduct $product, array $images = []): array
    {
        try {
            $adData = $this->mapToLamudiAd($product, $images);

            // Add validation summary
            $summary = $this->getValidationSummary($product, $adData);

            return [
                'success' => true,
                'data' => $adData,
                'summary' => $summary,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get validation summary for preview
     */
    private function getValidationSummary(MsProduct $product, array $adData): array
    {
        $summary = [
            'product_id' => $product->product_id,
            'has_coordinates' => false,
            'has_geo_hierarchy' => false,
            'has_address' => false,
            'has_images' => false,
            'has_operations' => false,
            'has_floor_area' => false,
            'has_total_area' => false,
            'amenities_count' => 0,
            'skipped_facilities' => [],
        ];

        // Check location data
        $summary['has_coordinates'] = !empty($adData['property']['location']['coordinates']['lat'])
            && !empty($adData['property']['location']['coordinates']['long']);

        $summary['has_geo_hierarchy'] = !empty($adData['property']['location']['geo']);
        $summary['has_address'] = !empty($adData['property']['location']['address']);

        // Check multimedia
        $summary['has_images'] = !empty($adData['multimedia']['pictures']);

        // Check operations
        $summary['has_operations'] = !empty($adData['operations']);

        // Check areas
        $summary['has_floor_area'] = isset($adData['floorArea']);
        $summary['has_total_area'] = isset($adData['totalArea']);

        // Check amenities
        $summary['amenities_count'] = count($adData['amenities'] ?? []);

        // Get skipped facilities
        $facilities = $product->facility_array ?? [];
        foreach ($facilities as $facility) {
            if (isset(self::UNSUPPORTED_FACILITIES[$facility])) {
                $summary['skipped_facilities'][] = [
                    'code' => $facility,
                    'name' => self::UNSUPPORTED_FACILITIES[$facility],
                ];
            }
        }

        return $summary;
    }

    /**
     * Validate required fields for Proppit API
     *
     * @throws \Exception
     */
    private function validateRequiredFields(MsProduct $product): void
    {
        $errors = [];

        if (empty($product->title)) {
            $errors[] = 'Title is required';
        }

        if (empty($product->description)) {
            $errors[] = 'Description is required';
        }

        if (empty($product->listing_type)) {
            $errors[] = 'Listing type is required';
        }

        if (empty($product->owner_email)) {
            $errors[] = 'Owner email is required';
        }

        if (!empty($errors)) {
            throw new \Exception('Validation failed: ' . implode(', ', $errors));
        }
    }

    /**
     * Validate coordinates are valid for geolocation
     * Indonesia coordinates range:
     * - Latitude: -10 to 5
     * - Longitude: 95 to 141
     *
     * @throws \Exception
     */
    private function validateCoordinates(?object $location): void
    {
        if (!$location) {
            throw new \Exception('Location data is missing. Please pin the location on the map.');
        }

        $lat = $location->latitude;
        $long = $location->longitude;

        // Check if coordinates are not null/empty
        if ($lat === null || $long === null) {
            throw new \Exception('Latitude and Longitude are required for Proppit integration.');
        }

        // Check if coordinates are not zero (invalid default)
        if (abs($lat) < 0.001 || abs($long) < 0.001) {
            throw new \Exception('Invalid coordinates (0, 0). Please pin the correct location on the map.');
        }

        // Validate Indonesia coordinate range (with buffer)
        if ($lat < -11 || $lat > 6 || $long < 94 || $long > 142) {
            throw new \Exception(
                "Coordinates ({$lat}, {$long}) are outside Indonesia range. " .
                "Please check the location pin on the map."
            );
        }
    }

    /**
     * Validate required fields based on property type
     * Proppit API requirements:
     * - floorArea MANDATORY for all types except "land"
     * - totalArea MANDATORY for "land"
     *
     * @throws \Exception
     */
    private function validateRequiredFieldsByPropertyType(MsProduct $product, ?array $specification): void
    {
        $propertyType = $this->mapPropertyType($product->product_type);
        $errors = [];

        // Parse specification for validation
        $parsed = [];
        if (!empty($specification)) {
            foreach ($specification as $key => $value) {
                // SPEC codes
                if ($key === 'SPEC-1' || $key === 'Luas Tanah' || $key === 'LT') {
                    $parsed['land_area'] = $value;
                }
                if ($key === 'SPEC-2' || $key === 'Luas Bangunan' || $key === 'LB') {
                    $parsed['building_area'] = $value;
                }
            }
        }

        // floorArea MANDATORY untuk semua tipe kecuali "land"
        if ($propertyType !== 'land') {
            $buildingArea = $parsed['building_area'] ?? null;
            if (empty($buildingArea) || (float) $buildingArea <= 0) {
                $errors[] = "Luas Bangunan (Building Area) wajib diisi untuk tipe properti {$propertyType}";
            }
        }

        // totalArea MANDATORY untuk "land"
        if ($propertyType === 'land') {
            $landArea = $parsed['land_area'] ?? null;
            if (empty($landArea) || (float) $landArea <= 0) {
                $errors[] = "Luas Tanah (Land Area) wajib diisi untuk tanah";
            }
        }

        if (!empty($errors)) {
            throw new \Exception('Validation failed: ' . implode(', ', $errors));
        }
    }

    /**
     * Sanitize text to remove invalid characters
     */
    private function sanitizeText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        // Remove null bytes and other problematic characters
        $text = str_replace(["\0", "\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        return trim($text);
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
        // Validate and get coordinates
        $lat = $location?->latitude;
        $long = $location?->longitude;

        // Build geo hierarchy array for Proppit geolocation
        $geo = [];

        // Add province (administrative_area_level_1)
        if (!empty($product->province)) {
            $geo[] = [
                'name' => $product->province,
                'level' => 'administrative_area_level_1',
            ];
        }

        // Add city (locality)
        if (!empty($product->city)) {
            $geo[] = [
                'name' => $product->city,
                'level' => 'locality',
            ];
        }

        // Add area (sublocality_level_1 or neighborhood)
        if (!empty($product->area)) {
            $geo[] = [
                'name' => $product->area,
                'level' => 'sublocality_level_1',
            ];
        }

        $property = [
            'type' => $this->mapPropertyType($product->product_type),
            'location' => [
                'countryCode' => 'ID',
                'visibility' => 'accurate',
                'coordinates' => [
                    'lat' => $lat,
                    'long' => $long,
                ],
            ],
        ];

        // Add geo array for geolocation (REQUIRED by Proppit API)
        if (!empty($geo)) {
            $property['location']['geo'] = $geo;
        }

        // Add address if exists
        if (!empty($product->address)) {
            $property['location']['address'] = $product->address;
        }

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
     * DISPLAY images → pictures
     * LAYOUT images → floorPlans (NOT in pictures)
     */
    private function mapMultimedia(array $images): array
    {
        $multimedia = [];

        // Separate DISPLAY and LAYOUT images
        $displayImages = array_filter($images, fn($img) =>
            !isset($img['type']) || $img['type'] !== 'LAYOUT'
        );

        $layoutImages = array_filter($images, fn($img) =>
            isset($img['type']) && $img['type'] === 'LAYOUT'
        );

        // Add DISPLAY images to pictures
        if (!empty($displayImages)) {
            $multimedia['pictures'] = [];
            foreach ($displayImages as $image) {
                $url = $image['url'];
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $multimedia['pictures'][] = ['url' => $url];
                }
            }
        }

        // Add LAYOUT images to floorPlans
        if (!empty($layoutImages)) {
            $multimedia['floorPlans'] = [];
            foreach ($layoutImages as $floorPlan) {
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

        // Map usable area (Luas Lain / semi-gross)
        if (!empty($specMapping['usable_area'])) {
            $fields['usableArea'] = [
                'value' => (float) $specMapping['usable_area'],
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

        // Map half bathrooms (Kamar Mandi Pembantu)
        if (!empty($specMapping['half_bathrooms']) || !empty($specMapping['powder_room'])) {
            $halfBath = (int) ($specMapping['half_bathrooms'] ?? $specMapping['powder_room'] ?? 0);
            if ($halfBath > 0) {
                $fields['halfBathrooms'] = $halfBath;
            }
        }

        // Map parking spaces (Carport + Garage)
        $carport = (int) ($specMapping['carport'] ?? 0);
        $garage = (int) ($specMapping['garage'] ?? 0);
        $totalParking = $carport + $garage;
        if ($totalParking > 0) {
            $fields['parkingSpaces'] = $totalParking;
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
            'Garasi' => 'garage',
            'Garage' => 'garage',
            'Dibangun Tahun' => 'year_built',
            'Tahun Dibangun' => 'year_built',
            'Lantai' => 'floor',
            'Furnished' => 'furnished',
            'Luas Lain' => 'usable_area',
            'Semi Gross' => 'usable_area',
            'Kamar Mandi Pembantu' => 'half_bathrooms',
            'Powder Room' => 'powder_room',
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
        // Valid Lamudi amenities (based on API enum)
        $validAmenities = [
            'air conditioning', 'alarm', 'balcony', 'bathtub', 'built-in wardrobe',
            'car park', 'cctv', 'ceiling fan', 'cellar', 'children\'s area',
            'cleaning room', 'concierge', 'daylighting', 'disabled access',
            'electricity', 'equipped bathroom', 'equipped kitchen', 'exterior',
            'fireplace', 'garden', 'green area', 'grill', 'guardhouse', 'gym',
            'heating', 'integral kitchen', 'intercom', 'internet', 'jacuzzi',
            'library', 'lift', 'multiuse room', 'natural gas', 'office',
            'panoramic view', 'roof garden', 'sauna', 'security', 'security door',
            'semi-detached', 'service room', 'shower', 'storage room',
            'swimming pool', 'tennis court', 'terrace', 'tv', 'video cable',
            'water', 'water tank', 'yard', 'badminton court', 'basketball court',
            'club house', 'deck', 'drying area', 'ensuite', 'entertainment room',
            'fire alarm', 'fire exits', 'fire sprinkler system', 'fully fenced',
            'function area', 'gazebo', 'jogging path', 'lanai', 'lounge',
            'multi purpose lawn', 'open space', 'powder room', 'shops',
            'shower rooms', 'smoke detector', 'spa', 'sports facilities',
            'wi-fi', 'twenty four hour security', 'secure parking',
            'outdoor entertaining area', 'hot water', 'telephone', 'pay tv access'
        ];

        // FACILITY codes mapping from database to valid Lamudi amenities
        $facilityCodeMapping = [
            'FACILITY-1' => 'swimming pool',           // Kolam Renang
            'FACILITY-2' => 'jogging path',            // Jogging Track (invalid: jogging track)
            'FACILITY-3' => 'children\'s area',        // Playground
            'FACILITY-4' => 'club house',              // Clubhouse
            'FACILITY-5' => 'twenty four hour security', // Security 24 Jam (invalid: 24 hours security)
            'FACILITY-6' => 'guardhouse',              // One Gate System
            'FACILITY-7' => 'gym',                     // Gym
            'FACILITY-8' => 'garden',                  // Taman
            'FACILITY-9' => 'basketball court',
            'FACILITY-10' => 'shops',                  // Pasar
            'FACILITY-11' => null,                     // Rumah Sakit (not supported)
            'FACILITY-12' => null,                     // Water Park (not supported)
            'FACILITY-13' => null,                     // Culinary Area (not supported)
            'FACILITY-14' => 'green area',             // Green Park
            'FACILITY-15' => 'panoramic view',         // Lake View
            'FACILITY-16' => 'multiuse room',          // Multifunction Room
            'FACILITY-17' => null,                     // Mosque (not supported)
            'FACILITY-18' => null,                     // Bicycle Track (not supported)
            'FACILITY-19' => 'tennis court',
            'FACILITY-20' => 'badminton court',
        ];

        // Indonesian text mapping to valid Lamudi amenities
        $textMapping = [
            'Kolam Renang' => 'swimming pool',
            'Jogging Track' => 'jogging path',
            'Playground' => 'children\'s area',
            'Clubhouse' => 'club house',
            'Security 24 Jam' => 'twenty four hour security',
            'One Gate System' => 'guardhouse',
            'Gym' => 'gym',
            'Taman' => 'garden',
            'Basketball Court' => 'basketball court',
            'Pasar' => 'shops',
            'Rumah Sakit' => null,
            'Water Park' => null,
            'Culinary Area' => null,
            'Green Park' => 'green area',
            'Lake View' => 'panoramic view',
            'Multifunction Room' => 'multiuse room',
            'Mosque' => null,
            'Bicycle Track' => null,
            'Tennis Court' => 'tennis court',
            'Badminton Court' => 'badminton court',
        ];

        $amenities = [];
        $skipped = [];

        foreach ($facilities as $facility) {
            $mapped = null;

            // Check if facility is a FACILITY code
            if (isset($facilityCodeMapping[$facility])) {
                $mapped = $facilityCodeMapping[$facility];
            }
            // Check if facility is Indonesian text
            elseif (isset($textMapping[$facility])) {
                $mapped = $textMapping[$facility];
            }
            // Check if direct text is a valid amenity
            elseif (in_array(strtolower($facility), $validAmenities)) {
                $mapped = strtolower($facility);
            }

            // Only add if mapped and not null
            if ($mapped) {
                $amenities[] = $mapped;
            } else {
                $skipped[] = $facility;
            }
        }

        // Log warning for skipped facilities
        if (!empty($skipped)) {
            Log::warning('Some facilities are not supported by Proppit API and will be skipped', [
                'skipped_facilities' => $skipped,
                'unsupported_count' => count($skipped),
            ]);
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
