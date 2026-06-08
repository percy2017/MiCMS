<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('menu_label')->nullable();
            $table->string('version', 20)->default('1.0.0');
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('category', 50)->default('general');
            $table->string('icon', 50)->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('installed')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
