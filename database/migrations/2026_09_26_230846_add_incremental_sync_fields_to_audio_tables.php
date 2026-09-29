<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversation_versions', function (Blueprint $table) {
            $table->string('sync_key')->nullable()->after('status');
            $table->unsignedInteger('expected_audio_assets_count')->nullable()->after('sync_key');
            $table->unique(['avatar_id', 'sync_key']);
        });

        Schema::table('audio_assets', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable()->after('error');
            $table->index(['conversation_version_id', 'status', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_assets', function (Blueprint $table) {
            $table->dropIndex(['conversation_version_id', 'status', 'synced_at']);
            $table->dropColumn('synced_at');
        });

        Schema::table('conversation_versions', function (Blueprint $table) {
            $table->dropUnique(['avatar_id', 'sync_key']);
            $table->dropColumn(['sync_key', 'expected_audio_assets_count']);
        });
    }
};
