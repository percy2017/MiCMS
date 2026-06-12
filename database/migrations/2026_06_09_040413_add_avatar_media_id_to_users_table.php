<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('avatar_media_id')
                ->nullable()
                ->constrained('media')
                ->nullOnDelete()
                ->after('password');
            $table->string('phone')->nullable()->after('avatar_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeignIdFor('avatar_media_id');
            $table->dropColumn('phone');
        });
    }
};
