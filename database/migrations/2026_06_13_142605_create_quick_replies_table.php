<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_replies', function (Blueprint $table) {
            $table->id();
            $table->string('shortcut', 50)->unique();
            $table->string('title', 100);
            $table->text('content')->nullable();
            $table->string('category', 50)->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->integer('sort')->default(0);
            $table->boolean('enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['enabled', 'category', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_replies');
    }
};
