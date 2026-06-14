<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estandariza las tablas `conversations` y `messages` para soportar
     * TODOS los tipos de inbox (Evolution, OpenWa, Web Widget, y futuros
     * como Telegram, Correo, Messenger, Instagram).
     *
     Cambios:
     * 1. `conversations`: agrega `external_thread_id` para threading (Correo
     *    usa In-Reply-To / References, Telegram usa message_thread_id).
     * 2. `conversations`: agrega índices faltantes en user_id, assigned_to,
     *    last_message_at (performance para sort + filter + badge counts).
     * 3. `messages`: agrega `reply_to_message_id` (FK nullable) para
     *    soportar replies nativos de Telegram, Correo, etc.
     * 4. `messages`: agrega índice en `conversation_id` simple + índice
     *    en `external_id` ya existe.
     *
     * NO destructivo: solo agrega columnas e índices. No elimina nada.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('external_thread_id', 255)->nullable()->after('external_id');

            $table->index('user_id', 'conversations_user_id_index');
            $table->index('assigned_to', 'conversations_assigned_to_index');
            $table->index('last_message_at', 'conversations_last_message_at_index');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->after('external_id')
                ->constrained('messages')
                ->nullOnDelete();

            $table->index('conversation_id', 'messages_conversation_id_index');
            $table->index('type', 'messages_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['reply_to_message_id']);
            $table->dropIndex('messages_conversation_id_index');
            $table->dropIndex('messages_type_index');
            $table->dropColumn('reply_to_message_id');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_user_id_index');
            $table->dropIndex('conversations_assigned_to_index');
            $table->dropIndex('conversations_last_message_at_index');
            $table->dropColumn('external_thread_id');
        });
    }
};
