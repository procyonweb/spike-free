<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @package Spike
 * @see https://spike.opcodes.io/docs
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stripe_subscription_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('stripe_subscription_id');
            $table->string('stripe_id')->index();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->integer('quantity')->nullable();
            $table->timestamps();

            $table->unique(['stripe_subscription_id', 'stripe_price'], 'ssi_subscription_id_stripe_price_unique');
        });

        if (Schema::hasTable('subscription_items')) {
            // migrate existing subscription items
            DB::table('subscription_items')
                ->eachById(function ($oldSubscriptionItem) {
                    DB::table('stripe_subscription_items')->insert([
                        'id' => $oldSubscriptionItem->id,
                        'stripe_subscription_id' => $oldSubscriptionItem->subscription_id,
                        'stripe_id' => $oldSubscriptionItem->stripe_id,
                        'stripe_product' => $oldSubscriptionItem->stripe_product,
                        'stripe_price' => $oldSubscriptionItem->stripe_price,
                        'quantity' => $oldSubscriptionItem->quantity,
                        'created_at' => $oldSubscriptionItem->created_at,
                        'updated_at' => $oldSubscriptionItem->updated_at,
                    ]);
                });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stripe_subscription_items');
    }
};
