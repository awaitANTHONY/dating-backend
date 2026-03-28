<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add server-configurable daily limits to the settings table.
 *
 * These columns let the admin change free-user caps from the backend
 * without requiring an app update:
 *   - daily_like_limit  (default 20)
 *   - daily_chat_limit  (default 5)
 *
 * The Flutter app reads these from GET /api/v1/settings and falls back
 * to the same defaults when the keys are absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Option A: settings table has one row per platform (key-value columns)
        if (Schema::hasTable('settings') && !Schema::hasColumn('settings', 'daily_like_limit')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('daily_like_limit')->default('20')->after('enable_live');
                $table->string('daily_chat_limit')->default('5')->after('daily_like_limit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn(['daily_like_limit', 'daily_chat_limit']);
            });
        }
    }
};
