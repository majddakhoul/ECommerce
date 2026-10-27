<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['Laptop 15 inch', 'Lightweight laptop for daily work', 850.00, 600.00, 25, 'electronics'],
            ['Wireless Mouse', 'Ergonomic wireless mouse', 18.50, 9.00, 120, 'electronics'],
            ['Mechanical Keyboard', 'Backlit mechanical keyboard', 65.00, 38.00, 60, 'electronics'],
            ['Cotton T-Shirt', 'Soft cotton t-shirt', 12.00, 5.00, 200, 'clothing'],
            ['Denim Jacket', 'Classic denim jacket', 55.00, 30.00, 40, 'clothing'],
            ['Running Shoes', 'Breathable running shoes', 70.00, 40.00, 80, 'clothing'],
            ['Clean Code', 'A handbook of agile software craftsmanship', 32.00, 18.00, 50, 'books'],
            ['Design Patterns', 'Elements of reusable object-oriented software', 38.00, 22.00, 35, 'books'],
            ['Desk Lamp', 'LED desk lamp with dimmer', 22.00, 11.00, 90, 'home'],
            ['Coffee Maker', 'Programmable drip coffee maker', 48.00, 27.00, 30, 'home'],
        ];

        foreach ($products as [$name, $description, $price, $cost, $stock, $category]) {
            Product::firstOrCreate(
                ['name' => $name],
                compact('description', 'price', 'cost', 'stock', 'category')
            );
        }
    }
}
