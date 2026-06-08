<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_widget_messages', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->index();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('message');
            $table->string('direction', 16)->default('incoming');
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_widget_messages');
    }
};
