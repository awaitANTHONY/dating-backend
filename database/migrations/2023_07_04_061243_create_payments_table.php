<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('user_id');
            $table->string('title');
            $table->string('date', 50);
            $table->string('amount', 50);
            $table->string('platform', 20);
            $table->string('transaction_id')->nullable();
            $table->string('original_transaction_id')->nullable();
            $table->string('payment_type')->default('subscription'); // subscription, verification, boost
        

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
