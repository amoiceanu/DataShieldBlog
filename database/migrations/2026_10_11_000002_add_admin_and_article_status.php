<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false);
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->string('status', 20)->default('published')->index();
            $table->date('published_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('articles')->whereNull('published_at')->update(['published_at' => now()->toDateString()]);

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
            $table->date('published_at')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_admin');
        });
    }
};
