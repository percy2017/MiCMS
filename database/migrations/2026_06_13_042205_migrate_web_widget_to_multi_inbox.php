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
            $table->json('allowed_domains')->nullable()->after('settings');
            $table->string('public_key', 32)->nullable()->unique()->after('allowed_domains');
        });

        DB::table('channels')
            ->where('type', 'web_widget')
            ->whereNull('public_key')
            ->orderBy('id')
            ->each(function (object $row): void {
                $key = substr(bin2hex(random_bytes(8)), 0, 16);
                DB::table('channels')
                    ->where('id', $row->id)
                    ->update(['public_key' => $key]);
            });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table): void {
            $table->dropColumn(['allowed_domains', 'public_key']);
        });
    }
};
