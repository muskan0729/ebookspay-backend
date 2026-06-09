<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            // Initiate API Data
            $table->string('txn_token')->nullable()->after('transaction_id');

            // Status API Data
            $table->string('txn_id')->nullable();
            $table->string('bank_txn_id')->nullable();
            $table->string('payment_mode')->nullable();
            $table->dateTime('txn_date')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('gateway')->default('PAYTM');

            // Full Status API Response
            $table->longText('status_response')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->dropColumn([
                'txn_token',
                'txn_id',
                'bank_txn_id',
                'payment_mode',
                'txn_date',
                'payment_status',
                'gateway',
                'status_response',
            ]);
        });
    }
};