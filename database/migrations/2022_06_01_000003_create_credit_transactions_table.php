<?php

use Opcodes\Spike\CreditTransaction;
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
        Schema::create('spike_credit_transactions', function (Blueprint $table) {
            $table->id();

            $table->morphs('billable');
            $table->string('type')->default(CreditTransaction::TYPE_ADJUSTMENT);
            $table->bigInteger('credits');
            $table->foreignId('cart_id')->nullable();
            $table->foreignId('cart_item_id')->nullable();
            $table->foreignId('subscription_id')->nullable();
            $table->foreignId('subscription_item_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('spike_credit_transactions');
    }
};
