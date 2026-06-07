<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->boolean('is_home')->default(false);
        });

        DB::statement('CREATE UNIQUE INDEX pages_is_home_unique ON pages (is_home) WHERE is_home = 1');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pages_is_home_unique');

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('is_home');
        });
    }
};
