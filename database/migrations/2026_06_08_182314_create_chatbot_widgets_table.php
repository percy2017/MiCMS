<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_widgets', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('title')->default('Asistente virtual');
            $table->string('subtitle')->nullable();
            $table->text('greeting')->nullable();
            $table->string('primary_color', 16)->default('#2563eb');
            $table->string('position', 8)->default('right');
            $table->foreignId('avatar_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('require_auth')->default(true);
            $table->boolean('show_typing')->default(true);
            $table->text('offline_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_widgets');
    }
};
