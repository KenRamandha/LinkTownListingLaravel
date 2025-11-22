<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_endpoint_works_without_query(): void
    {
        $response = $this->getJson('/api/products/search');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'products'   => [],
                    'pagination' => [],
                ],
            ]);
    }

    public function test_search_endpoint_accepts_q_and_property_status(): void
    {
        $response = $this->getJson('/api/products/search?q=rumah&property_status=Jual');

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'products',
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);
    }
}

