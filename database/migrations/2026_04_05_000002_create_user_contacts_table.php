<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserContactsTable extends Migration
{
    public function up()
    {
        Schema::create('user_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('contact_platform_id');
            $table->text('value'); // encrypted at application level
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'contact_platform_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_contacts');
    }
}
