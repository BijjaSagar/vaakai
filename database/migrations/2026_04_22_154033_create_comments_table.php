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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('urls')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
            $table->string('guest_name', 80)->nullable();
            $table->text('body');
            $table->enum('sentiment', ['positive', 'negative', 'neutral']);
            $table->integer('likes_count')->default(0);
            $table->integer('dislikes_count')->default(0);
            $table->string('ip_address', 45);
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();
            
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
