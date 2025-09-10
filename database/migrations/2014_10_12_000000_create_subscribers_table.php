<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscribersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            
            $table->string('redeem_code', 100);
            $table->string('phone', 100)->nullable();;
            $table->string('email', 191)->nullable();;
            $table->bigInteger('subscription_id')->default(0);
            $table->string('expired_at', 100)->nullable();
            $table->text('device_token')->nullable();
            $table->integer('status')->default(1);

            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
}
