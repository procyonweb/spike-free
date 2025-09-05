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
        $billableModel = config('spike.billable_models.0', 'App\Models\User');
        $billableForeignKey = (new $billableModel)->getForeignKey();

        Schema::create('stripe_subscriptions', function (Blueprint $table) use ($billableForeignKey) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger($billableForeignKey);
            $table->string('type');
            $table->string('stripe_id')->index();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->string('promotion_code_id')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index([$billableForeignKey, 'stripe_status'], 'ss_billable_id_stripe_status_index');
        });

        if (Schema::hasTable('subscriptions')) {
            // migrate existing subscriptions
            DB::table('subscriptions')
                ->eachById(function ($oldSubscription) use ($billableForeignKey) {
                    DB::table('stripe_subscriptions')->insert([
                        'id' => $oldSubscription->id,
                        $billableForeignKey => $oldSubscription->{$billableForeignKey},
                        'type' => $oldSubscription->name,
                        'stripe_id' => $oldSubscription->stripe_id,
                        'stripe_status' => $oldSubscription->stripe_status,
                        'stripe_price' => $oldSubscription->stripe_price,
                        'promotion_code_id' => $oldSubscription->promotion_code_id ?? null,
                        'quantity' => $oldSubscription->quantity,
                        'trial_ends_at' => $oldSubscription->trial_ends_at,
                        'renews_at' => $oldSubscription->renews_at ?? null,
                        'ends_at' => $oldSubscription->ends_at,
                        'created_at' => $oldSubscription->created_at,
                        'updated_at' => $oldSubscription->updated_at,
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
        Schema::dropIfExists('stripe_subscriptions');
    }
};
