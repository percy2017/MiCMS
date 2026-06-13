<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table): void {
            $table->dropColumn('allowed_domains');
            $table->string('allowed_domain')->nullable()->after('settings');
            $table->string('webhook_token', 32)->nullable()->unique()->after('allowed_domain');
        });

        DB::table('channels')
            ->where('type', 'web_widget')
            ->whereNull('webhook_token')
            ->orderBy('id')
            ->each(function (object $row): void {
                $token = substr(bin2hex(random_bytes(16)), 0, 32);
                DB::table('channels')
                    ->where('id', $row->id)
                    ->update(['webhook_token' => $token]);
            });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table): void {
            $table->dropColumn(['allowed_domain', 'webhook_token']);
            $table->json('allowed_domains')->nullable()->after('settings');
        });
    }
};
