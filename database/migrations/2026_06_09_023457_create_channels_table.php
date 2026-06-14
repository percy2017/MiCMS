<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->text('config')->nullable();
            $table->json('settings')->nullable();
            $table->string('allowed_domain')->nullable()->after('settings');
            $table->string('public_key', 32)->nullable()->unique()->after('allowed_domain');
            $table->string('webhook_token', 32)->nullable()->unique()->after('public_key');
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
