<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->text('description')->nullable(); $table->timestamps();
        });
        Schema::create('articles', function (Blueprint $table) {
            $table->id(); $table->foreignId('category_id')->constrained()->cascadeOnDelete(); $table->string('title'); $table->string('slug')->unique(); $table->text('excerpt'); $table->longText('content'); $table->string('image')->nullable(); $table->string('author')->default('Redacția DataShield'); $table->date('published_at'); $table->unsignedSmallInteger('reading_time')->default(5); $table->boolean('is_featured')->default(false); $table->timestamps();
        });
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email'); $table->string('subject'); $table->text('message'); $table->timestamp('privacy_accepted_at'); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages'); Schema::dropIfExists('articles'); Schema::dropIfExists('categories');
    }
};
