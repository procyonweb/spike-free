<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('spike_provide_history', function (Blueprint $table) {
            $table->id();

            $table->morphs('billable');
            $table->string('related_item_type')->nullable();
            $table->string('related_item_id')->nullable();
            $table->string('providable_key')->nullable();
            $table->text('providable_data')->nullable();
            $table->timestamp('provided_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('exception')->nullable();

            $table->index(['related_item_id', 'related_item_type'], 'related_item_index');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spike_provide_history');
    }
};
