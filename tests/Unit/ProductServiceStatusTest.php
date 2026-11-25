<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Modules\Product\Business\ProductService;
use PHPUnit\Framework\TestCase;

class ProductServiceStatusTest extends TestCase
{
    public function test_it_sets_availability_and_skips_saving_an_unchanged_status(): void
    {
        $product = new class extends Product {
            public int $saveCalls = 0;

            public function save(array $options = []): bool
            {
                $this->saveCalls++;

                return true;
            }
        };
        $product->active = false;

        $service = new ProductService();

        $service->setActive($product, true);
        $service->setActive($product, true);

        self::assertTrue($product->active);
        self::assertSame(1, $product->saveCalls);
    }
}
