<?php

use App\Enums\PickupStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('product_name_snapshot');
            $table->string('unit_snapshot');
            $table->unsignedBigInteger('price_at_purchase');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('subtotal');
            $table->enum('pickup_status', array_column(PickupStatus::cases(), 'value'))->default(PickupStatus::Waiting->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
