<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('chatbot_messages');
        Schema::dropIfExists('chatbot_conversations');
        Schema::dropIfExists('chatbot_widgets');
    }

    public function down(): void
    {
        // recreate empty tables for rollback safety
        Schema::create('chatbot_widgets', function ($table) {
            $table->id();
            $table->timestamps();
        });
        Schema::create('chatbot_conversations', function ($table) {
            $table->id();
            $table->timestamps();
        });
        Schema::create('chatbot_messages', function ($table) {
            $table->id();
            $table->timestamps();
        });
    }
};
