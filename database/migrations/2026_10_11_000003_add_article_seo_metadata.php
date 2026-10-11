<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('focus_keyword', 160)->nullable();
            $table->json('secondary_keywords')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords']);
        });
    }
};
