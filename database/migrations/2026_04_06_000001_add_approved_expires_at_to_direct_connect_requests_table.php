<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add approved_expires_at column to contact_requests table.
 *
 * When a request is approved, this column is set to 48 hours from now.
 * After that time, the requester can no longer view the owner's contacts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_requests') && !Schema::hasColumn('contact_requests', 'approved_expires_at')) {
            Schema::table('contact_requests', function (Blueprint $table) {
                $table->timestamp('approved_expires_at')->nullable()->after('responded_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contact_requests') && Schema::hasColumn('contact_requests', 'approved_expires_at')) {
            Schema::table('contact_requests', function (Blueprint $table) {
                $table->dropColumn('approved_expires_at');
            });
        }
    }
};
