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
        Schema::create('expansions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedSmallInteger('sort_order')->index();
            $table->timestamps();
        });

        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('short_name', 64)->unique();
            $table->unsignedInteger('eq_zone_id')->nullable();
            $table->string('name');
            $table->foreignId('expansion_id')->constrained();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->unsignedInteger('version');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 10);
            $table->string('status', 12)->default('pending');
            $table->boolean('is_official')->default(false);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size');
            $table->char('md5', 32);
            $table->char('sha256', 64);
            $table->text('notes')->nullable();
            $table->string('depot_commit', 40)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->timestamps();

            $table->unique(['zone_id', 'type', 'version']);
            $table->index(['zone_id', 'type', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('sha256');
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('expansions');
    }
};
