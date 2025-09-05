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
        Schema::create('spike_carts', function (Blueprint $table) {
            $table->id();

            $table->morphs('billable');
            $table->string('promotion_code_id')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->string('stripe_checkout_session_id')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('spike_carts');
    }
};
