<?php

use Illuminate\Database\Migrations\Migration;
use Opcodes\Spike\Actions\Migrations\MigrateProvideHistoryFromV2;

return new class extends Migration {
    public function up(): void
    {
        app(MigrateProvideHistoryFromV2::class)->handle();
    }

    public function down(): void
    {
        app(MigrateProvideHistoryFromV2::class)->rollback();
    }
};
