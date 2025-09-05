<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('spike_carts', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->string('paddle_transaction_id')->nullable()->after('stripe_checkout_session_id');
            } else {
                $table->string('paddle_transaction_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('spike_carts', function (Blueprint $table) {
            $table->dropColumn('paddle_transaction_id');
        });
    }
};
