<?php

namespace Cartxis\Core\Database\Seeders;

use Cartxis\Core\Models\ShippingMethod;
use Cartxis\Core\Models\ShippingRate;
use Cartxis\Core\Support\StoreCountry;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create flat-rate method (default)
        $flatRate = ShippingMethod::create([
            'name' => 'Standard Shipping',
            'slug' => 'standard-shipping',
            'type' => 'flat-rate',
            'base_cost' => 5.00,
            'cost_per_kg' => 0.5,
            'description' => 'Flat rate shipping: ₹5.00 + ₹0.50 per kg',
            'is_default' => true,
            'status' => 'active',
            'display_order' => 1,
        ]);

        // Create weight-based calculated method
        $calculated = ShippingMethod::create([
            'name' => 'Express Shipping',
            'slug' => 'express-shipping',
            'type' => 'calculated',
            'base_cost' => 0,
            'cost_per_kg' => 0,
            'description' => 'Weight-based shipping with rates by weight range',
            'is_default' => false,
            'status' => 'active',
            'display_order' => 2,
        ]);

        // Add rates for calculated method
        // Afghanistan rates
        ShippingRate::create([
            'shipping_method_id' => $calculated->id,
            'country' => StoreCountry::code(),
            'state' => null,
            'min_weight' => 0,
            'max_weight' => 5,
            'base_cost' => 10.00,
            'cost_per_kg' => 1.00,
            'status' => 'active',
        ]);

        ShippingRate::create([
            'shipping_method_id' => $calculated->id,
            'country' => StoreCountry::code(),
            'state' => null,
            'min_weight' => 5,
            'max_weight' => 25,
            'base_cost' => 15.00,
            'cost_per_kg' => 0.75,
            'status' => 'active',
        ]);

        ShippingRate::create([
            'shipping_method_id' => $calculated->id,
            'country' => StoreCountry::code(),
            'state' => null,
            'min_weight' => 25,
            'max_weight' => 100,
            'base_cost' => 25.00,
            'cost_per_kg' => 0.50,
            'status' => 'active',
        ]);

        // Create basic local pickup option
        ShippingMethod::create([
            'name' => 'Local Pickup',
            'slug' => 'local-pickup',
            'type' => 'flat-rate',
            'base_cost' => 0,
            'cost_per_kg' => 0,
            'description' => 'Free local pickup option',
            'is_default' => false,
            'status' => 'active',
            'display_order' => 3,
        ]);
    }
}
