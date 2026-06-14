<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps();

            $table->index(['mime_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
