<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_tips', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->string('slug')->unique();
            $table->text('body');
            $table->string('status', 20)->default('draft')->index();
            $table->date('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_tips');
    }
};
