<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('spike_credit_transactions', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->string('credit_type')->default('credits')->after('type');
            } else {
                $table->string('credit_type')->default('credits');
            }

            $table->dropIndex('spike_credit_transactions_billable_type_billable_id_index');

            $table->index(['billable_type', 'billable_id', 'credit_type'], 'credit_transactions_billable_type_billable_id_credit_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('spike_credit_transactions', function (Blueprint $table) {
            $table->dropIndex('credit_transactions_billable_type_billable_id_credit_type_index');

            $table->index(['billable_type', 'billable_id'], 'spike_credit_transactions_billable_type_billable_id_index');

            $table->dropColumn(['credit_type']);
        });
    }
};
