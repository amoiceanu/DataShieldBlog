<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exposure_settings', function (Blueprint $t) {
            $t->id();
            $t->json('options');
            $t->timestamps();
        });
        Schema::create('exposure_reports', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('owner', 64)->index();
            $t->string('address_hmac', 64)->nullable()->index();
            $t->string('module');
            $t->string('match_type');
            $t->text('subject');
            $t->text('results')->nullable();
            $t->string('state');
            $t->timestamp('checked_at')->nullable();
            $t->timestamp('expires_at')->index();
            $t->timestamps();
        });
        Schema::create('exposure_challenges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('report_id')->constrained('exposure_reports')->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('exposure_usage', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->string('provider');
            $t->unsignedInteger('api_calls')->default(0);
            $t->unsignedInteger('checks')->default(0);
            $t->unsignedInteger('errors')->default(0);
            $t->unsignedInteger('completed')->default(0);
            $t->unique(['day', 'provider']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_exposure_admin')->default(false));
    }

    public function down(): void
    {
        Schema::dropIfExists('exposure_challenges');
        Schema::dropIfExists('exposure_reports');
        Schema::dropIfExists('exposure_usage');
        Schema::dropIfExists('exposure_settings');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_exposure_admin'));
    }
};
