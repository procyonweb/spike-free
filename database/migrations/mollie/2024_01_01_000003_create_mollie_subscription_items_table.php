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
        Schema::create('mollie_subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mollie_subscription_id');
            $table->string('mollie_subscription_item_id')->nullable();
            $table->string('plan');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->index('mollie_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mollie_subscription_items');
    }
};
