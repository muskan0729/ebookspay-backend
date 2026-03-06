<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransactionFieldsToOrdersTable extends Migration
{
    
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->after('status');
            $table->text('payment_response')->nullable()->after('transaction_id');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['transaction_id', 'payment_response']);
        });
    }

};
