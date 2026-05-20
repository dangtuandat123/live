<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy user đầu tiên (hoặc tạo mới)
        $user = User::first();
        if (!$user) {
            return;
        }

        $products = [
            [
                'name' => 'Áo thun basic cotton',
                'sku' => 'AT-001',
                'price' => 189000,
                'category' => 'Áo',
                'keywords' => ['áo thun', 'áo phông', 'basic tee', 'áo cotton'],
                'image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=80&h=80&fit=crop&auto=format',
            ],
            [
                'name' => 'Quần jean slim fit',
                'sku' => 'QJ-002',
                'price' => 450000,
                'category' => 'Quần',
                'keywords' => ['quần jean', 'quần bò', 'slim fit', 'quần dài'],
                'image' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=80&h=80&fit=crop&auto=format',
            ],
            [
                'name' => 'Váy hoa mùa hè',
                'sku' => 'VH-003',
                'price' => 320000,
                'category' => 'Váy',
                'keywords' => ['váy hoa', 'váy mùa hè', 'đầm hoa', 'dress'],
                'image' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=80&h=80&fit=crop&auto=format',
            ],
            [
                'name' => 'Túi xách da PU',
                'sku' => 'TX-004',
                'price' => 280000,
                'category' => 'Phụ kiện',
                'keywords' => ['túi xách', 'túi da', 'túi PU', 'handbag'],
                'image' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=80&h=80&fit=crop&auto=format',
            ],
            [
                'name' => 'Giày sneaker trắng',
                'sku' => 'GS-005',
                'price' => 520000,
                'category' => 'Giày',
                'keywords' => ['giày sneaker', 'giày thể thao', 'giày trắng', 'sneaker'],
                'image' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=80&h=80&fit=crop&auto=format',
            ],
            [
                'name' => 'Kính mát thời trang',
                'sku' => 'KM-006',
                'price' => 150000,
                'category' => 'Phụ kiện',
                'keywords' => ['kính mát', 'kính râm', 'sunglasses', 'mắt kính'],
                'image' => 'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=80&h=80&fit=crop&auto=format',
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, ['user_id' => $user->id])
            );
        }
    }
}
