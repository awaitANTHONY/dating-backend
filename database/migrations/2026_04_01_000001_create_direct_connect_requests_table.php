<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contact platforms (WhatsApp, Telegram, Instagram, etc.)
        if (!Schema::hasTable('contact_platforms')) {
            Schema::create('contact_platforms', function (Blueprint $table) {
                $table->id();
                $table->string('name');            // e.g. WhatsApp
                $table->string('icon')->nullable(); // URL or asset name
                $table->string('placeholder')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();
            });

            // Seed default platforms
            DB::table('contact_platforms')->insert([
                ['name' => 'WhatsApp',   'icon' => 'whatsapp',   'placeholder' => '+1234567890',     'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Telegram',   'icon' => 'telegram',   'placeholder' => '@username',        'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Instagram',  'icon' => 'instagram',  'placeholder' => '@username',        'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Snapchat',   'icon' => 'snapchat',   'placeholder' => 'username',         'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Phone',      'icon' => 'phone',      'placeholder' => '+1234567890',      'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // User contacts (which platforms a user has set up)
        if (!Schema::hasTable('user_contacts')) {
            Schema::create('user_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('contact_platform_id');
                $table->string('value');           // the actual username / number
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('contact_platform_id')->references('id')->on('contact_platforms')->onDelete('cascade');
                $table->unique(['user_id', 'contact_platform_id']);
            });
        }

        // Direct connect requests
        if (!Schema::hasTable('direct_connect_requests')) {
            Schema::create('direct_connect_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('requester_id');   // user who sent the request
                $table->unsignedBigInteger('owner_id');       // user who received the request
                $table->unsignedBigInteger('platform_id')->nullable(); // requested contact platform
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->integer('coins_spent')->default(0);
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->foreign('requester_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('platform_id')->references('id')->on('contact_platforms')->onDelete('set null');

                // prevent duplicate pending requests
                $table->index(['requester_id', 'owner_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_connect_requests');
        Schema::dropIfExists('user_contacts');
        Schema::dropIfExists('contact_platforms');
    }
};
