<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create default web_widget channel from first widget config
        $oldWidget = DB::table('chatbot_widgets')->first();
        $channelId = DB::table('channels')->insertGetId([
            'type' => 'web_widget',
            'name' => 'Widget Web',
            'enabled' => true,
            'config' => null,
            'settings' => json_encode($oldWidget ? [
                'title' => $oldWidget->title ?? 'Asistente virtual',
                'subtitle' => $oldWidget->subtitle ?? 'Te respondemos en minutos',
                'greeting' => $oldWidget->greeting ?? '¡Hola! ¿En qué podemos ayudarte?',
                'position' => $oldWidget->position ?? 'right',
                'primary_color' => $oldWidget->primary_color ?? '#2563eb',
                'require_auth' => $oldWidget->require_auth ?? true,
                'show_typing' => $oldWidget->show_typing ?? true,
                'offline_message' => $oldWidget->offline_message ?? null,
            ] : []),
            'sort' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Migrate conversations
        $oldConversations = DB::table('chatbot_conversations')->get();
        $idMap = [];
        foreach ($oldConversations as $old) {
            $newId = DB::table('conversations')->insertGetId([
                'channel_id' => $channelId,
                'user_id' => $old->user_id,
                'external_id' => null,
                'visitor_name' => $old->visitor_name,
                'visitor_email' => $old->visitor_email,
                'page_url' => $old->page_url,
                'status' => $old->status ?? 'open',
                'assigned_to' => $old->assigned_to,
                'last_message_at' => $old->last_message_at,
                'unread_by_admin' => $old->unread_by_admin ?? 0,
                'created_at' => $old->created_at ?? now(),
                'updated_at' => $old->updated_at ?? now(),
            ]);
            $idMap[$old->id] = $newId;
        }

        // 3. Migrate messages
        $oldMessages = DB::table('chatbot_messages')->orderBy('id')->get();
        foreach ($oldMessages as $old) {
            $newConvId = $idMap[$old->conversation_id] ?? null;
            if (! $newConvId) {
                continue;
            }
            DB::table('messages')->insert([
                'conversation_id' => $newConvId,
                'role' => $old->role,
                'type' => $old->type ?? 'text',
                'content' => $old->content,
                'external_id' => null,
                'metadata' => $old->metadata,
                'attachment_media_id' => $old->attachment_media_id,
                'delivered_at' => $old->delivered_at,
                'read_at' => $old->read_at,
                'created_at' => $old->created_at ?? now(),
                'updated_at' => $old->updated_at ?? now(),
            ]);
        }

        // 4. Update autoincrement
        $maxConv = DB::table('conversations')->max('id');
        if ($maxConv) {
            DB::statement('UPDATE SQLITE_SEQUENCE SET seq = ? WHERE name = ?', [$maxConv, 'conversations']);
        }
        $maxMsg = DB::table('messages')->max('id');
        if ($maxMsg) {
            DB::statement('UPDATE SQLITE_SEQUENCE SET seq = ? WHERE name = ?', [$maxMsg, 'messages']);
        }
    }

    public function down(): void
    {
        DB::table('channels')->where('type', 'web_widget')->delete();
        DB::table('conversations')->delete();
        DB::table('messages')->delete();
    }
};
