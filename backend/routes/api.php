<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::get('/tenant-host', function () {
    $tenant = \App\Models\Tenant::query()
        ->where('status', 'Active')
        ->with('domains')
        ->orderBy('created_at')
        ->first();

    $domain = $tenant?->domains->sortBy('domain')->first();

    return response()->json([
        'success' => $domain !== null,
        'data' => [
                'domain' => $domain?->domain,
                'alias' => $tenant?->alias,
        ],
    ]);
});

if (! function_exists('grocery_storefront_products')) {
    function grocery_storefront_products(): array
    {
        return [
            [
                'id' => 1,
                'category_id' => 3,
                'name' => 'Farm Fresh Eggs',
                'slug' => 'farm-fresh-eggs',
                'description' => 'Cage-free eggs packed for breakfast, baking, and quick family meals.',
                'unit' => '12 Piece',
                'price' => 4.50,
                'compare_at_price' => 5.00,
                'discount_label' => '12 Piece',
                'badge' => 'Fresh Arrivals',
                'image_url' => 'https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?auto=format&fit=crop&w=500&q=80',
                'rating' => 4.9,
                'reviews_count' => 1200,
                'sold_count' => 120,
                'stock_status' => 'In-Stock',
                'is_featured' => true,
            ],
            [
                'id' => 2,
                'category_id' => 2,
                'name' => 'Fresh Tomatoes',
                'slug' => 'fresh-tomatoes',
                'description' => 'Bright, juicy tomatoes for salads, sauces, sandwiches, and everyday cooking.',
                'unit' => '1kg',
                'price' => 3.00,
                'compare_at_price' => 5.00,
                'discount_label' => '-40%',
                'badge' => 'Flash Sale',
                'image_url' => 'https://images.unsplash.com/photo-1592841200221-a6898f307baa?auto=format&fit=crop&w=500&q=80',
                'rating' => 4.7,
                'reviews_count' => 256,
                'sold_count' => 835,
                'stock_status' => 'In-Stock',
                'is_featured' => true,
            ],
            [
                'id' => 3,
                'category_id' => 1,
                'name' => 'Premium Strawberries',
                'slug' => 'premium-strawberries',
                'description' => 'Sweet red strawberries selected fresh for desserts, smoothies, and snacks.',
                'unit' => '500g',
                'price' => 5.99,
                'compare_at_price' => 7.99,
                'discount_label' => '-25%',
                'badge' => 'New',
                'image_url' => 'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?auto=format&fit=crop&w=500&q=80',
                'rating' => 4.8,
                'reviews_count' => 2100,
                'sold_count' => 2500,
                'stock_status' => 'In-Stock',
                'is_featured' => true,
            ],
            [
                'id' => 4,
                'category_id' => 4,
                'name' => 'Wheat Bread',
                'slug' => 'wheat-bread',
                'description' => 'Soft sliced wheat bread with a nutty flavor and tender crumb.',
                'unit' => '1 loaf',
                'price' => 2.50,
                'compare_at_price' => null,
                'discount_label' => null,
                'badge' => 'Free Delivery',
                'image_url' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=500&q=80',
                'rating' => 4.9,
                'reviews_count' => 765,
                'sold_count' => 5000,
                'stock_status' => 'In-Stock',
                'is_featured' => true,
            ],
            [
                'id' => 5,
                'category_id' => 1,
                'name' => 'Banana',
                'slug' => 'banana',
                'description' => 'Naturally sweet bananas, great for breakfast, baking, or snacking.',
                'unit' => '1 bunch',
                'price' => 2.50,
                'compare_at_price' => 2.80,
                'discount_label' => '-10%',
                'badge' => 'Flash Sale',
                'image_url' => 'https://images.unsplash.com/photo-1603833665858-e61d17a86224?auto=format&fit=crop&w=500&q=80',
                'rating' => 4.7,
                'reviews_count' => 256,
                'sold_count' => 835,
                'stock_status' => 'In-Stock',
                'is_featured' => false,
            ],
            [
                'id' => 6,
                'category_id' => 1,
                'name' => 'Watermelon',
                'slug' => 'watermelon',
                'description' => 'Hydrating watermelon with crisp texture and naturally sweet flavor.',
                'unit' => '1 piece',
                'price' => 4.50,
                'compare_at_price' => null,
                'discount_label' => null,
                'badge' => 'Fresh Arrivals',
                'image_url' => 'https://images.unsplash.com/photo-1589984662646-e7b2e4962f18?auto=format&fit=crop&w=500&q=80',
                'rating' => 4.5,
                'reviews_count' => 235,
                'sold_count' => 300,
                'stock_status' => 'In-Stock',
                'is_featured' => false,
            ],
        ];
    }
}

