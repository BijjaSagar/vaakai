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
        Schema::create('urls', function (Blueprint $table) {
            $table->id();
            $table->string('url_hash', 32)->unique();
            $table->text('original_url');
            $table->text('normalized_url');
            $table->string('slug', 12)->unique();
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->string('domain', 255)->index();
            $table->timestamp('og_fetched_at')->nullable();
            $table->integer('comment_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('urls');
    }
};
