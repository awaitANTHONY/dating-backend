<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed server-configurable daily limits into the settings key-value table.
 *
 * The settings table stores each setting as a row { name, value }.
 * These rows are read by ApiController::settings() and returned to the
 * Flutter app as part of GET /api/v1/settings, where the app uses them to
 * dynamically cap free-user daily likes and new chats without a release.
 *
 * A previous incorrect version of this migration added these as columns
 * instead of rows. This version corrects that by:
 *   1. Dropping the wrong columns if they were created.
 *   2. Inserting the correct key-value rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Remove wrongly-added columns from the old migration version ────
        if (Schema::hasColumn('settings', 'daily_like_limit')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('daily_like_limit');
            });
        }

        if (Schema::hasColumn('settings', 'daily_chat_limit')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('daily_chat_limit');
            });
        }

        // ── 2. Insert or update the correct key-value rows ───────────────────
        $now = now();

        DB::table('settings')->insertOrIgnore([
            ['name' => 'daily_like_limit', 'value' => '20', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'daily_chat_limit', 'value' => '3',  'created_at' => $now, 'updated_at' => $now],
        ]);

        // Ensure the live row reflects the correct default (3) even if it was
        // previously seeded as 5 by an earlier version of this migration.
        DB::table('settings')->where('name', 'daily_chat_limit')->where('value', '5')->update(['value' => '3', 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('name', ['daily_like_limit', 'daily_chat_limit'])->delete();
    }
};
