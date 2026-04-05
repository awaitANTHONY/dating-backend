<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactPlatformsTable extends Migration
{
    public function up()
    {
        Schema::create('contact_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon');
            $table->string('placeholder')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Seed default platforms
        \DB::table('contact_platforms')->insert([
            ['name' => 'WhatsApp', 'icon' => 'whatsapp', 'placeholder' => '+234...', 'sort_order' => 1, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Snapchat', 'icon' => 'snapchat', 'placeholder' => '@username', 'sort_order' => 2, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Instagram', 'icon' => 'instagram', 'placeholder' => '@username', 'sort_order' => 3, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Phone Number', 'icon' => 'phone', 'placeholder' => '+1234567890', 'sort_order' => 4, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('contact_platforms');
    }
}
