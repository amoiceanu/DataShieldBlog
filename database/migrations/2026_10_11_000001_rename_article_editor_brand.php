<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_AUTHOR = 'Redacția DataShield';

    private const CURRENT_AUTHOR = 'Redacția Securitate Digitală';

    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('author')->default(self::CURRENT_AUTHOR)->change();
        });

        DB::table('articles')
            ->where('author', self::LEGACY_AUTHOR)
            ->update(['author' => self::CURRENT_AUTHOR]);
    }

    public function down(): void
    {
        DB::table('articles')
            ->where('author', self::CURRENT_AUTHOR)
            ->update(['author' => self::LEGACY_AUTHOR]);

        Schema::table('articles', function (Blueprint $table): void {
            $table->string('author')->default(self::LEGACY_AUTHOR)->change();
        });
    }
};
