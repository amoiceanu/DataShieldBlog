<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexability_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20)->default('queued')->index();
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('indexability_audit_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_id')->constrained('indexability_audits')->cascadeOnDelete();
            $table->string('title');
            $table->string('url', 2048);
            $table->string('type', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->boolean('sitemap_included')->default(false);
            $table->boolean('discovered')->default(false);
            $table->string('local_status', 32)->default('unknown')->index();
            $table->string('google_status', 64)->nullable();
            $table->json('problems')->nullable();
            $table->json('google_result')->nullable();
            $table->timestamp('content_updated_at')->nullable();
            $table->timestamps();
            $table->unique(['audit_id', 'url']);
        });

        Schema::create('google_search_console_connections', function (Blueprint $table): void {
            $table->id();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('property', 255)->default('sc-domain:securitatedigitala.ro');
            $table->json('sitemap_status')->nullable();
            $table->string('last_sitemap_hash', 64)->nullable();
            $table->json('performance_data')->nullable();
            $table->timestamp('last_sitemap_submission_at')->nullable();
            $table->timestamp('last_google_refresh_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_search_console_connections');
        Schema::dropIfExists('indexability_audit_items');
        Schema::dropIfExists('indexability_audits');
    }
};
