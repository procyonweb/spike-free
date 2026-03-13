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
        Schema::create('mollie_orders', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('mollie_order_id')->unique();
            $table->string('mollie_payment_id')->nullable();
            $table->string('mollie_payment_status')->nullable();
            $table->string('number')->nullable();
            $table->string('currency');
            $table->integer('amount');
            $table->text('metadata')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'owner_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mollie_orders');
    }
};
