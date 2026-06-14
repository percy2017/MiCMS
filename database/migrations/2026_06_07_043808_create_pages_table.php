<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('draft');
            $table->json('puck_data')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_home')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('deleted_at');
        });

        DB::statement('CREATE UNIQUE INDEX pages_is_home_unique ON pages (is_home) WHERE is_home = 1');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pages_is_home_unique');

        Schema::dropIfExists('pages');
    }
};
