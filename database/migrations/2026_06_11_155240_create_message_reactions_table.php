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
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('user_jid', 120);
            $table->string('emoji', 16);
            $table->string('external_id')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_jid', 'emoji'], 'msg_reaction_unique');
            $table->index('message_id');
            $table->index('user_jid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
