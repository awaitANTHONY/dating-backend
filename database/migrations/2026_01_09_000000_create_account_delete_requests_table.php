<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountDeleteRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_delete_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->enum('type', ['1', '2'])->comment("1 - Clear Data, 2 - Clear Data & Account");
            $table->tinyInteger('accepted')->default(0);
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
        Schema::dropIfExists('account_delete_requests');
    }
}
