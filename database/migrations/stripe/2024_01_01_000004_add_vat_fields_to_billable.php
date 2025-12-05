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
    public function up(): void
    {
        Schema::table($this->getBillableModelTable(), function (Blueprint $table) {
            $table->string('billing_country', 2)->nullable();
            $table->string('vat_id')->nullable();
            $table->string('vat_id_type')->nullable();
            $table->boolean('vat_id_verified')->default(false);
            $table->timestamp('vat_id_verified_at')->nullable();
            $table->string('tax_exempt_status')->nullable();

            $table->string('vies_request_identifier')->nullable();
            $table->string('vies_company_name')->nullable();
            $table->text('vies_company_address')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->getBillableModelTable(), function (Blueprint $table) {
            $table->dropColumn([
                'billing_country',
                'vat_id',
                'vat_id_type',
                'vat_id_verified',
                'vat_id_verified_at',
                'tax_exempt_status',
                'vies_request_identifier',
                'vies_company_name',
                'vies_company_address',
            ]);
        });
    }

    protected function getBillableModelTable(): string
    {
        $billableModel = config('spike.billable_models.0', 'App\Models\User');

        return (new $billableModel)->getTable();
    }
};