Route::get('/storefront', function () {
    return response()->json([
        'brand' => [
            'name' => 'Grocely',
            'tagline' => 'Fresh groceries, delivered daily',
            'location' => '245 Madison Avenue, NY',
        ],
        'categories' => [
            ['id' => 1, 'name' => 'Fruits', 'slug' => 'fruits', 'image_url' => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?auto=format&fit=crop&w=240&q=80'],
            ['id' => 2, 'name' => 'Vegetable', 'slug' => 'vegetable', 'image_url' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=240&q=80'],
            ['id' => 3, 'name' => 'Dairy', 'slug' => 'dairy', 'image_url' => 'https://images.unsplash.com/photo-1628088062854-d1870b4553da?auto=format&fit=crop&w=240&q=80'],
            ['id' => 4, 'name' => 'Bakery', 'slug' => 'bakery', 'image_url' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=240&q=80'],
            ['id' => 5, 'name' => 'Snacks', 'slug' => 'snacks', 'image_url' => 'https://images.unsplash.com/photo-1599490659213-e2b9527bd087?auto=format&fit=crop&w=240&q=80'],
        ],
        'products' => grocery_storefront_products(),
        'coupons' => [
            ['id' => 1, 'code' => 'GIFT23', 'title' => 'GET $2 OFF', 'description' => 'Use this on checkout today.', 'discount_type' => 'fixed', 'discount_value' => 2, 'minimum_subtotal' => 3],
            ['id' => 2, 'code' => 'CASHBACK20', 'title' => 'GET 50% OFF', 'description' => 'Add items worth $2 more to unlock.', 'discount_type' => 'percent', 'discount_value' => 50, 'minimum_subtotal' => 20],
        ],
        'addresses' => [
            ['id' => 1, 'type' => 'Home', 'line' => '245 Madison Avenue, NY', 'is_default' => true],
            ['id' => 2, 'type' => 'Office', 'line' => '245 Madison Avenue, NY', 'is_default' => false],
            ['id' => 3, 'type' => 'Home 2', 'line' => '4517 Washington Ave. Manchester', 'is_default' => false],
        ],
        'delivery_options' => [
            ['id' => 'regular', 'name' => 'Regular', 'description' => 'Estimated arrival 2-3 Hours', 'fee' => 2],
            ['id' => 'special', 'name' => 'Special', 'description' => 'Estimated arrival 30 Min', 'fee' => 3],
        ],
        'payment_methods' => [
            ['id' => 'bank-card', 'name' => 'Bank Card', 'detail' => '123456 78452465'],
            ['id' => 'paypal', 'name' => 'PayPal', 'detail' => 'paypal@example.com'],
            ['id' => 'apple-pay', 'name' => 'Apple Pay', 'detail' => 'apple@example.com'],
            ['id' => 'google-pay', 'name' => 'Google Pay', 'detail' => 'google@example.com'],
        ],
    ]);
});

Route::post('/orders', function (Request $request) {
    $data = $request->validate([
        'items' => ['required', 'array', 'min:1'],
        'items.*.product_id' => ['required', 'integer'],
        'items.*.quantity' => ['required', 'integer', 'min:1'],
        'address.type' => ['required', 'string'],
        'address.line' => ['required', 'string'],
        'delivery_type' => ['required', 'string'],
        'payment_method' => ['required', 'string'],
        'coupon_code' => ['nullable', 'string'],
    ]);

    $products = collect(grocery_storefront_products())->keyBy('id');
    $items = collect($data['items'])->map(function ($item) use ($products) {
        $product = $products->get((int) $item['product_id']);
        $quantity = (int) $item['quantity'];

        return [
            'product_id' => $product['id'] ?? $item['product_id'],
            'product_name' => $product['name'] ?? 'Product',
            'unit' => $product['unit'] ?? 'unit',
            'quantity' => $quantity,
            'unit_price' => (float) ($product['price'] ?? 0),
            'line_total' => round(((float) ($product['price'] ?? 0)) * $quantity, 2),
        ];
    })->values();

    $subtotal = round($items->sum('line_total'), 2);
    $deliveryFee = $data['delivery_type'] === 'special' ? 3 : 2;
    $discount = strtoupper((string) ($data['coupon_code'] ?? '')) === 'GIFT23' && $subtotal >= 3 ? 2 : 0;

    return response()->json([
        'order_number' => 'GR-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
        'status' => 'active',
        'address_type' => $data['address']['type'],
        'address_line' => $data['address']['line'],
        'delivery_type' => $data['delivery_type'],
        'payment_method' => $data['payment_method'],
        'coupon_code' => $data['coupon_code'] ?? null,
        'subtotal' => $subtotal,
        'delivery_fee' => $deliveryFee,
        'discount' => $discount,
        'total' => max(0, round($subtotal + $deliveryFee - $discount, 2)),
        'items' => $items,
    ], 201);
});
