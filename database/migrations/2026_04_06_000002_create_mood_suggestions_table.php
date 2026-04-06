<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('text', 50);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default mood suggestions
        $moods = [
            ['text' => '😎 Up for anything',  'sort_order' => 1],
            ['text' => '🤝 Let\'s meet up',   'sort_order' => 2],
            ['text' => '💬 Ready to chat',     'sort_order' => 3],
            ['text' => '⚽ Feeling active',    'sort_order' => 4],
            ['text' => '💨 Hookah time?',      'sort_order' => 5],
            ['text' => '🎬 Movie night?',      'sort_order' => 6],
            ['text' => '🎮 Game on!',          'sort_order' => 7],
            ['text' => '💼 Busy working',      'sort_order' => 8],
            ['text' => '🍽️ Grab a bite',       'sort_order' => 9],
            ['text' => '☕ Coffee break?',      'sort_order' => 10],
        ];

        $now = now();
        foreach ($moods as &$mood) {
            $mood['is_active'] = true;
            $mood['created_at'] = $now;
            $mood['updated_at'] = $now;
        }

        DB::table('mood_suggestions')->insert($moods);
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_suggestions');
    }
};
