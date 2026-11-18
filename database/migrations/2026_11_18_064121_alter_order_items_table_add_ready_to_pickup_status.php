<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_pickup_status_check');
            DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_pickup_status_check CHECK (pickup_status IN ('waiting', 'ready', 'picked_up', 'out_of_stock'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_pickup_status_check');
            DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_pickup_status_check CHECK (pickup_status IN ('waiting', 'picked_up', 'out_of_stock'))");
        }
    }
};
