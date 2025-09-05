<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @package Spike
 * @see https://spike.opcodes.io/docs
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('spike_cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id');
            $table->string('product_id');
            $table->smallInteger('quantity');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('spike_cart_items');
    }
};
