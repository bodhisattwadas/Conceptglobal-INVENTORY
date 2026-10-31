<?php

namespace Tests\Unit;

use App\Enums\PurchaseLocation;
use App\Models\Product;
use App\Models\PurchaseItem;
use Tests\TestCase;

class ProductLocationCastTest extends TestCase
{
    public function test_product_to_array_does_not_throw_cannot_instantiate_enum(): void
    {
        $product = new Product;
        $product->name = 'Test Product';
        $product->location = PurchaseLocation::PARLOUR;

        // Calling toArray() triggers the mutator/caster bug if getLocationAttribute conflicts with enum cast
        $array = $product->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('parlour', $array['location']);
    }

    public function test_product_default_location_is_shop(): void
    {
        $product = new Product;
        $this->assertEquals(PurchaseLocation::SHOP, $product->location);
        $this->assertEquals('shop', $product->toArray()['location']);
    }

    public function test_purchase_item_to_array_does_not_throw_cannot_instantiate_enum(): void
    {
        $item = new PurchaseItem;
        $item->location = PurchaseLocation::PARLOUR;

        $array = $item->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('parlour', $array['location']);
    }
}
