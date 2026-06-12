<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_jid')->nullable()->after('phone');
            $table->boolean('is_whatsapp_business')->default(false)->after('whatsapp_jid');
            $table->json('business_data')->nullable()->after('is_whatsapp_business');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_jid', 'is_whatsapp_business', 'business_data']);
        });
    }
};
