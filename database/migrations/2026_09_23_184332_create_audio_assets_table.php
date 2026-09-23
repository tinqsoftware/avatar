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
        Schema::create('audio_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_version_id')->constrained()->cascadeOnDelete();
            $table->string('asset_key');
            $table->text('text');
            $table->string('path')->nullable();
            $table->string('mime_type')->default('audio/mpeg');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('visemes')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['conversation_version_id', 'asset_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_assets');
    }
};
