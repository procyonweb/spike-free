<?php

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
        Schema::create('paddle_subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paddle_subscription_id');
            $table->string('product_id');
            $table->string('price_id');
            $table->string('status');
            $table->integer('quantity');
            $table->timestamps();

            $table->unique(['paddle_subscription_id', 'price_id'], 'psi_paddle_subscription_id_price_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paddle_subscription_items');
    }
};
