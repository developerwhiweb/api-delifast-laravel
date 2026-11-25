<?php

namespace Tests\Unit;

use App\Http\Requests\ProductStoreRequest;
use PHPUnit\Framework\TestCase;

class ProductStoreRequestTest extends TestCase
{
    public function test_locked_category_from_query_overrides_submitted_category(): void
    {
        $request = new class extends ProductStoreRequest {
            public function prepare(): void
            {
                $this->prepareForValidation();
            }
        };

        $request->replace(['product_category_id' => '10']);
        $request->query->set('product_category_id', '25');
        $request->prepare();

        self::assertSame('25', $request->input('product_category_id'));
    }
}
