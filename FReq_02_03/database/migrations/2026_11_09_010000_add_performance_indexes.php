<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) { $table->index(['category', 'stock', 'id'], 'products_category_stock_id_index'); });
        Schema::table('orders', function (Blueprint $table) { $table->index(['user_id', 'status', 'id'], 'orders_user_status_id_index'); });
    }
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) { $table->dropIndex('products_category_stock_id_index'); });
        Schema::table('orders', function (Blueprint $table) { $table->dropIndex('orders_user_status_id_index'); });
    }
};
