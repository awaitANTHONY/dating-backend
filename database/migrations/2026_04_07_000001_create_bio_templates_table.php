<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bio_templates', function (Blueprint $table) {
            $table->id();
            $table->string('text', 500);
            $table->string('gender', 10)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $templates = [
            ['text' => "From {city} 📍\nLiving life one good vibe at a time.\nEasygoing, genuine, and always up for meaningful conversations.", 'gender' => null, 'sort_order' => 1],
            ['text' => "From {city} 📍\nCalm energy, sharp mind, good vibes only.\nI like things simple, genuine, and drama-free.", 'gender' => null, 'sort_order' => 2],
            ['text' => "From {city} 📍\nJust here to meet good people and create nice memories.\nLet's vibe and see where it leads.", 'gender' => null, 'sort_order' => 3],
            ['text' => "From {city} 📍\nHere for good vibes and better connections.\nNo stress, no pressure — just real energy.", 'gender' => null, 'sort_order' => 4],
        ];

        $now = now();
        foreach ($templates as &$tpl) {
            $tpl['is_active']   = true;
            $tpl['created_at']  = $now;
            $tpl['updated_at']  = $now;
        }

        DB::table('bio_templates')->insert($templates);
    }

    public function down(): void
    {
        Schema::dropIfExists('bio_templates');
    }
};
